<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Models\Client;
use App\Models\Country;
use App\Models\PhoneVerification;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Inscription et connexion des clients de l'application mobile.
 *
 *  1. le client saisit son pays + téléphone
 *  2. inscription / PIN oublié : un code à 6 chiffres est envoyé par SMS (10 min, 5 essais)
 *  3. il choisit un PIN à 4 chiffres (haché) ; connexion ensuite par téléphone + PIN
 *  4. après 5 PIN erronés, le compte est verrouillé 15 minutes
 *
 * Un client déjà créé par un agent au guichet (même numéro) récupère son historique.
 */
class ClientAuthService
{
    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_RESET_PIN = 'reset_pin';

    /** PIN trop faciles à deviner. */
    private const WEAK_PINS = ['0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '1234', '4321', '1212', '0123', '9876', '2580'];

    protected SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    /** @throws TransferException */
    public function resolvePhone(int $countryId, string $phone): array
    {
        $country = Country::find($countryId);
        if (!$country) {
            throw new TransferException('Pays inconnu.');
        }
        $national = Phone::normalize($phone, $country);
        if (!Phone::isPlausible($national)) {
            throw new TransferException('Numéro de téléphone invalide.');
        }

        return [$country, $national];
    }

    public function findClient(string $national): ?Client
    {
        return Client::where('phone', $national)->first();
    }

    /**
     * Envoie un code SMS. Renvoie le code en clair UNIQUEMENT si CLIENT_OTP_DEBUG=true (développement).
     *
     * @throws TransferException
     */
    public function sendOtp(Country $country, string $national, string $purpose): ?string
    {
        $client = $this->findClient($national);

        if ($purpose === self::PURPOSE_REGISTER && $client && $client->hasAppAccess()) {
            throw new TransferException('Ce numéro a déjà un compte. Connectez-vous avec votre code PIN.', 409);
        }
        if ($purpose === self::PURPOSE_RESET_PIN && (!$client || !$client->hasAppAccess())) {
            throw new TransferException("Aucun compte n'est associé à ce numéro.", 404);
        }

        $code = (string) random_int(100000, 999999);

        // Un seul code actif par numéro et par usage.
        PhoneVerification::where('phone', $national)->where('purpose', $purpose)->whereNull('used_at')->delete();
        $verification = PhoneVerification::create([
            'phone' => $national,
            'country_id' => $country->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('transfer.client.otp_ttl_minutes', 10)),
        ]);

        try {
            $this->sms->sendSms(
                $national,
                "Votre code de verification Money Transfer : {$code}. Il expire dans "
                . (int) config('transfer.client.otp_ttl_minutes', 10) . " minutes. Ne le communiquez a personne.",
                $country->phone_code,
                'mt-otp-' . $verification->id
            );
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLogger::log('client.otp_sent', $client, ['purpose' => $purpose, 'phone' => $national]);

        return config('transfer.client.otp_debug') ? $code : null;
    }

    /** @throws TransferException */
    public function consumeOtp(string $national, string $purpose, string $code): void
    {
        $verification = PhoneVerification::where('phone', $national)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (!$verification || $verification->expires_at->isPast()) {
            throw new TransferException('Code expiré. Demandez un nouveau code.', 410);
        }
        if ($verification->attempts >= (int) config('transfer.client.otp_max_attempts', 5)) {
            throw new TransferException('Trop d\'essais. Demandez un nouveau code.', 429);
        }
        if (!Hash::check(trim($code), $verification->code_hash)) {
            $verification->increment('attempts');
            throw new TransferException('Code incorrect.', 422);
        }

        $verification->update(['used_at' => now()]);
    }

    /**
     * Inscription (ou activation d'un client déjà connu au guichet).
     *
     * @throws TransferException
     */
    public function register(Country $country, string $national, string $otp, string $name, string $pin): Client
    {
        $this->assertStrongPin($pin);
        $this->consumeOtp($national, self::PURPOSE_REGISTER, $otp);

        return DB::transaction(function () use ($country, $national, $name, $pin) {
            $client = Client::where('phone', $national)->lockForUpdate()->first();

            if ($client && $client->hasAppAccess()) {
                throw new TransferException('Ce numéro a déjà un compte.', 409);
            }

            $data = [
                'pin_hash' => Hash::make($pin),
                'phone_verified_at' => now(),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
                'is_active' => true,
            ];

            if ($client) {
                $client->update($data + ['country_id' => $client->country_id ?: $country->id]);
            } else {
                $client = Client::create($data + [
                    'uuid' => (string) Str::uuid(),
                    'name' => trim($name),
                    'phone' => $national,
                    'country_id' => $country->id,
                ]);
            }

            AuditLogger::log('client.registered', $client, ['claimed_existing' => !$client->wasRecentlyCreated]);

            return $client;
        });
    }

    /** @throws TransferException */
    public function login(string $national, string $pin): Client
    {
        $client = $this->findClient($national);

        if (!$client || !$client->hasAppAccess()) {
            throw new TransferException('Numéro ou code PIN incorrect.', 401);
        }
        if ($client->is_active === false) {
            throw new TransferException('Compte suspendu. Contactez le service client.', 403);
        }
        if ($client->isPinLocked()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($client->pin_locked_until) / 60));
            throw new TransferException("Compte verrouillé après plusieurs codes erronés. Réessayez dans {$minutes} min ou réinitialisez votre PIN.", 423);
        }

        if (!Hash::check($pin, $client->pin_hash)) {
            $attempts = $client->pin_failed_attempts + 1;
            $max = (int) config('transfer.client.pin_max_attempts', 5);
            $update = ['pin_failed_attempts' => $attempts];
            if ($attempts >= $max) {
                $update['pin_locked_until'] = now()->addMinutes((int) config('transfer.client.pin_lock_minutes', 15));
                $update['pin_failed_attempts'] = 0;
            }
            $client->update($update);
            AuditLogger::log('client.login_failed', $client);

            $left = $max - $attempts;
            throw new TransferException(
                $left > 0 ? "Code PIN incorrect. Il vous reste {$left} essai(s)." : 'Compte verrouillé pour 15 minutes.',
                401
            );
        }

        $client->update(['pin_failed_attempts' => 0, 'pin_locked_until' => null, 'last_login_at' => now()]);
        AuditLogger::log('client.login', $client);

        return $client;
    }

    /** @throws TransferException */
    public function resetPin(string $national, string $otp, string $pin): Client
    {
        $this->assertStrongPin($pin);
        $client = $this->findClient($national);
        if (!$client || !$client->hasAppAccess()) {
            throw new TransferException("Aucun compte n'est associé à ce numéro.", 404);
        }

        $this->consumeOtp($national, self::PURPOSE_RESET_PIN, $otp);

        $client->update([
            'pin_hash' => Hash::make($pin),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ]);
        // Toutes les sessions ouvertes sont fermées.
        $client->tokens()->delete();
        AuditLogger::log('client.pin_reset', $client);

        return $client;
    }

    /** @throws TransferException */
    public function changePin(Client $client, string $currentPin, string $newPin): void
    {
        if (!Hash::check($currentPin, $client->pin_hash)) {
            throw new TransferException('Code PIN actuel incorrect.', 422);
        }
        $this->assertStrongPin($newPin);
        $client->update(['pin_hash' => Hash::make($newPin)]);
        AuditLogger::log('client.pin_changed', $client);
    }

    /** @throws TransferException */
    private function assertStrongPin(string $pin): void
    {
        if (!preg_match('/^\d{4}$/', $pin)) {
            throw new TransferException('Le code PIN doit contenir 4 chiffres.');
        }
        if (in_array($pin, self::WEAK_PINS, true)) {
            throw new TransferException('Code PIN trop simple. Choisissez-en un autre.');
        }
    }
}

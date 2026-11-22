<?php


namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $twilioSid;
    protected $twilioToken;
    protected $twilioPhone;
    protected $twilioWhatsApp; 

    public function __construct()
    {
        $this->twilioSid = config('services.twilio.sid');
        $this->twilioToken = config('services.twilio.token');
        $this->twilioPhone = config('services.twilio.phone');
        $this->twilioWhatsApp = config('services.twilio.whatsapp');
    }

    /**
     * Formats phone number to E.164 standard
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber($phone)
    {
        // 1. Remove all spaces, dashes, parentheses
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        // 2. Handle "+..." format (Already clean, just return)
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        // 3. Handle "00..." format (Replace 00 with +)
        if (str_starts_with($clean, '00')) {
            return '+' . substr($clean, 2);
        }

        // 4. Handle number starting with country code without + (e.g. 22177...)
        // We check common codes for this app context
        $knownCodes = ['221', '225', '33', '1'];
        foreach ($knownCodes as $code) {
            if (str_starts_with($clean, $code)) {
                return '+' . $clean;
            }
        }

        // 5. Default: Assume it's a local Senegal number if no code found
        // Prepend +221
        return '+221' . $clean;
    }

    public function sendSms($to, $message)
    {
        $to = $this->formatPhoneNumber($to);

        try {
            $response = Http::withBasicAuth($this->twilioSid, $this->twilioToken)
                ->asForm()
                ->post('https://api.twilio.com/2010-04-01/Accounts/' . $this->twilioSid . '/Messages.json', [
                    'To' => $to,
                    'From' => $this->twilioPhone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', ['to' => $to, 'message' => $message]);
                return true;
            }

            Log::error('Failed to send SMS', [
                'to' => $to,
                'message' => $message,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending SMS', [
                'to' => $to,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendWhatsApp($to, $message)
    {
        $to = $this->formatPhoneNumber($to);

        try {
            $response = Http::withBasicAuth($this->twilioSid, $this->twilioToken)
                ->asForm()
                ->post('https://api.twilio.com/2010-04-01/Accounts/' . $this->twilioSid . '/Messages.json', [
                    'To' => 'whatsapp:' . $to,
                    'From' => 'whatsapp:' . $this->twilioWhatsApp,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp sent successfully', ['to' => $to, 'message' => $message]);
                return true;
            }

            Log::error('Failed to send WhatsApp', [
                'to' => $to,
                'message' => $message,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp', [
                'to' => $to,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifySender(array $transaction): void
    {
        $message = "✅ TRANSFERPRO\n\n"
            . "Vous avez envoyé {$transaction['amount']} {$transaction['currency']} "
            . "à {$transaction['recipient_name']}.\n\n"
            . "Code: {$transaction['reference']}\n"
            . "Frais: {$transaction['fee']} {$transaction['currency']}";
        // dd('sender_phone', $transaction['sender_phone'], $message);
        $this->sendSms($transaction['sender_phone'], $message);
        $this->sendWhatsapp($transaction['sender_phone'], $message);
    } 

    public function notifyRecipient(array $transaction): void
    {
        $message = "💰 TRANSFERPRO\n\n"
            . "{$transaction['sender_name']} vous envoie "
            . "{$transaction['amount']} {$transaction['currency']}.\n\n"
            . "🔑 Code de retrait: {$transaction['reference']}\n\n"
            . "Présentez ce code dans une agence pour retirer.";
        // dd('recipient_phone', $transaction['recipient_phone']);
        $this->sendSms($transaction['recipient_phone'], $message);
        $this->sendWhatsapp($transaction['recipient_phone'], $message);
    }

    public function notifyWithdrawal(array $transaction): void
    {
        // Message au destinataire (celui qui a retiré)
        $msgRecipient = "✅ RETRAIT EFFECTUÉ\n\n"
            . "Vous avez retiré {$transaction['amount']} {$transaction['currency']}.\n"
            . "Réf: {$transaction['reference']}";
        $this->sendSms($transaction['recipient_phone'], $msgRecipient);
        // Message à l'expéditeur (pour l'informer)
        $msgSender = "📢 TRANSFERPRO\n\n"
            . "{$transaction['recipient_name']} a retiré les "
            . "{$transaction['amount']} {$transaction['currency']} "
            . "que vous avez envoyés.\n"
            . "Réf: {$transaction['reference']}";
        $this->sendSms($transaction['sender_phone'], $msgSender);
    }
}
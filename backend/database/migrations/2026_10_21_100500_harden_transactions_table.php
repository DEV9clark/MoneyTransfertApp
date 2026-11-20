<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sécurisation et traçabilité des transactions :
 *  - le code de retrait n'est plus stocké en clair : seule son empreinte HMAC est conservée
 *  - "reference" devient un numéro de suivi public (imprimé sur les reçus), distinct du code secret
 *  - expiration, annulation, pièces d'identité, agents expéditeur/payeur, numéros de reçus
 *  - clés étrangères et index manquants
 */
class HardenTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('withdrawal_code_hash', 64)->nullable()->after('reference');
            $table->foreignId('destination_country_id')->nullable()->after('destination')
                ->constrained('countries')->nullOnDelete();

            $table->string('sender_id_type', 30)->nullable()->after('recipient_phone');
            $table->string('sender_id_number', 50)->nullable()->after('sender_id_type');
            $table->string('recipient_id_type', 30)->nullable()->after('sender_id_number');
            $table->string('recipient_id_number', 50)->nullable()->after('recipient_id_type');

            $table->foreignId('created_by')->nullable()->after('payout_wallet_id')->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('paid_by')->constrained('users')->nullOnDelete();

            $table->string('sender_receipt_number', 40)->nullable();
            $table->string('payout_receipt_number', 40)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
        });

        // Un rechargement de float (type "topup") n'a ni client ni portefeuille expéditeur.
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->unsignedBigInteger('sender_wallet_id')->nullable()->change();
        });

        $this->migrateExistingRows();

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('reference');
            $table->unique('withdrawal_code_hash');
            $table->unique('sender_receipt_number');
            $table->unique('payout_receipt_number');
            $table->index(['status', 'type']);
            $table->index('created_at');

            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('payout_wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });
    }

    /**
     * Les anciens envois "pending" avaient le code en clair dans "reference".
     * On conserve ce code (empreinte) pour qu'il reste utilisable, puis on attribue
     * à chaque ligne un vrai numéro de suivi et des numéros de reçus.
     */
    private function migrateExistingRows(): void
    {
        $key = config('app.key');
        $ttl = (int) config('transfer.code_ttl_days', 30);

        DB::table('transactions')->orderBy('id')->chunkById(200, function ($rows) use ($key, $ttl) {
            foreach ($rows as $row) {
                $created = \Carbon\Carbon::parse($row->created_at);
                $update = [
                    'reference' => 'MT' . $created->format('ymd') . '-' . strtoupper(Str::random(6)) . $row->id,
                    'sender_receipt_number' => $row->type === 'send'
                        ? 'DEP-' . $created->format('Ymd') . '-' . str_pad($row->id, 6, '0', STR_PAD_LEFT)
                        : null,
                ];

                if ($row->status === 'pending' && $row->reference) {
                    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $row->reference));
                    $update['withdrawal_code_hash'] = hash_hmac('sha256', $code, $key);
                    $update['expires_at'] = $created->copy()->addDays($ttl);
                }

                if ($row->status === 'completed') {
                    $paidAt = \Carbon\Carbon::parse($row->updated_at);
                    $update['paid_at'] = $paidAt;
                    $update['payout_receipt_number'] = 'RET-' . $paidAt->format('Ymd') . '-' . str_pad($row->id, 6, '0', STR_PAD_LEFT);
                }

                DB::table('transactions')->where('id', $row->id)->update($update);
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['payout_wallet_id']);
            $table->dropForeign(['destination_country_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['paid_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropUnique(['reference']);
            $table->dropUnique(['withdrawal_code_hash']);
            $table->dropUnique(['sender_receipt_number']);
            $table->dropUnique(['payout_receipt_number']);
            $table->dropIndex(['status', 'type']);
            $table->dropIndex(['created_at']);
            $table->dropColumn([
                'withdrawal_code_hash', 'destination_country_id',
                'sender_id_type', 'sender_id_number', 'recipient_id_type', 'recipient_id_number',
                'created_by', 'paid_by', 'cancelled_by',
                'sender_receipt_number', 'payout_receipt_number',
                'expires_at', 'paid_at', 'cancelled_at', 'cancel_reason', 'notes',
            ]);
        });
    }
}

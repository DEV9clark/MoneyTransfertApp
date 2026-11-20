<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pré-envois créés par les clients dans l'application :
 *  - statut "awaiting_payment" tant que le client n'a pas payé en agence
 *  - payment_code : code court que le client présente à l'agent
 *  - channel : agent (guichet) ou client_app (application mobile)
 */
class AddPreorderFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_code', 12)->nullable()->unique()->after('reference');
            $table->string('channel', 20)->default('agent')->after('type');
            $table->timestamp('funded_at')->nullable()->after('expires_at');
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropUnique(['payment_code']);
            $table->dropColumn(['payment_code', 'channel', 'funded_at']);
        });
    }
}

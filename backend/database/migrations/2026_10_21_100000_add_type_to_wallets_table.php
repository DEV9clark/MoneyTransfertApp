<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un type de portefeuille :
 *  - agent   : portefeuille de float d'un agent (un seul par agent)
 *  - revenue : compte de commissions de la société (un par devise), où sont crédités les frais.
 */
class AddTypeToWalletsTable extends Migration
{
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('type', 20)->default('agent')->after('user_id');
            $table->index(['type', 'currency']);
        });
    }

    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex(['type', 'currency']);
            $table->dropColumn('type');
        });
    }
}

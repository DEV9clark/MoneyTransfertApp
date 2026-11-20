<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * max_amount NULL = tranche sans plafond. Le code et le formulaire l'autorisaient déjà,
 * mais la colonne était NOT NULL (erreur SQL à l'enregistrement).
 */
class MakeTariffsMaxAmountNullable extends Migration
{
    public function up()
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->decimal('max_amount', 15, 2)->nullable()->change();
            $table->index(['type', 'min_amount']);
        });
    }

    public function down()
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropIndex(['type', 'min_amount']);
        });
    }
}

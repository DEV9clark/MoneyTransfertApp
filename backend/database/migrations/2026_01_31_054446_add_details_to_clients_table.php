<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetailsToClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->unique();
            $table->string('email')->nullable()->after('name');
            $table->string('address')->nullable()->after('phone');
            $table->unsignedBigInteger('country_id')->nullable()->after('address');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn(['uuid', 'email', 'address', 'country_id']);
        });
    }
}

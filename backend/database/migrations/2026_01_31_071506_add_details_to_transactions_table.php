<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetailsToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('destination')->nullable()->after('currency'); // or country_id
            $table->string('recipient_name')->nullable()->after('destination');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->decimal('fee_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('total_amount', 15, 2)->default(0)->after('fee_amount'); // amount + fee
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
}

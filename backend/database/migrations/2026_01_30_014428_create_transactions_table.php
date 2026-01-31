<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sender_wallet_id');
            // $table->unsignedBigInteger('receiver_wallet_id'); // REMOVED
            $table->unsignedBigInteger('client_id'); // ADDED: The beneficiary client
            $table->unsignedBigInteger('payout_wallet_id')->nullable(); // ADDED: Agent B wallet, filled on withdraw
            
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->string('type'); // transfer, deposit, withdrawal
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->foreign('sender_wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            // $table->foreign('receiver_wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            // $table->foreign('client_id')->references('id')->on('clients'); // Optional, strictness
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}

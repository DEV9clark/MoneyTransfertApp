<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes de vérification SMS (inscription, PIN oublié). Stockés hachés, à usage unique.
 */
class CreatePhoneVerificationsTable extends Migration
{
    public function up()
    {
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('purpose', 20); // register | reset_pin
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('phone_verifications');
    }
}

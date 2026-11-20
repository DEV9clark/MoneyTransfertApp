<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accès des clients à l'application mobile : téléphone vérifié par SMS + code PIN.
 * Le PIN n'est jamais stocké en clair (hash bcrypt).
 */
class AddAppAccessToClientsTable extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('id_number');
            $table->timestamp('phone_verified_at')->nullable()->after('pin_hash');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('phone_verified_at');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_attempts');
            $table->timestamp('last_login_at')->nullable()->after('pin_locked_until');
            $table->boolean('is_active')->default(true)->after('last_login_at');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'phone_verified_at', 'pin_failed_attempts', 'pin_locked_until', 'last_login_at', 'is_active']);
        });
    }
}

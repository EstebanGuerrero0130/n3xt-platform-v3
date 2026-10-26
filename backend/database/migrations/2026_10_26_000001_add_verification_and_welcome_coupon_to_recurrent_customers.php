<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación del correo por código y cupón de bienvenida personal.
 * Sin verificar, el cliente no ve pedidos: cualquiera podía registrarse con el
 * correo de otra persona y leer los suyos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrent_customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_code_hash')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->string('welcome_coupon', 32)->nullable()->unique();
            $table->timestamp('welcome_coupon_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurrent_customers', function (Blueprint $table) {
            $table->dropUnique(['welcome_coupon']);
            $table->dropColumn([
                'email_verified_at', 'verification_code_hash', 'verification_expires_at',
                'verification_attempts', 'welcome_coupon', 'welcome_coupon_used_at',
            ]);
        });
    }
};

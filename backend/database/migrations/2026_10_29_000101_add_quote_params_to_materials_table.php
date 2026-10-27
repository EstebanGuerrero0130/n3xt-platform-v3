<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parámetros del cotizador por material (null = valor por defecto):
     * speed_factor multiplica el tiempo de impresión (1), waste_pct es material extra (0 %)
     * y failure_pct reemplaza la reserva por fallos global (oper.fallos).
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('speed_factor', 6, 3)->nullable();
            $table->decimal('waste_pct', 5, 2)->nullable();
            $table->decimal('failure_pct', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['speed_factor', 'waste_pct', 'failure_pct']);
        });
    }
};

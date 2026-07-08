<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OrcaEngine v2 — Printer Profile Fields
 *
 * Agrega los campos necesarios para que OrcaEngine calcule velocidades
 * y geometría de shell basados en el perfil real de cada impresora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            // Nozzle diameter in mm (0.2, 0.4, 0.6, 0.8...)
            $table->decimal('nozzle_mm', 4, 2)->default(0.4)->after('technology');
            // Number of perimeter walls (default: 2 = Bambu standard)
            $table->unsignedTinyInteger('wall_count')->default(2)->after('nozzle_mm');
            // Maximum volumetric flow in mm³/h for print time calculation
            $table->unsignedInteger('max_flow_mm3_hr')->default(15500)->after('wall_count');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['nozzle_mm', 'wall_count', 'max_flow_mm3_hr']);
        });
    }
};

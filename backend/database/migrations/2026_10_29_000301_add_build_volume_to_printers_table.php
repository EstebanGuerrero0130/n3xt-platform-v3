<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Volumen de impresión de cada máquina (mm): avisos de "no cabe" y acomodo en cama. */
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->unsignedInteger('build_x_mm')->nullable();
            $table->unsignedInteger('build_y_mm')->nullable();
            $table->unsignedInteger('build_z_mm')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['build_x_mm', 'build_y_mm', 'build_z_mm']);
        });
    }
};

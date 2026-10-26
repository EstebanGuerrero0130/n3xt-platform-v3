<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cuándo se avisó por correo (stock bajo / mantenimiento), para no repetir el aviso. */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->timestamp('low_stock_alerted_at')->nullable();
        });
        Schema::table('printers', function (Blueprint $table) {
            $table->timestamp('maintenance_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('low_stock_alerted_at');
        });
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn('maintenance_alerted_at');
        });
    }
};

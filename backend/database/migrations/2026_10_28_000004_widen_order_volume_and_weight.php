<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DECIMAL(10,2) llega a ~1e8: un pedido de 100 cubos macizos de 10 cm ya no cabía
     * y Postgres rechazaba el pedido (SQLite lo aceptaba, así que las pruebas no lo veían).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('volume_mm3', 16, 2)->default(0)->change();
            $table->decimal('estimated_weight_g', 14, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('volume_mm3', 10, 2)->default(0)->change();
            $table->decimal('estimated_weight_g', 10, 2)->nullable()->change();
        });
    }
};

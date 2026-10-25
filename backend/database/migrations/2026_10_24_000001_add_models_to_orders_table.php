<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modelos de un pedido multi-archivo:
     * [{path, original_filename, qty, scale_factor, dimensions_mm, volume_mm3, weight_g, duration_h, subtotal}]
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('models')->nullable()->after('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('models');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('dimensions_mm')->nullable()->after('volume_mm3');
            $table->decimal('scale_factor', 8, 4)->default(1.0)->after('dimensions_mm');
            $table->string('material_name')->nullable()->after('material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['dimensions_mm', 'scale_factor', 'material_name']);
        });
    }
};

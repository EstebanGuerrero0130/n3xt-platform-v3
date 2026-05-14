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
        Schema::create('inventario_3d', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('datos_json')->nullable();
            $table->timestamp('ultima_modificacion')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_3d');
    }
};

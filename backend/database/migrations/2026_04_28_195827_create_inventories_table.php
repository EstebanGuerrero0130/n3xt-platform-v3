<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('material_id');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            $table->decimal('stock_available', 10, 2); // in grams or ml depending on material category
            $table->decimal('low_stock_threshold', 10, 2)->default(500); // Alert when stock is below this
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};

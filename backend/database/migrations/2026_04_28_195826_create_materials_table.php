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
        Schema::create('materials', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name'); // e.g., PLA, Resina Tough
            $table->string('category'); // e.g., FDM, SLA
            $table->decimal('density', 8, 4)->nullable(); // g/cm3
            $table->decimal('cost_per_kg', 12, 2); // Price per Kg (Filament) or Liter (Resin)
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};

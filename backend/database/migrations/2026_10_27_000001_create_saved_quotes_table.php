<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cotizaciones que el cliente guarda para retomar después (archivos + parámetros). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('recurrent_customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('technology', 10);
            $table->string('material_id')->nullable();
            $table->unsignedSmallInteger('infill')->nullable();
            $table->decimal('layer_height', 4, 2)->nullable();
            $table->json('models');   // [{path, original_filename, qty, scale_factor}]
            $table->json('extras')->nullable(); // [{id, qty}]
            $table->unsignedInteger('estimated_total')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_quotes');
    }
};

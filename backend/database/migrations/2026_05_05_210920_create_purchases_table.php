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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('category')->default('maintenance'); // 'inventory_restock', 'maintenance', 'tools', 'office'
            $table->string('material_id')->nullable(); // String to match materials.id which is a string like "FDM-PLA-123"
            $table->decimal('units', 10, 2)->default(1); // e.g. 5 spools
            $table->decimal('unit_amount', 10, 2)->default(0); // e.g. 1000g
            $table->decimal('qty', 10, 2)->default(1); // Total (units * unit_amount)
            $table->decimal('total_cost', 10, 2);
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->date('purchase_date');
            $table->timestamps();
            
            // Note: Since material_id is a custom string in this system (e.g., FDM-PLA-123), we don't use constrained()
            $table->foreign('material_id')->references('id')->on('materials')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

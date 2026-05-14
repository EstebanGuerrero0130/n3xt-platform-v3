<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->decimal('volume_mm3', 10, 2);
            $table->decimal('estimated_weight_g', 10, 2)->nullable();
            $table->integer('qty');
            $table->string('technology');
            $table->string('material_id');
            $table->integer('infill')->nullable();
            $table->decimal('total_price', 12, 2);
            $table->text('comments')->nullable();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('status')->default('pending'); // pending, printing, completed, shipped
            $table->string('tracking_guide')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

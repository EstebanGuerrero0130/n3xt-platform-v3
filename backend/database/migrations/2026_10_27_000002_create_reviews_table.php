<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reseñas de clientes sobre pedidos terminados; se publican tras aprobarlas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('recurrent_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->json('product_ids')->nullable(); // productos del catálogo del pedido
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('photo_url')->nullable();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

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
            $table->string('customer_company')->nullable()->after('customer_name');
            $table->string('customer_phone_secondary')->nullable()->after('customer_phone');
            $table->string('shipping_address')->nullable()->after('customer_phone_secondary');
            $table->string('shipping_city')->nullable()->after('shipping_address');
            $table->string('shipping_zip')->nullable()->after('shipping_city');
            $table->string('shipping_reference')->nullable()->after('shipping_zip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_company', 'customer_phone_secondary', 'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_reference']);
        });
    }
};

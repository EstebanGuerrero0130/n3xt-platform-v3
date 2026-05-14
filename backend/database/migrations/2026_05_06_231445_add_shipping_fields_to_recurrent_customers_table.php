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
        Schema::table('recurrent_customers', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
            $table->string('address_full')->nullable()->after('location');
            $table->string('city_dept_country')->nullable()->after('address_full');
            $table->string('zip_code')->nullable()->after('city_dept_country');
            $table->string('location_reference')->nullable()->after('zip_code');
            $table->string('phone_secondary')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurrent_customers', function (Blueprint $table) {
            $table->dropColumn(['company', 'address_full', 'city_dept_country', 'zip_code', 'location_reference', 'phone_secondary']);
        });
    }
};

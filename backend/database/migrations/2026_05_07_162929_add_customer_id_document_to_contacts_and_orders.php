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
            $table->string('customer_id_document', 50)->nullable()->after('company');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_id_document', 50)->nullable()->after('customer_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurrent_customers', function (Blueprint $table) {
            $table->dropColumn('customer_id_document');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_id_document');
        });
    }
};

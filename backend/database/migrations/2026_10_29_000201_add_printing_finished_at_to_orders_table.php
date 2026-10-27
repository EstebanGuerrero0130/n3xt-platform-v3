<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Hora en que el pedido salió de impresión (calibración del tiempo estimado). */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('printing_finished_at')->nullable()->after('printing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('printing_finished_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Hora en que el pedido entró a impresión (calendario de producción). */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('printing_started_at')->nullable()->after('printer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('printing_started_at');
        });
    }
};

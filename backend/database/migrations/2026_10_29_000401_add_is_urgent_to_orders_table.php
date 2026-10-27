<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** El cliente pagó el recargo urgente: el pedido va primero en la cola de producción. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('is_paid');
        });

        // Pedidos existentes: el recargo quedó guardado en la copia de costos
        DB::table('orders')->whereNotNull('cost_snapshot')->select(['id', 'cost_snapshot'])
            ->chunkById(200, function ($orders) {
                foreach ($orders as $o) {
                    $snap = is_string($o->cost_snapshot) ? json_decode($o->cost_snapshot, true) : null;
                    if (($snap['quote']['urgent'] ?? false) === true) {
                        DB::table('orders')->where('id', $o->id)->update(['is_urgent' => true]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_urgent');
        });
    }
};

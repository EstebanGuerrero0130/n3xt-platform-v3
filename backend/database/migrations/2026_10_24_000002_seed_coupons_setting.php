<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos: mueve al servidor los cupones que estaban hardcodeados en
 * frontend/src/views/QuoteView.vue (validCoupons). Idempotente: no pisa una
 * clave `coupons` ya existente.
 */
return new class extends Migration
{
    private const COUPONS = [
        ['code' => 'N3XT10', 'discount_pct' => 10, 'label' => 'Cupón Bienvenida', 'active' => true],
        ['code' => 'MAKER5', 'discount_pct' => 5, 'label' => 'Especial Maker', 'active' => true],
        ['code' => 'FDM2026', 'discount_pct' => 15, 'label' => 'Lanzamiento Industrial', 'active' => true],
    ];

    public function up(): void
    {
        if (DB::table('settings')->where('key', 'coupons')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'coupons',
            'value' => json_encode(self::COUPONS, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'coupons')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El gestor de descuentos del admin guardaba en `discounts` ({code, type, value,
 * status, max_uses}) pero el cotizador lee `coupons` ({code, discount_pct, label,
 * active}): ningún descuento creado en el admin funcionaba. Se pasan los de
 * porcentaje a `coupons`; los de monto fijo no existen en el cotizador y se
 * dejan en `discounts` (que ya no es pública).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = $this->read('discounts');
        if (!$legacy) {
            return;
        }

        $coupons = $this->read('coupons');
        $existing = array_map(fn ($c) => strtoupper((string) ($c['code'] ?? '')), $coupons);

        foreach ($legacy as $d) {
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            $pct = (float) ($d['value'] ?? 0);
            if ($code === '' || ($d['type'] ?? 'percentage') !== 'percentage' || $pct <= 0 || $pct > 100 || in_array($code, $existing, true)) {
                continue;
            }
            $coupons[] = [
                'code' => $code,
                'discount_pct' => $pct,
                'label' => '',
                'active' => ($d['status'] ?? 'active') === 'active',
                'max_uses' => isset($d['max_uses']) ? (int) $d['max_uses'] : null,
            ];
            $existing[] = $code;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'coupons'],
            ['value' => json_encode($coupons, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        // Los cupones fusionados quedan en `coupons`; `discounts` no se tocó.
    }

    private function read(string $key): array
    {
        $value = DB::table('settings')->where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
};

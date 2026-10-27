<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /** Claves que solo ve el admin con su sección (códigos de cupón, usos, seguridad). */
    public const PRIVATE_KEYS = ['coupons', 'coupon_uses', 'discounts', 'security'];

    /** Ajustes sin las claves privadas (para copias como la de costos de cada pedido). */
    public static function withoutPrivate(iterable $settings): array
    {
        return array_diff_key(collect($settings)->all(), array_flip(self::PRIVATE_KEYS));
    }
}

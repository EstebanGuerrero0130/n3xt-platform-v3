<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['material_id', 'stock_available', 'low_stock_threshold'];

    protected $casts = [
        'stock_available' => 'float',
        'low_stock_threshold' => 'float',
        'low_stock_alerted_at' => 'datetime',
    ];

    /** Cambió stock u horas: revisar avisos al cerrar la petición */
    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Services\WorkshopAlerts::class)->checkLater());
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}

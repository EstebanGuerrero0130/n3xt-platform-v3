<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'name', 'model', 'technology', 'watts', 'status', 
        'last_maintenance', 'next_maintenance', 'maintenance_notes',
        'total_hours_run', 'maintenance_interval_h',
        'build_x_mm', 'build_y_mm', 'build_z_mm',
    ];

    protected $casts = [
        'maintenance_alerted_at' => 'datetime',
    ];

    /** Cambió stock u horas: revisar avisos al cerrar la petición */
    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Services\WorkshopAlerts::class)->checkLater());
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

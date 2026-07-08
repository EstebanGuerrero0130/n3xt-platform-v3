<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'name', 'model', 'technology', 'watts', 'status', 
        'last_maintenance', 'next_maintenance', 'maintenance_notes',
        'total_hours_run', 'maintenance_interval_h',
        // OrcaEngine v2: perfil técnico para cálculo de shell y velocidad
        'nozzle_mm', 'wall_count', 'max_flow_mm3_hr',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

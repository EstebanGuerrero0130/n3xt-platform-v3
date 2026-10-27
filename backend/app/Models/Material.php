<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'id',
        'name',
        'category',
        'type',
        'unit',
        'cost_per_kg',
        'density',
        'color',
        'location',
        'is_active',
        // Cotizador (null = valor por defecto)
        'speed_factor',
        'waste_pct',
        'failure_pct',
    ];
    
    public $incrementing = false;
    protected $keyType = 'string';

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}

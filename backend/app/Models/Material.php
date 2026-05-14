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
        'is_active'
    ];
    
    public $incrementing = false;
    protected $keyType = 'string';

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}

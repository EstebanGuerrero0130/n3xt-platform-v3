<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['material_id', 'stock_available', 'low_stock_threshold'];

    protected $casts = [
        'stock_available' => 'float',
        'low_stock_threshold' => 'float',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}

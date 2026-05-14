<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'item_name',
        'category',
        'material_id',
        'units',
        'unit_amount',
        'qty',
        'total_cost',
        'supplier',
        'notes',
        'purchase_date'
    ];

    protected $casts = [
        'units' => 'decimal:2',
        'unit_amount' => 'decimal:2',
        'qty' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'purchase_date' => 'date'
    ];

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id', 'id');
    }
}

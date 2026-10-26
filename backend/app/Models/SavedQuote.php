<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedQuote extends Model
{
    protected $fillable = ['customer_id', 'name', 'technology', 'material_id', 'infill', 'layer_height', 'models', 'extras', 'estimated_total'];

    protected $casts = [
        'models' => 'array',
        'extras' => 'array',
        'infill' => 'integer',
        'layer_height' => 'float',
        'estimated_total' => 'integer',
    ];

    /** Lo que ve el cliente: sin rutas internas de almacenamiento. */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'technology' => $this->technology,
            'material_id' => $this->material_id,
            'infill' => $this->infill,
            'layer_height' => $this->layer_height,
            'extras' => $this->extras ?? [],
            'estimated_total' => $this->estimated_total,
            'created_at' => $this->created_at,
            'models' => collect($this->models)->map(fn ($m) => [
                'name' => $m['original_filename'] ?? null,
                'qty' => $m['qty'] ?? 1,
                'scale_factor' => $m['scale_factor'] ?? 1,
            ])->values(),
        ];
    }
}

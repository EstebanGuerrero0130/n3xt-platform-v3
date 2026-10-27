<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'customer_name', 'customer_company', 'customer_id_document', 'customer_email', 'customer_phone',
        'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_reference',
        'volume_mm3', 'estimated_weight_g', 'qty',
        'technology', 'material_id', 'material_name',
        'infill', 'total_price', 'comments',
        'file_path', 'original_filename', 'status',
        'tracking_guide', 'tracking_carrier', 'dimensions_mm', 'scale_factor',
        'printer_id', 'estimated_duration_h',
        'extras_cost', 'extra_items', 'cost_snapshot', 'is_paid',
        'stock_deducted', 'hours_added_to_printer', 'project_id',
        'job_name', 'models', 'printing_started_at'
    ];

    protected $casts = [
        'volume_mm3' => 'float',
        'estimated_weight_g' => 'float',
        'total_price' => 'float',
        'qty' => 'integer',
        'infill' => 'integer',
        'scale_factor' => 'float',
        'estimated_duration_h' => 'float',
        'extras_cost' => 'float',
        'extra_items' => 'array',
        'models' => 'array',
        'is_paid' => 'boolean',
        'stock_deducted' => 'boolean',
        'hours_added_to_printer' => 'boolean',
        'printing_started_at' => 'datetime',
    ];

    /**
     * Copia de costos del pedido. Nunca guarda ni devuelve las claves privadas de
     * ajustes (cupones): el personal sin la sección Descuentos ve los pedidos.
     */
    protected function costSnapshot(): Attribute
    {
        $clean = function ($value) {
            $snap = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($snap) && isset($snap['settings'])) {
                $snap['settings'] = Setting::withoutPrivate($snap['settings']);
            }
            return $snap;
        };

        return Attribute::make(
            get: fn ($value) => $clean($value),
            set: fn ($value) => $value === null ? null : json_encode($clean($value)),
        );
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }
}

<?php

namespace App\Models;

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
        'job_name'
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
        'cost_snapshot' => 'array',
        'is_paid' => 'boolean',
        'stock_deducted' => 'boolean',
        'hours_added_to_printer' => 'boolean'
    ];

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }
}

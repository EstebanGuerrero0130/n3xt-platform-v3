<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialRequest extends FormRequest
{
    /** Parámetros del cotizador (vacío = valor por defecto); también los usa la edición */
    public const QUOTE_PARAM_RULES = [
        'speed_factor' => 'nullable|numeric|min:0.1|max:10',
        'waste_pct' => 'nullable|numeric|min:0|max:100',
        'failure_pct' => 'nullable|numeric|min:0|max:100',
    ];

    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'id' => 'required|string|unique:materials,id|max:50',
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'type' => 'required|in:material,utility,service,product',
            'unit' => 'required|string',
            'cost_per_kg' => 'required|numeric|min:0',
            'density' => 'nullable|numeric|min:0.01',
            'color' => 'nullable|string|max:20',
            'initial_stock' => 'required|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            ...self::QUOTE_PARAM_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'El código del material es obligatorio.',
            'id.unique' => 'Este código de material ya existe.',
            'name.required' => 'El nombre del material es obligatorio.',
            'type.in' => 'El tipo debe ser: material, utility o service.',
            'cost_per_kg.required' => 'El costo por kg es obligatorio.',
            'initial_stock.required' => 'El stock inicial es obligatorio.',
        ];
    }
}

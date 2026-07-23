<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAdmin = auth('sanctum')->check();

        return [
            // Customer fields (reused in orders and contacts)
            'customer_name'             => 'required|string|max:255',
            'customer_company'          => 'nullable|string|max:255',
            'customer_email'            => 'nullable|email|max:255',
            'customer_phone'            => 'nullable|string|max:50',
            'customer_id_document'      => 'nullable|string|max:50',

            // Shipping fields (reused in contacts)
            'shipping_address'          => 'nullable|string|max:255',
            'shipping_city'             => 'nullable|string|max:255',
            'shipping_zip'              => 'nullable|string|max:20',
            'shipping_reference'        => 'nullable|string|max:255',

            // Order-specific fields
            'job_name'                  => 'nullable|string|max:255',
            'extras_cost'               => 'nullable|numeric',
            'comments'                  => 'nullable|string',
            'volume_mm3'                => 'required|numeric',
            'estimated_weight_g'        => 'required|numeric',
            'total_price'               => 'required|numeric',
            'technology'                => 'required|string',
            'material_id'               => 'required|string',
            'material_name'             => 'nullable|string',
            'qty'                       => 'nullable|integer|min:1',
            'infill'                    => 'nullable|integer',
            'dimensions_mm'             => 'nullable|string',
            'scale_factor'              => 'nullable|numeric',
            'estimated_duration_h'      => 'nullable|numeric',
            'file'                      => $isAdmin ? 'nullable|file|max:153600' : 'required|file|max:153600',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
            'volume_mm3.required' => 'El volumen de la pieza es obligatorio.',
            'estimated_weight_g.required' => 'El peso estimado es obligatorio.',
            'total_price.required' => 'El precio total es obligatorio.',
            'material_id.required' => 'Debes seleccionar un material.',
            'file.required' => 'Debes adjuntar un archivo STL, OBJ o 3MF.',
            'file.max' => 'El archivo no debe superar los 150MB.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public const MAX_MODELS = 10;
    public const MAX_FILE_KB = 51200; // 50 MB

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAdmin = EnsureAdmin::check();

        // Pedido público: solo STL/OBJ (el servidor debe poder medir la malla).
        // Admin: mantiene .3mf en el `file` único (pedido manual con precio propio).
        $publicFile = 'file|max:' . self::MAX_FILE_KB . '|extensions:stl,obj';
        $adminFile = 'file|max:' . self::MAX_FILE_KB . '|extensions:stl,obj,3mf';

        // Pedido manual del admin (sin `models`): debe dar las métricas como hasta ahora
        $adminRequiredWithoutFiles = 'required_without:models|nullable|numeric';

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
            // Público: el servidor calcula volumen/peso/precio; lo que mande el cliente es solo informativo
            'volume_mm3'                => $isAdmin ? $adminRequiredWithoutFiles : 'nullable|numeric',
            'estimated_weight_g'        => $isAdmin ? $adminRequiredWithoutFiles : 'nullable|numeric',
            'total_price'               => $isAdmin ? $adminRequiredWithoutFiles : 'nullable|numeric',
            'technology'                => $isAdmin ? 'required|string' : 'required|string|in:FDM,SLA',
            'material_id'               => 'required|string',
            'material_name'             => 'nullable|string',
            'qty'                       => 'nullable|integer|min:1|max:1000',
            'infill'                    => 'nullable|integer|min:0|max:100',
            'layer_height'              => 'nullable|numeric|min:0.05|max:0.4',
            'coupon_code'               => 'nullable|string|max:50',
            'dimensions_mm'             => 'nullable|string|max:100',
            'scale_factor'              => 'nullable|numeric|gt:0|max:100',
            'estimated_duration_h'      => 'nullable|numeric',
            'file'                      => $isAdmin ? 'nullable|' . $adminFile : 'required_without:models|nullable|' . $publicFile,

            // Multi-modelo
            'models'                    => 'nullable|array|min:1|max:' . self::MAX_MODELS,
            'models.*.file'             => 'required|' . $publicFile,
            'models.*.qty'              => 'nullable|integer|min:1|max:1000',
            'models.*.scale_factor'     => 'nullable|numeric|gt:0|max:100',
            'models.*.dimensions_mm'    => 'nullable|string|max:100',
            'models.*.support_area_mm2' => 'nullable|numeric|min:0',
            'models.*.infill'           => 'nullable|integer|min:0|max:100',
            'models.*.layer_height'     => 'nullable|numeric|min:0.05|max:0.4',

            // Extras (insumos)
            'extra_items'               => 'nullable|array|max:30',
            'extra_items.*.material_id' => 'required|string',
            'extra_items.*.qty'         => 'required|numeric|min:0.01|max:100000',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
            'volume_mm3.required_without' => 'El volumen de la pieza es obligatorio.',
            'estimated_weight_g.required_without' => 'El peso estimado es obligatorio.',
            'total_price.required_without' => 'El precio total es obligatorio.',
            'technology.in' => 'La tecnología debe ser FDM o SLA.',
            'material_id.required' => 'Debes seleccionar un material.',
            'file.required_without' => 'Debes adjuntar al menos un archivo STL u OBJ.',
            'file.max' => 'El archivo no debe superar los 50MB.',
            'file.extensions' => 'Formato de archivo no autorizado. Solo se permiten .STL y .OBJ.',
            'models.max' => 'Puedes enviar como máximo ' . self::MAX_MODELS . ' modelos por pedido.',
            'models.*.file.required' => 'Cada modelo debe incluir su archivo.',
            'models.*.file.max' => 'Cada archivo no debe superar los 50MB.',
            'models.*.file.extensions' => 'Formato de archivo no autorizado. Solo se permiten .STL y .OBJ.',
            'models.*.qty.min' => 'La cantidad de cada modelo debe ser al menos 1.',
        ];
    }
}

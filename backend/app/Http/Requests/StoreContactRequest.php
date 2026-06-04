<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * Reglas de validación unificadas para clientes y proveedores.
     * Se validan todos los campos posibles; los no enviados simplemente se ignoran.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'customer_id_document' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'address_full' => 'nullable|string|max:255',
            'city_dept_country' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'location_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del contacto es obligatorio.',
            'email.email' => 'El email ingresado no es válido.',
        ];
    }
}

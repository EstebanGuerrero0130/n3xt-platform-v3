<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:pending,printing,post-processing,completed,shipped,cancelled',
            'tracking_guide' => 'nullable|string|max:255',
            'tracking_carrier' => 'nullable|string|max:255',
            'printer_id' => 'nullable|integer|exists:printers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'Estado no válido.',
            'printer_id.exists' => 'La impresora seleccionada no existe.',
        ];
    }
}

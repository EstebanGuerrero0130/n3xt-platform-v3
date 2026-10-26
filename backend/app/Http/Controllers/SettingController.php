<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Setting;
use App\Http\Middleware\EnsureAdmin;

class SettingController extends Controller
{
    /** Claves que solo ve el admin (p. ej. códigos de cupón). */
    private const PRIVATE_KEYS = ['coupons', 'coupon_uses', 'discounts', 'security'];
    /** Solo los escribe el servidor (el admin guardaría una copia vieja encima). */
    private const SERVER_KEYS = ['coupon_uses'];

    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        if (!EnsureAdmin::check()) {
            $settings = $settings->except(self::PRIVATE_KEYS);
        }
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        try {
            $data = $request->validate([
                'settings' => 'required|array',
                'settings.*' => 'nullable',
            ]);

            foreach ($data['settings'] as $key => $value) {
                if (in_array($key, self::SERVER_KEYS, true)) {
                    continue;
                }
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }

            // Retornamos los ajustes actuales para sincronizar el frontend sin recargar
            $allSettings = Setting::all()->pluck('value', 'key');
            return response()->json([
                'message' => 'Settings updated successfully',
                'success' => true,
                'settings' => $allSettings
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error interno al guardar', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            $oldLogo = Setting::where('key', 'company_logo')->first();
            if ($oldLogo && $oldLogo->value && \Storage::disk('public')->exists($oldLogo->value)) {
                \Storage::disk('public')->delete($oldLogo->value);
            }

            $file = $request->file('logo');
            $path = $file->store('logos', 'public');

            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $path]);

            return response()->json([
                'message' => 'Logo actualizado con éxito',
                'logo_url' => asset('storage/' . $path) . '?t=' . time(),
                'logo_path' => $path
            ]);
        }

        return response()->json(['message' => 'Error al subir el archivo'], 400);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'public_id' => 'nullable|string|max:120|regex:/^[A-Za-z0-9_\-\/]+$/',
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No se envió ningún archivo'], 400);
        }

        $file = $request->file('file');
        // Destino fijo del servidor: el cliente no decide a qué cuenta se sube
        $cloudName = config('services.cloudinary.cloud_name');
        $uploadPreset = config('services.cloudinary.upload_preset');
        $publicId = $request->input('public_id', 'n3xt_upload_' . time());

        // Construir el multipart form data
        $postFields = [
            'file' => curl_file_create($file->getPathname(), $file->getMimeType(), $file->getClientOriginalName()),
            'upload_preset' => $uploadPreset,
            'public_id' => $publicId,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json(['message' => 'Error de conexión con Cloudinary', 'error' => $error], 500);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['secure_url'])) {
            return response()->json([
                'message' => 'Error al subir imagen a Cloudinary',
                'error' => $data['error']['message'] ?? 'Error desconocido',
            ], 500);
        }

        return response()->json([
            'message' => 'Imagen subida correctamente',
            'secure_url' => $data['secure_url'],
            'public_id' => $data['public_id'] ?? $publicId,
        ]);
    }
}

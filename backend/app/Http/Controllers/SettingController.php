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

    /**
     * Logo de la empresa. Con Cloudinary configurado se sube allí (en Vercel el
     * disco es temporal y el logo desaparecía); si no, al disco público local.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);
        $file = $request->file('logo');

        if (config('services.cloudinary.cloud_name')) {
            $url = $this->cloudinaryUpload($file, 'n3xt_logo_' . time());
            if (!$url) {
                return response()->json(['message' => 'No se pudo subir el logo'], 502);
            }
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $url]);

            return response()->json(['message' => 'Logo actualizado con éxito', 'logo_url' => $url, 'logo_path' => $url]);
        }

        $oldLogo = Setting::where('key', 'company_logo')->value('value');
        if (is_string($oldLogo) && !str_starts_with($oldLogo, 'http') && \Storage::disk('public')->exists($oldLogo)) {
            \Storage::disk('public')->delete($oldLogo);
        }
        $path = $file->store('logos', 'public');
        Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $path]);

        return response()->json([
            'message' => 'Logo actualizado con éxito',
            'logo_url' => asset('storage/' . $path) . '?t=' . time(),
            'logo_path' => $path,
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'public_id' => 'nullable|string|max:120|regex:/^[A-Za-z0-9_\-\/]+$/',
        ]);

        // Destino fijo del servidor: el cliente no decide a qué cuenta se sube
        $publicId = $request->input('public_id', 'n3xt_upload_' . time());
        $url = $this->cloudinaryUpload($request->file('file'), $publicId);
        if (!$url) {
            return response()->json(['message' => 'Error al subir imagen a Cloudinary'], 502);
        }

        return response()->json([
            'message' => 'Imagen subida correctamente',
            'secure_url' => $url,
            'public_id' => $publicId,
        ]);
    }

    private function cloudinaryUpload(\Illuminate\Http\UploadedFile $file, string $publicId): ?string
    {
        return app(\App\Services\ImageUploader::class)->upload($file, $publicId);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        file_put_contents(storage_path('logs/emergency.log'), "--- INICIO UPDATE " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);
        try {
            $raw = $request->all();
            file_put_contents(storage_path('logs/emergency.log'), "PAYLOAD: " . json_encode($raw) . "\n", FILE_APPEND);
            
            $data = $request->validate([
                'settings' => 'required|array',
                'settings.*' => 'nullable',
            ]);

            foreach ($data['settings'] as $key => $value) {
                $setting = Setting::updateOrCreate(['key' => $key], ['value' => $value]);
                file_put_contents(storage_path('logs/emergency.log'), "KEY: $key | ID: {$setting->id}\n", FILE_APPEND);
            }

            file_put_contents(storage_path('logs/emergency.log'), "--- FIN EXITOSO ---\n", FILE_APPEND);
            
            // Retornamos los ajustes actuales para sincronizar el frontend sin recargar
            $allSettings = Setting::all()->pluck('value', 'key');
            return response()->json([
                'message' => 'Settings updated successfully', 
                'success' => true,
                'settings' => $allSettings
            ]);
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/emergency.log'), "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            return response()->json(['message' => 'Error interno al guardar', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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
                'logo_url' => asset('storage/' . $path) . '?t=' . time()
            ]);
        }

        return response()->json(['message' => 'Error al subir el archivo'], 400);
    }
}

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sube imágenes a Cloudinary (preset sin firma). Sin Cloudinary configurado
 * guarda en el disco público local (solo sirve en desarrollo).
 */
class ImageUploader
{
    /** URL pública de la imagen, o null si falló. */
    public function upload(UploadedFile $file, string $publicId): ?string
    {
        $cloudName = config('services.cloudinary.cloud_name');
        if (!$cloudName) {
            return asset('storage/' . $file->store('uploads', 'public'));
        }

        $res = Http::timeout(30)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'upload_preset' => config('services.cloudinary.upload_preset'),
                'public_id' => $publicId,
            ]);

        if ($res->failed() || !$res->json('secure_url')) {
            Log::warning('Cloudinary rechazó la subida', ['status' => $res->status(), 'error' => $res->json('error.message')]);
            return null;
        }

        return $res->json('secure_url');
    }
}

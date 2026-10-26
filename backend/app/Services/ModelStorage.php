<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archivos 3D de los pedidos (privados).
 *
 * En Vercel el disco es temporal (/tmp) y los archivos se perdían entre
 * peticiones. Con MODELS_STORAGE=supabase se guardan en un bucket privado de
 * Supabase Storage por su API HTTP (sin el SDK de AWS, que no cabe bien en la
 * función). Sin configurar, se usa el disco local, como en desarrollo.
 */
class ModelStorage
{
    public const DIR = 'orders_models';

    public function usesSupabase(): bool
    {
        return config('services.supabase_storage.driver') === 'supabase'
            && config('services.supabase_storage.url')
            && config('services.supabase_storage.key');
    }

    /** Guarda el archivo y devuelve su ruta relativa (la que va en el pedido). */
    public function put(UploadedFile $file): string
    {
        $path = self::DIR . '/n3xt_' . time() . '_' . Str::random(10) . '.' . strtolower($file->getClientOriginalExtension());

        if (!$this->usesSupabase()) {
            Storage::disk('local')->putFileAs(self::DIR, $file, basename($path));
            return $path;
        }

        $res = Http::withToken(config('services.supabase_storage.key'))
            ->withHeaders(['Content-Type' => 'application/octet-stream', 'x-upsert' => 'false'])
            ->withBody(file_get_contents($file->getRealPath()), 'application/octet-stream')
            ->post($this->objectUrl($path));

        if ($res->failed()) {
            throw new \RuntimeException('No se pudo guardar el archivo en el almacenamiento (' . $res->status() . ').');
        }

        return $path;
    }

    public function exists(?string $path): bool
    {
        if (!$path) {
            return false;
        }
        if (!$this->usesSupabase()) {
            return Storage::disk('local')->exists($path);
        }

        return Http::withToken(config('services.supabase_storage.key'))->head($this->objectUrl($path))->successful();
    }

    /** Respuesta de descarga con el nombre original del archivo. */
    public function download(string $path, string $filename): Response
    {
        if (!$this->usesSupabase()) {
            return response()->download(Storage::disk('local')->path($path), $filename);
        }

        $res = Http::withToken(config('services.supabase_storage.key'))->get($this->objectUrl($path));
        abort_if($res->failed(), 404, 'Archivo no encontrado.');

        return response($res->body(), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
        ]);
    }

    /** Copia un archivo a una ruta nueva (repetir pedido sin compartir archivos). */
    public function copy(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'stl';
        $new = self::DIR . '/n3xt_' . time() . '_' . Str::random(10) . '.' . strtolower($ext);

        if (!$this->usesSupabase()) {
            Storage::disk('local')->copy($path, $new);
            return $new;
        }

        $res = Http::withToken(config('services.supabase_storage.key'))
            ->post(rtrim(config('services.supabase_storage.url'), '/') . '/storage/v1/object/copy', [
                'bucketId' => config('services.supabase_storage.bucket'),
                'sourceKey' => $path,
                'destinationKey' => $new,
            ]);
        if ($res->failed()) {
            throw new \RuntimeException('No se pudo copiar el archivo (' . $res->status() . ').');
        }

        return $new;
    }

    /** Borra uno o varios archivos; un fallo no interrumpe al que llama. */
    public function delete(array|string|null $paths): void
    {
        $paths = array_values(array_unique(array_filter((array) $paths)));
        if (!$paths) {
            return;
        }
        if (!$this->usesSupabase()) {
            Storage::disk('local')->delete($paths);
            return;
        }

        $res = Http::withToken(config('services.supabase_storage.key'))
            ->delete(rtrim(config('services.supabase_storage.url'), '/') . '/storage/v1/object/' . config('services.supabase_storage.bucket'), ['prefixes' => $paths]);
        if ($res->failed()) {
            Log::warning('No se pudieron borrar archivos del almacenamiento', ['paths' => $paths, 'status' => $res->status()]);
        }
    }

    private function objectUrl(string $path): string
    {
        return rtrim(config('services.supabase_storage.url'), '/') . '/storage/v1/object/'
            . config('services.supabase_storage.bucket') . '/' . ltrim($path, '/');
    }
}

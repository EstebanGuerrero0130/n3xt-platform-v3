<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\SavedQuote;
use App\Services\ModelStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Cotizaciones guardadas del cliente: archivos y parámetros para retomarlas en el
 * cotizador sin volver a subir nada. Solo con correo verificado.
 */
class SavedQuoteController extends Controller
{
    use ApiResponse;

    public const MAX_PER_CUSTOMER = 10;

    public function __construct(protected ModelStorage $storage) {}

    public function index(Request $request)
    {
        return $this->success(
            SavedQuote::where('customer_id', $request->user()->id)->latest()->get()->map->toPublic()
        );
    }

    public function store(Request $request)
    {
        $customer = $request->user();
        if (!$customer->isVerified()) {
            return $this->error('Verifica tu correo para guardar cotizaciones.', 403);
        }
        if (SavedQuote::where('customer_id', $customer->id)->count() >= self::MAX_PER_CUSTOMER) {
            return $this->error('Puedes guardar hasta ' . self::MAX_PER_CUSTOMER . ' cotizaciones. Borra alguna para guardar esta.', 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'technology' => 'required|in:FDM,SLA',
            'material_id' => 'nullable|string|max:255',
            'infill' => 'nullable|integer|min:5|max:100',
            'layer_height' => 'nullable|numeric|min:0.01|max:1',
            'estimated_total' => 'nullable|integer|min:0',
            'models' => 'required|array|min:1|max:10',
            'models.*.file' => 'required|file|max:' . StoreOrderRequest::MAX_FILE_KB . '|extensions:stl,obj',
            'models.*.qty' => 'nullable|integer|min:1|max:999',
            'models.*.scale_factor' => 'nullable|numeric|min:0.01|max:100',
            'extra_items' => 'nullable|array|max:30',
            'extra_items.*.material_id' => 'required|string|max:255',
            'extra_items.*.qty' => 'required|numeric|min:0.01',
        ]);

        $stored = [];
        try {
            $models = [];
            foreach ($request->file('models') as $i => $m) {
                $path = $this->storage->put($m['file']);
                $stored[] = $path;
                $models[] = [
                    'path' => $path,
                    'original_filename' => $m['file']->getClientOriginalName(),
                    'qty' => (int) ($data['models'][$i]['qty'] ?? 1),
                    'scale_factor' => (float) ($data['models'][$i]['scale_factor'] ?? 1),
                ];
            }
        } catch (\Throwable $e) {
            $this->storage->delete($stored);
            return $this->error('No se pudieron guardar los archivos. Inténtalo de nuevo.', 500);
        }

        $quote = SavedQuote::create([
            'customer_id' => $customer->id,
            'name' => $data['name'],
            'technology' => $data['technology'],
            'material_id' => $data['material_id'] ?? null,
            'infill' => $data['infill'] ?? null,
            'layer_height' => $data['layer_height'] ?? null,
            'estimated_total' => $data['estimated_total'] ?? null,
            'models' => $models,
            'extras' => collect($data['extra_items'] ?? [])->map(fn ($e) => ['id' => $e['material_id'], 'qty' => (float) $e['qty']])->values()->all(),
        ]);

        return $this->success($quote->toPublic(), 'Cotización guardada.', 201);
    }

    public function file(Request $request, int $id, int $index)
    {
        $quote = SavedQuote::where('customer_id', $request->user()->id)->findOrFail($id);
        $model = $quote->models[$index] ?? null;
        abort_if(!$model || !$this->storage->exists($model['path']), 404);

        return $this->storage->download($model['path'], $model['original_filename'] ?? 'modelo.stl');
    }

    public function destroy(Request $request, int $id)
    {
        $quote = SavedQuote::where('customer_id', $request->user()->id)->findOrFail($id);
        $this->storage->delete(array_column($quote->models ?? [], 'path'));
        $quote->delete();

        return $this->success(null, 'Cotización borrada.');
    }
}

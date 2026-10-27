<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Inventory;
use App\Traits\ApiResponse;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Requests\StoreMaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            return $this->success(Material::with('inventory')->get());
        } catch (\Exception $e) {
            return $this->error('Error al listar inventario', 500);
        }
    }

    public function store(StoreMaterialRequest $request)
    {
        try {
            $validated = $request->validated();
            if (!self::mayChangeQuoteParams(array_filter(array_intersect_key($validated, StoreMaterialRequest::QUOTE_PARAM_RULES), fn ($v) => $v !== null))) {
                return $this->error(self::QUOTE_PARAMS_DENIED, 403);
            }

            return DB::transaction(function () use ($validated) {
                $material = Material::create([
                    'id' => strtoupper($validated['id']),
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'type' => $validated['type'],
                    'unit' => $validated['unit'],
                    'cost_per_kg' => $validated['cost_per_kg'],
                    'color' => $validated['color'] ?? '#cccccc',
                    'density' => $validated['density'] ?? ($validated['category'] === 'FDM' ? 1.24 : 1.10),
                    'is_active' => true,
                    'speed_factor' => $validated['speed_factor'] ?? null,
                    'waste_pct' => $validated['waste_pct'] ?? null,
                    'failure_pct' => $validated['failure_pct'] ?? null,
                ]);

                Inventory::create([
                    'material_id' => $material->id,
                    'stock_available' => $validated['initial_stock'],
                    'low_stock_threshold' => $validated['low_stock_threshold'] ?? 500
                ]);

                return $this->success($material->load('inventory'), 'Material creado con éxito.', 201);
            });
        } catch (\Exception $e) {
            return $this->error('Error al registrar material: ' . $e->getMessage(), 422);
        }
    }

    public function updateStock(Request $request, $id)
    {
        try {
            $request->validate([
                'stock_available' => 'required|numeric|min:0'
            ]);

            $material = Material::findOrFail($id);
            $inventory = $material->inventory;
            
            if ($inventory) {
                $inventory->stock_available = $request->stock_available;
                $inventory->save();
            }

            return $this->success($material->load('inventory'));
        } catch (\Exception $e) {
            return $this->error('Error al actualizar stock', 422);
        }
    }

    private const QUOTE_PARAMS_DENIED = 'Los parámetros de cotización del material los cambia quien tiene acceso a Ajustes.';

    /** Velocidad, desperdicio y fallos cambian el precio de cada cotización: van con Ajustes, no con Inventario. */
    private static function mayChangeQuoteParams(array $changed): bool
    {
        return !$changed || EnsureAdmin::can('settings');
    }

    public function update(Request $request, $id)
    {
        try {
            $material = Material::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'cost_per_kg' => 'sometimes|numeric|min:0',
                'color' => 'sometimes|nullable|string|max:20',
                'low_stock_threshold' => 'sometimes|numeric|min:0',
                ...array_map(fn ($r) => 'sometimes|' . $r, StoreMaterialRequest::QUOTE_PARAM_RULES),
            ]);

            // Solo cuenta lo que cambia: el modal de edición manda todos los campos
            $changed = array_filter(
                array_intersect_key($validated, StoreMaterialRequest::QUOTE_PARAM_RULES),
                fn ($v, $k) => $v != $material->{$k} || ($v === null) !== ($material->{$k} === null),
                ARRAY_FILTER_USE_BOTH,
            );
            if (!self::mayChangeQuoteParams($changed)) {
                return $this->error(self::QUOTE_PARAMS_DENIED, 403);
            }

            $material->name = $request->input('name', $material->name);
            $material->cost_per_kg = $request->input('cost_per_kg', $material->cost_per_kg);
            $material->color = $request->input('color', $material->color);
            // Parámetros del cotizador: vacío vuelve al valor por defecto
            foreach (array_keys(StoreMaterialRequest::QUOTE_PARAM_RULES) as $key) {
                if (array_key_exists($key, $validated)) {
                    $material->{$key} = $validated[$key];
                }
            }
            $material->save();

            if (isset($validated['low_stock_threshold'])) {
                $inventory = $material->inventory;
                if ($inventory) {
                    $inventory->low_stock_threshold = $validated['low_stock_threshold'];
                    $inventory->save();
                }
            }
            return $this->success($material->load('inventory'));
        } catch (\Exception $e) {
            return $this->error('Error al actualizar material: ' . $e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $material = Material::findOrFail($id);
            $material->delete();
            return $this->success(null, 'Material e inventario eliminados exitosamente');
        } catch (\Exception $e) {
            return $this->error('Error al eliminar material', 500);
        }
    }
}

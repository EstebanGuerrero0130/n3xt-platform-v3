<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Inventory;
use App\Traits\ApiResponse;
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

            return DB::transaction(function () use ($validated) {
                $material = Material::create([
                    'id' => strtoupper($validated['id']),
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'type' => $validated['type'],
                    'unit' => $validated['unit'] ?? 'unid',
                    'cost_per_kg' => $validated['cost_per_kg'],
                    'color' => $validated['color'] ?? '#cccccc',
                    'density' => $validated['density'] ?? ($validated['category'] === 'FDM' ? 1.24 : 1.10),
                    'is_active' => true
                ]);

                Inventory::create([
                    'material_id' => $material->id,
                    'stock_available' => $validated['initial_stock'] ?? 0,
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

    public function update(Request $request, $id)
    {
        try {
            $material = Material::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'cost_per_kg' => 'sometimes|numeric|min:0',
                'color' => 'sometimes|nullable|string|max:20',
                'low_stock_threshold' => 'sometimes|numeric|min:0'
            ]);

            $material->name = $request->input('name', $material->name);
            $material->cost_per_kg = $request->input('cost_per_kg', $material->cost_per_kg);
            $material->color = $request->input('color', $material->color);
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

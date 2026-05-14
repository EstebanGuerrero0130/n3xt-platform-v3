<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index()
    {
        try {
            return Material::with('inventory')->get();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar inventario'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|unique:materials,id|max:50',
                'name' => 'required|string|max:255',
                'category' => 'required|string',
                'type' => 'required|in:material,utility,service',
                'unit' => 'required|string',
                'cost_per_kg' => 'required|numeric|min:0',
                'color' => 'nullable|string|max:20',
                'initial_stock' => 'required|numeric|min:0',
                'low_stock_threshold' => 'nullable|numeric|min:0'
            ]);

            return \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
                $material = Material::create([
                    'id' => strtoupper($validated['id']),
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'type' => $validated['type'],
                    'unit' => $validated['unit'],
                    'cost_per_kg' => $validated['cost_per_kg'],
                    'color' => $validated['color'] ?? '#cccccc',
                    'density' => $validated['density'] ?? ($validated['category'] === 'FDM' ? 1.24 : 1.10),
                    'is_active' => true
                ]);

                \App\Models\Inventory::create([
                    'material_id' => $material->id,
                    'stock_available' => $validated['initial_stock'],
                    'low_stock_threshold' => $validated['low_stock_threshold'] ?? 500
                ]);

                return response()->json($material->load('inventory'), 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar material: ' . $e->getMessage()], 422);
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

            return response()->json($material->load('inventory'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar stock'], 422);
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
            return response()->json($material->load('inventory'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar material: ' . $e->getMessage()], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $material = Material::findOrFail($id);
            // El inventario debería borrarse por cascada si está configurado en la migración,
            // si no, se puede borrar aquí manualmente.
            $material->delete();
            return response()->json(['message' => 'Material e inventario eliminados exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar material'], 500);
        }
    }
}

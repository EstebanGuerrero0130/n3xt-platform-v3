<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Inventory;
use App\Models\Material;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $purchases = Purchase::with('material')->orderBy('purchase_date', 'desc')->get();
        return $this->success($purchases);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|string',
            'material_id' => 'nullable|string', 
            'units' => 'required|numeric|min:0.01',
            'unit_amount' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'purchase_date' => 'required|date',
            'is_new_item' => 'boolean',
            'material_data' => 'nullable|array'
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                // El total a ingresar al inventario es Unidades * Gramos/ML por unidad
                $finalQty = $validated['units'] * $validated['unit_amount'];
                
                $materialId = $validated['material_id'];

                // Si es un item nuevo, lo creamos primero
                if ($request->boolean('is_new_item') && !empty($request->material_data)) {
                    $mD = $request->material_data;
                    
                    // Calcular el costo unitario técnico para el simulador
                    $calculatedCostPerKg = 0;
                    if ($finalQty > 0) {
                        $unit = $mD['unit'] ?? 'g';
                        // Si es gramos o ml, llevamos el costo a Kilo/Litro (* 1000)
                        // Si es unidades, el costo es por pieza (directo)
                        if ($unit === 'g' || $unit === 'ml') {
                            $calculatedCostPerKg = ($validated['total_cost'] / $finalQty) * 1000;
                        } else {
                            $calculatedCostPerKg = ($validated['total_cost'] / $finalQty);
                        }
                    }

                    $material = Material::create([
                        'id' => $mD['id'],
                        'name' => $mD['name'],
                        'category' => $mD['category'],
                        'type' => $mD['type'] ?? 'material',
                        'unit' => $mD['unit'] ?? 'g',
                        'cost_per_kg' => $calculatedCostPerKg, 
                        'color' => $mD['color'] ?? '#cccccc'
                    ]);
                    $materialId = $material->id;
                } else if ($validated['category'] === 'inventory_restock' && $materialId) {
                    // Si ya existe el material, actualizamos su costo promedio
                    $material = Material::find($materialId);
                    if ($material && $finalQty > 0) {
                        if ($material->unit === 'g' || $material->unit === 'ml') {
                            $material->cost_per_kg = ($validated['total_cost'] / $finalQty) * 1000;
                        } else {
                            $material->cost_per_kg = ($validated['total_cost'] / $finalQty);
                        }
                        $material->save();
                    }
                }

                $purchaseData = [
                    'item_name' => $validated['item_name'],
                    'category' => $validated['category'],
                    'material_id' => $materialId,
                    'units' => $validated['units'],
                    'unit_amount' => $validated['unit_amount'],
                    'qty' => $finalQty,
                    'total_cost' => $validated['total_cost'],
                    'supplier' => $validated['supplier'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'purchase_date' => $validated['purchase_date']
                ];
                
                $purchase = Purchase::create($purchaseData);

                // Lógica de Doble Vía (Dual-Path Logic)
                if ($validated['category'] === 'inventory_restock' && !empty($materialId)) {
                    $inventory = Inventory::query()->where(['material_id' => $materialId])->first();
                    if ($inventory) {
                        $inventory->stock_available += $finalQty;
                        $inventory->save();
                    } else {
                        Inventory::create([
                            'material_id' => $materialId,
                            'stock_available' => $finalQty
                        ]);
                    }
                }

                return $this->success($purchase, 'Compra registrada exitosamente.', 201);
            });
        } catch (\Exception $e) {
            return $this->error('Error al registrar compra: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $purchase = Purchase::findOrFail($id);

                // Revert stock and recalculate cost if it was an inventory restock
                if ($purchase->category === 'inventory_restock' && $purchase->material_id) {
                    $inventory = Inventory::query()->where(['material_id' => $purchase->material_id])->first();
                    if ($inventory) {
                        $inventory->stock_available -= $purchase->qty;
                        if ($inventory->stock_available < 0) $inventory->stock_available = 0;
                        $inventory->save();
                    }

                    // Recalcular el costo unitario basándose en la compra anterior más reciente
                    $purchase->delete(); // Borramos primero para que no salga en la búsqueda

                    $lastPurchase = Purchase::query()->where(['material_id' => $purchase->material_id])
                        ->where(['category' => 'inventory_restock'])
                        ->orderBy('purchase_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastPurchase) {
                        $material = Material::find($purchase->material_id);
                        if ($material && $lastPurchase->qty > 0) {
                            if ($material->unit === 'g' || $material->unit === 'ml') {
                                $material->cost_per_kg = ($lastPurchase->total_cost / $lastPurchase->qty) * 1000;
                            } else {
                                $material->cost_per_kg = ($lastPurchase->total_cost / $lastPurchase->qty);
                            }
                            $material->save();
                        }
                    }
                    
                    return $this->success(null, 'Registro de compra eliminado y costos recalculados.');
                }

                $purchase->delete();
                return $this->success(null, 'Registro de compra eliminado.');
            });
        } catch (\Exception $e) {
            return $this->error('Error al eliminar compra: ' . $e->getMessage(), 500);
        }
    }
}

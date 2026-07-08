<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\Printer;
use App\Services\CostCalculatorService;
use App\Traits\ApiResponse;
use App\Models\Setting;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CostCalculatorService $costCalculator
    ) {}

    /**
     * Process STL with CuraEngine (Industrial Slicing API)
     */
    public function processStl(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'infill' => 'nullable|integer',
            'layer_height' => 'nullable|numeric',
        ]);

        $file = $request->file('file');
        $tempPath = $file->storeAs('temp_slicing', 'slice_' . time() . '_' . Str::random(5) . '.stl', 'local');
        $fullPath = Storage::disk('local')->path($tempPath);
        
        $infill = (int)$request->input('infill', 20);
        $layerHeight = (float)$request->input('layer_height', 0.2);

        // --- PROTOCOLO N3XT: ORCAENGINE BRIDGE v2 ---
        // En un entorno con CuraEngine/OrcaSlicer CLI instalado, aquí se ejecutaría el binario.
        // Mientras tanto, usamos el Simulador de Alta Fidelidad OrcaEngine v2.
        
        $analysis = $this->simulateCuraAnalysis($fullPath, $infill, $layerHeight);
        
        Storage::disk('local')->delete($tempPath);

        return $this->success($analysis, 'Análisis OrcaEngine v2 (N3XT Industrial) completado.');
    }

    /**
     * Store a new order (Public or Admin)
     */
    public function store(StoreOrderRequest $request)
    {
        $isAdmin = auth('sanctum')->check();

        // 1. Bot Protection (Turnstile)
        if (!$isAdmin) {
            if (!$this->verifyTurnstile($request)) {
                return $this->error('Validación anti-bot fallida.', 403);
            }
        }

        // 2. Validation (using StoreOrderRequest)
        $validated = $request->validated();

        // 3. File Handling
        $path = null;
        $originalFilename = 'Manual Order';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            if (!in_array($extension, ['stl', 'obj', '3mf'])) {
                return $this->error('Protocolo de Seguridad: Formato de archivo no autorizado. Solo se permiten .STL, .OBJ y .3MF.', 422);
            }

            $path = $file->storeAs('orders_models', 'n3xt_' . time() . '_' . Str::random(10) . '.' . $extension, 'local');
            $originalFilename = $file->getClientOriginalName();
        }

        // 4. Persistence with Extras Handling
        return DB::transaction(function () use ($validated, $path, $originalFilename, $request) {
            $extrasCost = 0;
            $processedExtras = [];

            if ($request->has('extra_items') && is_array($request->extra_items)) {
                foreach ($request->extra_items as $extra) {
                    $material = Material::findOrFail($extra['material_id']);
                    $inventory = Inventory::where('material_id', $material->id)->first();

                    if ($inventory && $inventory->stock_available >= $extra['qty']) {
                        $inventory->stock_available -= $extra['qty'];
                        $inventory->save();

                        $cost = $this->costCalculator->calculateExtraCost(
                            (float)$material->cost_per_kg,
                            $material->unit,
                            (float)$extra['qty']
                        );

                        $processedExtras[] = [
                            'material_id' => $material->id,
                            'name' => $material->name,
                            'qty' => $extra['qty'],
                            'cost' => $cost,
                            'added_at' => now()->toIso8601String()
                        ];
                        $extrasCost += $cost;
                    }
                }
            }

            // Capture Cost Snapshot for Historical Integrity + OrcaEngine Traceability (#10)
            $settings = Setting::all()->pluck('value', 'key');
            $material = Material::find($validated['material_id']);
            $costSnapshot = [
                'settings'             => $settings,
                'material_cost_per_kg' => $material ? $material->cost_per_kg : 0,
                'captured_at'          => now()->toIso8601String(),
                // Factores del OrcaEngine para auditoría y recalculo exacto
                'orca_factors'         => [
                    'infill'          => $validated['infill'] ?? null,
                    'layer_height'    => $request->input('layer_height'),
                    'technology'      => $validated['technology'] ?? null,
                    'nozzle_mm'       => $request->input('nozzle_diameter', 0.4),
                    'wall_count'      => $request->input('wall_count', 2),
                    'algorithm'       => 'OrcaEngine-v2',
                ],
            ];

            $order = Order::create(array_merge($validated, [
                'material_name'     => $validated['material_name'] ?? $validated['material_id'],
                'qty'               => $validated['qty'] ?? 1,
                'file_path'         => $path,
                'original_filename' => $originalFilename,
                'status'            => 'pending',
                'extra_items'       => count($processedExtras) > 0 ? $processedExtras : null,
                'extras_cost'       => $extrasCost,
                'cost_snapshot'     => $costSnapshot
            ]));

            return $this->success(['order_id' => $order->id], 'Orden creada con éxito.', 201);
        });
    }

    /**
     * List all orders (Admin only)
     */
    public function index()
    {
        return $this->success(Order::with('printer')->orderBy('created_at', 'desc')->get());
    }

    /**
     * Update order status and trigger inventory deduction
     */
    public function updateStatus(UpdateStatusRequest $request, $id)
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated, $id) {
                $order = Order::findOrFail($id);
                $oldStatus = $order->status;
                $oldPrinterId = $order->printer_id;
                
                $order->fill($validated)->save();

                // Logic: Handle Printer Status
                if ($order->status === 'printing') {
                    // Update current printer status to printing
                    if ($order->printer_id) {
                        Printer::where('id', $order->printer_id)->update(['status' => 'printing']);
                    }
                } else if ($oldStatus === 'printing' && $order->status !== 'printing') {
                    // Order moved away from printing -> Release the printer
                    $printerId = $oldPrinterId ?? $order->printer_id;
                    if ($printerId) {
                        $printer = Printer::find($printerId);
                        if ($printer) {
                            $printer->status = 'idle';
                            
                            // Track hours (ONLY ONCE per order to avoid double counting if moved back and forth)
                            if (!$order->hours_added_to_printer) {
                                $printer->total_hours_run += (float)($order->estimated_duration_h ?? 0);
                                $order->hours_added_to_printer = true;
                                $order->save();
                            }
                            
                            $printer->save();
                        }
                    }
                }

                // Removed inventory deduction from here, now happens on togglePaid

                return $this->success($order, 'Estado actualizado con éxito.');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Toggle payment status
     */
    public function togglePaid($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $order = Order::findOrFail($id);

                if ($order->is_paid) {
                    return $this->error('El pago ya ha sido confirmado y no puede revertirse por seguridad del inventario.', 422);
                }

                $order->is_paid = true;

                // Inventory logic: Deduct stock when marked as paid if not already deducted
                if (!$order->stock_deducted) {
                    $this->deductInventory($order);
                    $order->stock_deducted = true;
                }
                
                $order->save();
                
                return $this->success($order, 'Estado de pago actualizado y stock procesado.');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Add an extra item (Utility) to the order
     */
    public function addExtra(Request $request, $id)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'qty' => 'required|numeric|min:0.01'
        ]);

        try {
            return DB::transaction(function () use ($validated, $id) {
                $order = Order::findOrFail($id);
                $material = Material::findOrFail($validated['material_id']);
                $inventory = Inventory::where('material_id', $material->id)->first();

                if (!$inventory) {
                    throw new \Exception('Inventario no encontrado para este material.');
                }

                $cost = $this->costCalculator->calculateExtraCost(
                    (float)$material->cost_per_kg,
                    $material->unit,
                    (float)$validated['qty']
                );

                $extraItems = $order->extra_items ?? [];
                $extraItems[] = [
                    'material_id' => $material->id,
                    'name' => $material->name,
                    'qty' => $validated['qty'],
                    'unit' => $material->unit,
                    'cost' => $cost
                ];

                $order->extra_items = $extraItems;
                $order->extras_cost = ($order->extras_cost ?? 0) + $cost;
                $order->save();

                return $this->success($order, 'Insumo adicional vinculado al pedido.');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Download order 3D model
     */
    public function download($id)
    {
        $order = Order::findOrFail($id);
        
        if (!$order->file_path || !Storage::disk('local')->exists($order->file_path)) {
            return $this->error('Archivo no encontrado físicamente.', 404);
        }

        return response()->download(Storage::disk('local')->path($order->file_path), $order->original_filename);
    }

    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            // Delete file if exists
            if ($order->file_path) {
                Storage::disk('local')->delete($order->file_path);
            }
            $order->delete();
            return $this->success(null, 'Pedido eliminado exitosamente.');
        } catch (\Exception $e) {
            return $this->error('Error al eliminar pedido: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Track an order status (Public)
     */
    public function track(Request $request)
    {
        $request->validate([
            'order_id'       => 'nullable|integer',
            'email'          => 'nullable|email',
            'tracking_guide' => 'nullable|string|max:255',
            'carrier'        => 'nullable|string|max:255'
        ]);

        $query = Order::query();

        if ($request->filled('order_id') && $request->filled('email')) {
            $query->where('id', $request->order_id)
                  ->where('customer_email', $request->email);
        } elseif ($request->filled('tracking_guide')) {
            $query->where('tracking_guide', $request->tracking_guide);
            if ($request->filled('carrier')) {
                $query->where('tracking_carrier', 'LIKE', '%' . $request->carrier . '%');
            }
        } else {
            return $this->error('Se requiere (ID de Proyecto + Email) o (Número de Guía) para el rastreo.', 422);
        }

        $order = $query->first();

        if (!$order) {
            return $this->error('Pedido no encontrado con esos datos.', 404);
        }

        return $this->success([
            'id'             => $order->id,
            'status'         => $order->status,
            'customer_name'  => $order->customer_name,
            'technology'     => $order->technology,
            'material'       => $order->material_name ?? $order->material_id,
            'created_at'     => $order->created_at,
            'tracking_guide' => $order->tracking_guide,
            'tracking_carrier' => $order->tracking_carrier,
            'is_paid'        => $order->is_paid
        ]);
    }

    /**
     * RESET SYSTEM: Purge all orders (CAUTION - Requires confirmation)
     */
    public function purgeAll(Request $request)
    {
        // ---- SEGURIDAD N3XT: Confirmación Requerida ----
        $confirmation = $request->input('confirm_purge');
        if ($confirmation !== 'PURGE_ALL_CONFIRMED') {
            return $this->error('Acción denegada. Debes enviar confirm_purge = "PURGE_ALL_CONFIRMED" para ejecutar esta operación destructiva.', 403);
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::table('orders')->truncate();
            DB::table('purchases')->truncate();
            
            // Delete contacts (customers and suppliers)
            DB::table('recurrent_customers')->truncate();
            DB::table('recurrent_suppliers')->truncate();
            
            // Delete all materials and their linked inventories
            DB::table('inventories')->truncate();
            DB::table('materials')->truncate();
            
            Schema::enableForeignKeyConstraints();
            
            return $this->success(null, 'Sistema purgado TOTALMENTE: Pedidos, Compras, Contactos y Catálogo de Materiales eliminados.');
        } catch (\Exception $e) {
            return $this->error('Error al limpiar el sistema: ' . $e->getMessage(), 500);
        }
    }

    // --- Private Helpers ---

    private function verifyTurnstile(Request $request)
    {
        $token = $request->input('cf_turnstile_response');
        if (!$token) return false;

        $secret = config('services.turnstile.secret');
        
        // Si no hay secret configurado en producción, la validación falla
        if (!$secret) {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $request->ip()
        ]);

        return $response->json('success');
    }

    private function deductInventory(Order $order)
    {
        // 1. Deduct Main Material
        $inventory = Inventory::where('material_id', $order->material_id)->first();
        if ($inventory) {
            $requiredStock = $order->estimated_weight_g * $order->qty;
            $inventory->stock_available -= $requiredStock;
            $inventory->save();
        }

        // 2. Deduct Extra Items (Utilities)
        $extras = $order->extra_items;
        if (is_string($extras)) $extras = json_decode($extras, true);
        
        if (is_array($extras)) {
            foreach ($extras as $item) {
                $extraInv = Inventory::where('material_id', $item['material_id'])->first();
                if ($extraInv) {
                    $extraInv->stock_available -= (float)$item['qty'];
                    $extraInv->save();
                }
            }
        }
    }

    /**
     * ORCAENGINE v2 — N3XT 3D Industrial Slicer
     *
     * Mejoras v2:
     *  - Shell dinámico por nozzle_diameter × wall_count (FDM) o espesor de recubrimiento (SLA)
     *  - Perfiles de velocidad calibrados por layer_height (draft / normal / quality / ultra)
     *  - Lógica SLA independiente: tiempo por exposición + lift por capa
     *  - Purga condicional: solo FDM
     *  - Payload enriquecido con metadatos de algoritmo para cost_snapshot trazable
     */
    private function simulateCuraAnalysis($path, $infill, $layerHeight)
    {
        $request = request();
        $totalArea   = (float)$request->input('total_area', 0);
        $volumeMm3   = (float)$request->input('volume_mm3', 0);
        $supportArea = (float)$request->input('support_area', 0);
        $density     = (float)$request->input('density', 1.24);  // PLA default
        $technology  = strtoupper((string)$request->input('technology', 'FDM'));

        // --- GUARDIA N3XT: Evitar Division by Zero ---
        if ($density <= 0)     $density     = 1.24;
        if ($layerHeight <= 0) $layerHeight = 0.2;

        // ── FDM PATH ──────────────────────────────────────────────────────────
        if ($technology === 'FDM') {

            // 1. SHELL DINÁMICO — nozzle diameter × number of walls
            $nozzleDiameter = (float)$request->input('nozzle_diameter', 0.4);
            $wallCount      = (int)$request->input('wall_count', 2);
            if ($nozzleDiameter <= 0) $nozzleDiameter = 0.4;
            if ($wallCount <= 0)      $wallCount = 2;
            $shellThickness = $nozzleDiameter * $wallCount; // e.g. 0.4×2 = 0.8mm

            $shellVolume = $totalArea * $shellThickness;
            if ($shellVolume > $volumeMm3) $shellVolume = $volumeMm3 * 0.45;
            $shellWeight = ($shellVolume / 1000) * $density;

            // 2. RELLENO (INFILL)
            $internalVolume    = max(0, $volumeMm3 - $shellVolume);
            $infillDensityFactor = ($infill / 100);
            $internalWeight    = ($internalVolume / 1000) * $infillDensityFactor * $density;

            // 3. SOPORTES (densidad reducida Bambu Slim ~35%)
            $supportVolume  = $supportArea * 0.35;
            $supportWeight  = ($supportVolume / 1000) * $density;

            // 4. PURGA + BRIM (solo FDM)
            $purgeWeight = 3.0;
            $estimatedWeight = $shellWeight + $internalWeight + $supportWeight + $purgeWeight;

            // 5. LONGITUD DE FILAMENTO
            $filamentRadius  = 1.75 / 2;
            $filamentAreaMm2 = M_PI * ($filamentRadius ** 2); // ≈ 2.405 mm²
            $totalVolumePlastic = ($estimatedWeight / $density) * 1000;
            $filamentLengthM    = $totalVolumePlastic / $filamentAreaMm2 / 1000;

            // 6. PERFILES DE VELOCIDAD calibrados por layer_height
            //    (flujo volumétrico efectivo en mm³/h)
            if ($layerHeight >= 0.28) {
                $volumetricFlowHr = 18000; // Borrador rápido
                $profileName      = 'Draft';
            } elseif ($layerHeight >= 0.18) {
                $volumetricFlowHr = 15500; // Normal Bambu A1
                $profileName      = 'Normal';
            } elseif ($layerHeight >= 0.08) {
                $volumetricFlowHr = 8000;  // Calidad — aceleración limitada
                $profileName      = 'Quality';
            } else {
                $volumetricFlowHr = 4000;  // Ultra — microresinas/SLA híbrido
                $profileName      = 'Ultra';
            }

            $resolutionFactor = 0.2 / $layerHeight; // factor relativo a capa base
            $prepTimeH  = 0.233; // 14 min (calibración + brim)
            $printTimeH = ($totalVolumePlastic / $volumetricFlowHr) * $resolutionFactor;

            return [
                'engine'  => 'OrcaEngine v2 — N3XT Industrial (FDM)',
                'status'  => 'success',
                'factors' => [
                    'resolution'        => round($resolutionFactor, 4),
                    'infill_density'    => $infillDensityFactor,
                    'shell_weight_g'    => round((float)$shellWeight, 4),
                    'internal_weight_g' => round((float)$internalWeight, 4),
                    'support_weight_g'  => round((float)$supportWeight, 4),
                    'purge_weight_g'    => $purgeWeight,
                    'filament_length_m' => round((float)$filamentLengthM, 4),
                    'prep_time_h'       => (float)$prepTimeH,
                    'print_time_h'      => round((float)$printTimeH, 4),
                    'nozzle_mm'         => $nozzleDiameter,
                    'wall_count'        => $wallCount,
                    'shell_thickness_mm'=> $shellThickness,
                    'speed_profile'     => $profileName,
                    'flow_mm3_hr'       => $volumetricFlowHr,
                    'algorithm'         => 'OrcaEngine-FDM-v2 (Bambu-Sync)',
                ],
            ];
        }

        // ── SLA / DLP PATH ────────────────────────────────────────────────────
        // Para resina: el peso se basa directamente en volumen × densidad
        // El tiempo se calcula por capas: (exposición + lift) × número de capas
        $heightMm   = (float)$request->input('height_mm', 0);
        if ($heightMm <= 0) {
            // Fallback: estimar altura desde volumen (cubo equivalente)
            $heightMm = pow($volumeMm3, 1/3);
        }
        $layerCount = ($heightMm > 0 && $layerHeight > 0)
            ? ceil($heightMm / $layerHeight)
            : 0;

        // Tiempo de exposición por capa (configurable en el futuro via settings)
        $exposureTimeS = 3.0;  // segundos de exposición UV por capa
        $liftTimeS     = 6.5;  // segundos de ascenso/descenso del build plate
        $printTimeH    = ($layerCount * ($exposureTimeS + $liftTimeS)) / 3600;
        $prepTimeH     = 0.167; // 10 min de calentamiento + nivelado

        // Peso: volumen sólido × densidad (resina no tiene infill vacío)
        $resinVolumeCm3 = $volumeMm3 / 1000;
        $resinWeight    = $resinVolumeCm3 * $density;

        // Soporte en resina (~50% densidad, no 35%)
        $supportVolumeCm3 = ($supportArea * 0.30) / 1000;
        $supportWeight    = $supportVolumeCm3 * $density;

        $totalWeight = $resinWeight + $supportWeight;

        return [
            'engine'  => 'OrcaEngine v2 — N3XT Industrial (SLA/DLP)',
            'status'  => 'success',
            'factors' => [
                'resolution'        => round(0.05 / $layerHeight, 4), // relativo a 50µm base
                'infill_density'    => 1.0, // resina siempre sólida
                'shell_weight_g'    => round((float)$resinWeight, 4),
                'internal_weight_g' => 0.0, // no aplica en resina
                'support_weight_g'  => round((float)$supportWeight, 4),
                'purge_weight_g'    => 0.0, // no aplica en SLA
                'filament_length_m' => 0.0, // no aplica en SLA
                'prep_time_h'       => (float)$prepTimeH,
                'print_time_h'      => round((float)$printTimeH, 4),
                'layer_count'       => (int)$layerCount,
                'exposure_time_s'   => $exposureTimeS,
                'lift_time_s'       => $liftTimeS,
                'height_mm'         => $heightMm,
                'algorithm'         => 'OrcaEngine-SLA-v2 (Layer-Exposure)',
            ],
        ];
    }
}

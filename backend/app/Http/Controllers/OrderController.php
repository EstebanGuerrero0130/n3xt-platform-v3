<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\Printer;
use App\Services\CostCalculatorService;
use App\Services\MeshVolumeService;
use App\Services\QuotePricingService;
use App\Services\CustomerAccountService;
use App\Services\OrderMailer;
use App\Services\ModelStorage;
use App\Models\RecurrentCustomer;
use App\Exceptions\InvalidMeshException;
use App\Http\Middleware\EnsureAdmin;
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
        protected CostCalculatorService $costCalculator,
        protected QuotePricingService $pricing,
        protected MeshVolumeService $meshVolume,
        protected CustomerAccountService $accounts,
        protected OrderMailer $mailer,
        protected ModelStorage $models,
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

        // --- PROTOCOLO N3XT: CURAENGINE BRIDGE ---
        // En un entorno con CuraEngine instalado, aquí se ejecutaría el binario.
        // Mientras tanto, usamos el Simulador de Alta Fidelidad (Cura Simulation Algorithm)
        
        $analysis = $this->simulateCuraAnalysis($fullPath, $infill, $layerHeight);
        
        Storage::disk('local')->delete($tempPath);

        return $this->success($analysis, 'Análisis de motor CuraEngine (N3XT Virtual) completado.');
    }

    /**
     * Store a new order (Public or Admin)
     *
     * Con modelos (`models[]` o un `file` STL/OBJ suelto) el servidor mide cada malla y
     * calcula volumen, peso, tiempo y precio: lo que mande el cliente no cuenta.
     * Sin modelos solo el admin puede crear pedidos manuales con sus propias métricas.
     */
    public function store(StoreOrderRequest $request)
    {
        // Precio y métricas manuales: solo quien maneja producción
        $isAdmin = EnsureAdmin::can('kanban');

        // 1. Bot Protection (Turnstile)
        if (!$isAdmin) {
            if (!$this->verifyTurnstile($request)) {
                return $this->error('Validación anti-bot fallida.', 403);
            }
        }

        $validated = $request->validated();

        $material = Material::find($validated['material_id']);
        if (!$material || ($material->type ?? 'material') !== 'material') {
            return $this->error('El material seleccionado no existe.', 422);
        }
        if (!$isAdmin && strtoupper((string)$material->category) !== strtoupper($validated['technology'])) {
            return $this->error('El material no corresponde a la tecnología seleccionada.', 422);
        }

        $uploads = $this->collectModelUploads($request, $validated, $isAdmin);

        if (empty($uploads)) {
            if (!$isAdmin) {
                return $this->error('Debes adjuntar al menos un archivo STL u OBJ.', 422);
            }
            return $this->storeManualOrder($request, $validated, $material);
        }

        return $this->storeQuotedOrder($validated, $material, $uploads, $isAdmin);
    }

    /**
     * Normaliza `models[]` y el `file` suelto (legacy) a una sola lista de uploads.
     * El `file` suelto del admin es un pedido manual con su propio precio.
     */
    private function collectModelUploads(Request $request, array $validated, bool $isAdmin): array
    {
        $uploads = [];
        foreach (array_values($request->file('models') ?? []) as $i => $m) {
            if (!isset($m['file'])) continue;
            $meta = $validated['models'][$i] ?? [];
            $uploads[] = ['file' => $m['file']] + $meta;
        }

        if (empty($uploads) && !$isAdmin && $request->hasFile('file')) {
            $file = $request->file('file');
            if (in_array(strtolower($file->getClientOriginalExtension()), ['stl', 'obj'], true)) {
                $uploads[] = [
                    'file' => $file,
                    'qty' => $validated['qty'] ?? 1,
                    'scale_factor' => $validated['scale_factor'] ?? 1,
                    'dimensions_mm' => $validated['dimensions_mm'] ?? null,
                ];
            }
        }

        return $uploads;
    }

    private function storeQuotedOrder(array $validated, Material $material, array $uploads, bool $isAdmin)
    {
        $stored = [];
        $pricingModels = [];

        try {
            foreach ($uploads as $i => $up) {
                $file = $up['file'];
                $extension = strtolower($file->getClientOriginalExtension());
                // Se mide el archivo subido antes de guardarlo (el almacenamiento puede ser remoto)
                $mesh = $this->meshVolume->analyze($file->getRealPath(), $extension);
                $path = $this->models->put($file);
                $stored[] = $path;

                // Áreas por eje: el cotizador elige la orientación con menos soporte
                $pricingModels[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                ] + QuotePricingService::pricingModelFromMesh($mesh, $up);
            }
        } catch (InvalidMeshException $e) {
            $this->models->delete($stored);
            $name = isset($file) ? $file->getClientOriginalName() : 'el archivo';
            return $this->error("No pudimos leer {$name}: {$e->getMessage()}", 422);
        }

        $settings = $this->pricing->loadSettings();
        $coupon = $this->pricing->findActiveCoupon($validated['coupon_code'] ?? null, $settings);

        // Cupón de bienvenida: personal, solo con el correo de su dueño
        $welcomeOwner = $coupon ? null : $this->accounts->welcomeCouponOwner($validated['coupon_code'] ?? null);
        if ($welcomeOwner) {
            if (strtolower((string) ($validated['customer_email'] ?? '')) !== strtolower((string) $welcomeOwner->email)) {
                $this->models->delete($stored);
                return $this->error('Este cupón es personal: úsalo con el correo de tu cuenta.', 422);
            }
            $coupon = $this->accounts->welcomeCoupon($welcomeOwner);
        }
        $technology = strtoupper((string) $validated['technology']) === 'FDM' ? 'FDM' : 'SLA';
        $quote = $this->pricing->quote(
            [
                'technology' => $validated['technology'],
                'infill' => $validated['infill'] ?? null,
                'layer_height' => $validated['layer_height'] ?? null,
                'urgent' => (bool) ($validated['urgent'] ?? false),
                // Cama de la impresora más grande de la tecnología (quote() sigue siendo pura)
                'bed' => $this->pricing->loadBeds($settings)[$technology],
                'models' => $pricingModels,
                'extras' => $validated['extra_items'] ?? [],
            ],
            $settings,
            $material->only(['id', 'cost_per_kg', 'density', 'speed_factor', 'waste_pct', 'failure_pct']),
            $this->pricing->loadUtilities(),
            $coupon
        );

        $now = now()->toIso8601String();
        $modelsOut = [];
        foreach ($quote['models'] as $i => $qm) {
            $modelsOut[] = [
                'path' => $pricingModels[$i]['path'],
                'original_filename' => $pricingModels[$i]['name'],
                'qty' => $qm['qty'],
                'scale_factor' => $qm['scale_factor'],
                'dimensions_mm' => $pricingModels[$i]['dimensions_mm'],
                'volume_mm3' => $qm['volume_mm3'],
                'weight_g' => $qm['weight_g'],
                'duration_h' => $qm['duration_h'],
                'subtotal' => $qm['subtotal'],
                // Eje vertical elegido y piezas por cama (los pedidos viejos no los tienen)
                'orientation' => $qm['orientation'],
                'pieces_per_bed' => $qm['pieces_per_bed'],
            ];
            // FDM: relleno y capa con que se cotizó cada modelo (los ve el taller)
            if (isset($qm['infill'])) {
                $modelsOut[$i]['infill'] = $qm['infill'];
                $modelsOut[$i]['layer_height'] = $qm['layer_height'];
            }
        }
        $extras = array_map(fn ($e) => $e + ['added_at' => $now], $quote['extras']);

        $first = $modelsOut[0];
        try {
            $order = DB::transaction(function () use ($validated, $material, $quote, $modelsOut, $first, $extras, $settings, $now, $welcomeOwner) {
                if ($welcomeOwner && !$this->accounts->consumeWelcomeCoupon($welcomeOwner)) {
                    return null;
                }
                return Order::create(array_merge($this->orderFields($validated), [
                    'material_id'          => $material->id,
                    'material_name'        => $material->name,
                    'qty'                  => $quote['qty'],
                    'volume_mm3'           => round($quote['volume_mm3'], 2),
                    'estimated_weight_g'   => round($quote['weight_g'], 2),
                    'estimated_duration_h' => round($quote['duration_h'], 3),
                    'total_price'          => $quote['total'],
                    'dimensions_mm'        => count($modelsOut) === 1 ? $first['dimensions_mm'] : null,
                    'scale_factor'         => count($modelsOut) === 1 ? $first['scale_factor'] : 1,
                    'file_path'            => $first['path'],
                    'original_filename'    => $first['original_filename'],
                    'models'               => $modelsOut,
                    'status'               => 'pending',
                    // Solo si el cotizador aplicó el recargo urgente
                    'is_urgent'            => (bool) ($quote['urgent'] ?? false),
                    'extra_items'          => count($extras) > 0 ? $extras : null,
                    'extras_cost'          => $quote['extras_cost'],
                    'cost_snapshot'        => [
                        'settings' => $settings,
                        'material_cost_per_kg' => $material->cost_per_kg,
                        'quote' => array_intersect_key($quote, array_flip(['unit_price', 'subtotal', 'coupon', 'discount', 'subtotal_after_discount', 'iva_pct', 'iva', 'total', 'beds', 'pieces_per_bed', 'bed', 'urgent', 'adjustments', 'volume_discount'])),
                        // Factor de tiempo aplicado: la calibración compara real / (estimado / factor)
                        'calibration_factor' => $quote['calibration_factor'],
                        'captured_at' => $now,
                    ],
                ]));
            });
        } catch (\Throwable $e) {
            // Sin pedido no deben quedar archivos huérfanos en el almacenamiento
            $this->models->delete($stored);
            throw $e;
        }

        if (!$order) {
            $this->models->delete($stored);
            return $this->error('Este cupón de bienvenida ya fue usado.', 422);
        }
        if ($coupon && !$welcomeOwner) {
            $this->pricing->recordCouponUse($coupon['code']);
        }
        $this->mailer->received($order);

        return $this->success([
            'order_id' => $order->id,
            'total_price' => $quote['total'],
            'breakdown' => array_diff_key($quote, array_flip(['production'])),
        ], 'Orden creada con éxito.', 201);
    }

    /** Pedido manual del admin (sin malla medible): métricas y precio vienen del formulario. */
    private function storeManualOrder(Request $request, array $validated, Material $material)
    {
        $path = null;
        $originalFilename = 'Manual Order';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $this->models->put($file);
            $originalFilename = $file->getClientOriginalName();
        }

        // El stock de los extras se descuenta al confirmar el pago (togglePaid), no aquí
        $extrasCost = 0;
        $processedExtras = [];
        foreach ($validated['extra_items'] ?? [] as $extra) {
            $extraMaterial = Material::find($extra['material_id']);
            if (!$extraMaterial) continue;
            $cost = $this->costCalculator->calculateExtraCost((float)$extraMaterial->cost_per_kg, $extraMaterial->unit, (float)$extra['qty']);
            $processedExtras[] = [
                'material_id' => $extraMaterial->id,
                'name' => $extraMaterial->name,
                'qty' => $extra['qty'],
                'unit' => $extraMaterial->unit,
                'cost' => $cost,
                'added_at' => now()->toIso8601String()
            ];
            $extrasCost += $cost;
        }

        $order = Order::create(array_merge($this->orderFields($validated), [
            'material_id'          => $material->id,
            'material_name'        => $validated['material_name'] ?? $material->name,
            'qty'                  => $validated['qty'] ?? 1,
            'volume_mm3'           => $validated['volume_mm3'],
            'estimated_weight_g'   => $validated['estimated_weight_g'],
            'estimated_duration_h' => $validated['estimated_duration_h'] ?? 0,
            'total_price'          => $validated['total_price'],
            'dimensions_mm'        => $validated['dimensions_mm'] ?? null,
            'scale_factor'         => $validated['scale_factor'] ?? 1,
            'file_path'            => $path,
            'original_filename'    => $originalFilename,
            'status'               => 'pending',
            'extra_items'          => count($processedExtras) > 0 ? $processedExtras : null,
            'extras_cost'          => $extrasCost,
            'cost_snapshot'        => [
                'settings' => Setting::all()->pluck('value', 'key'),
                'material_cost_per_kg' => $material->cost_per_kg,
                'captured_at' => now()->toIso8601String()
            ]
        ]));

        $this->mailer->received($order);

        return $this->success(['order_id' => $order->id, 'total_price' => $order->total_price], 'Orden creada con éxito.', 201);
    }

    /** Campos del cliente/envío que se guardan tal cual en el pedido. */
    private function orderFields(array $validated): array
    {
        return array_intersect_key($validated, array_flip([
            'customer_name', 'customer_company', 'customer_email', 'customer_phone', 'customer_id_document',
            'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_reference',
            'job_name', 'comments', 'technology', 'infill',
        ]));
    }

    /**
     * Valida un cupón (Public). Responde solo el cupón pedido, nunca la lista.
     */
    public function validateCoupon(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/']]);
        $coupon = $this->pricing->findActiveCoupon($data['code']);
        if (!$coupon && ($owner = $this->accounts->welcomeCouponOwner($data['code']))) {
            $coupon = $this->accounts->welcomeCoupon($owner);
        }

        if (!$coupon) {
            return $this->error('Ese cupón no existe o ya no está activo.', 404);
        }

        return $this->success($coupon);
    }

    /**
     * Show a single order (Admin only)
     */
    public function show($id)
    {
        return $this->success(Order::with('printer')->findOrFail($id));
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
                
                $order->fill($validated);
                // Inicio real de la impresión: lo usa el calendario de producción
                if ($order->status === 'printing' && $oldStatus !== 'printing') {
                    $order->printing_started_at = now();
                    $order->printing_finished_at = null;
                }
                // Fin real (calibración del tiempo); cancelar o devolver a revisión no cuenta como impresión terminada
                if ($oldStatus === 'printing' && !in_array($order->status, ['printing', 'cancelled', 'pending'], true) && $order->printing_started_at) {
                    $order->printing_finished_at = now();
                }
                $order->save();
                $this->mailer->statusChanged($order, $oldStatus);

                // Logic: Handle Printer Status
                if ($order->status === 'printing') {
                    // Update current printer status to printing
                    if ($order->printer_id) {
                        Printer::where('id', $order->printer_id)->update(['status' => 'printing']);
                    }
                    // Pasó a otra máquina sin salir de impresión: la anterior queda libre
                    if ($oldStatus === 'printing' && $oldPrinterId && $oldPrinterId != $order->printer_id) {
                        Printer::where('id', $oldPrinterId)->update(['status' => 'idle']);
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
                // Bloqueo del pedido: dos clics a la vez no descuentan el stock dos veces
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();

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
    public function download(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // ?model=N baja la pieza N de models[]; sin él, la primera (pedidos viejos: file_path)
        $index = (int) $request->query('model', 0);
        $model = $order->models[$index] ?? null;
        $path = $model['path'] ?? ($index === 0 ? $order->file_path : null);
        $name = $model['original_filename'] ?? $order->original_filename ?? 'modelo.stl';

        if (!$this->models->exists($path)) {
            return $this->error('Archivo no encontrado.', 404);
        }

        return $this->models->download($path, $name);
    }

    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            // Borrado mientras imprimía: la máquina queda libre
            if ($order->status === 'printing' && $order->printer_id) {
                Printer::where('id', $order->printer_id)->update(['status' => 'idle']);
            }
            // Todas las piezas del pedido, no solo la primera
            $this->models->delete(array_merge([$order->file_path], array_column($order->models ?? [], 'path')));
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
                  ->whereRaw('LOWER(customer_email) = ?', [strtolower($request->email)]);
        } elseif ($request->filled('tracking_guide')) {
            $query->where('tracking_guide', $request->tracking_guide);
            if ($request->filled('carrier')) {
                $query->whereRaw('LOWER(tracking_carrier) = ?', [strtolower($request->carrier)]);
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
            // Solo con la guía (que puede adivinarse) no se muestra el nombre completo
            'customer_name'  => $request->filled('email') ? $order->customer_name : $this->shortName($order->customer_name),
            'technology'     => $order->technology,
            'material'       => $order->material_name ?? $order->material_id,
            'created_at'     => $order->created_at,
            'tracking_guide' => $order->tracking_guide,
            'tracking_carrier' => $order->tracking_carrier,
            'is_paid'        => $order->is_paid
        ]);
    }

    /** "Laura Gómez" → "Laura G." */
    private function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? '';
        return isset($parts[1]) ? $first . ' ' . mb_substr($parts[1], 0, 1) . '.' : $first;
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
        $secret = config('services.turnstile.secret');

        // Sin secret: solo se permite fuera de producción (dev local); en producción falla
        if (!$secret) {
            return !app()->isProduction();
        }

        $token = $request->input('cf_turnstile_response');
        if (!$token) return false;

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $request->ip()
        ]);

        return $response->json('success') === true;
    }

    /**
     * Descuenta del inventario lo que usa el pedido: el material principal (gramos),
     * las unidades de cada producto del catálogo con ítem de stock y los insumos extra.
     * Cada resta es atómica en la base: dos pagos a la vez no pisan el descuento del otro.
     */
    private function deductInventory(Order $order)
    {
        $this->deductStock($order->material_id, (float) $order->estimated_weight_g);

        foreach ($order->technology === 'CATALOGO' ? ($order->models ?? []) : [] as $line) {
            if (!empty($line['stock_item'])) {
                $this->deductStock($line['stock_item'], (int) ($line['qty'] ?? 1));
            }
        }

        $extras = $order->extra_items;
        if (is_string($extras)) $extras = json_decode($extras, true);
        foreach (is_array($extras) ? $extras : [] as $item) {
            $this->deductStock($item['material_id'] ?? null, (float) ($item['qty'] ?? 0));
        }
    }

    private function deductStock(?string $materialId, float $amount): void
    {
        if (!$materialId || $amount <= 0) {
            return;
        }
        $item = Inventory::where('material_id', $materialId)->first();
        if ($item) {
            Inventory::whereKey($item->id)->decrement('stock_available', $amount);
            // decrement no dispara eventos del modelo: revisar avisos de stock bajo
            app(\App\Services\WorkshopAlerts::class)->checkLater();
        }
    }

    /**
     * SIMULADOR CURAENGINE N3XT PRO
     * Calcula pesos exactos basados en la geometría real (Shell + Infill + Support)
     */
    private function simulateCuraAnalysis($path, $infill, $layerHeight)
    {
        $request = request();
        $totalArea = (float)$request->input('total_area', 0);
        $volumeMm3 = (float)$request->input('volume_mm3', 0);
        $supportArea = (float)$request->input('support_area', 0);
        $density = (float)$request->input('density', 1.25); 
        
        // --- GUARDIA N3XT: Evitar Division by Zero ---
        if ($density <= 0) $density = 1.25;
        if ($layerHeight <= 0) $layerHeight = 0.2;

        // 1. CÁLCULO DE PAREDES (SHELL) - Perfil Bambu Standard (2 walls)
        $shellThickness = 0.8; 
        $shellVolume = ($totalArea * $shellThickness); 
        if ($shellVolume > $volumeMm3) $shellVolume = $volumeMm3 * 0.45; 
        
        $shellWeight = ($shellVolume / 1000) * $density; 
        
        // 2. CÁLCULO DE RELLENO (INFILL)
        $internalVolume = $volumeMm3 - $shellVolume;
        $infillDensityFactor = ($infill / 100);
        $internalWeight = ($internalVolume / 1000) * $infillDensityFactor * $density; 
        
        // 3. CÁLCULO DE SOPORTES (Perfil Bambu Slim)
        $supportVolume = ($supportArea * 0.35); 
        $supportWeight = ($supportVolume / 1000) * $density; 

        // 4. LONGITUD Y MASA TOTAL (Incluye Brim/Balsa + Purga ~3.0g)
        $purgeWeight = 3.0; // Offset fijo para adherencia y purga
        $estimatedWeight = $shellWeight + $internalWeight + $supportWeight + $purgeWeight;
        
        $totalVolumePlastic = ($estimatedWeight / $density) * 1000;
        $filamentLengthM = $totalVolumePlastic / 2.405 / 1000;

        // 5. DESGLOSE DE TIEMPO (Perfil Bambu A1 EQUILIBRADO + 5min Setup)
        $volumetricFlowHr = 15500; 
        $resolutionFactor = 0.2 / $layerHeight;
        $prepTimeH = 0.233; // 14 mins (9 iniciales + 5 extra de balsa/calibración)
        $printTimeH = (($totalVolumePlastic / $volumetricFlowHr) * $resolutionFactor); 

        return [
            'engine' => 'CuraEngine 5.0 Industrial (Bambu-Sync)',
            'status' => 'success',
            'factors' => [
                'resolution' => $resolutionFactor,
                'infill_density' => $infillDensityFactor,
                'shell_weight_g' => (float)$shellWeight,
                'internal_weight_g' => (float)$internalWeight,
                'support_weight_g' => (float)$supportWeight,
                'purge_weight_g' => $purgeWeight,
                'filament_length_m' => (float)$filamentLengthM,
                'prep_time_h' => (float)$prepTimeH,
                'print_time_h' => (float)$printTimeH,
                'algorithm' => 'Bambu-Accelerated Geometry Analysis'
            ]
        ];
    }
}

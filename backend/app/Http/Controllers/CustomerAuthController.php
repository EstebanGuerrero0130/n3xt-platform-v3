<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\CustomerAccountService;
use App\Services\OrderMailer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Portal del cliente: perfil, verificación del correo, pedidos y compras del
 * catálogo. El registro y el login están en UnifiedAuthController.
 */
class CustomerAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerAccountService $accounts,
        protected OrderMailer $mailer,
    ) {}

    /** Solo lo que el panel necesita: los datos que cargó el taller no salen de aquí. */
    public function profile(Request $request)
    {
        $c = $request->user();

        return response()->json([
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'email_verified' => $c->isVerified(),
            'welcome_coupon' => $c->isVerified() && !$c->welcome_coupon_used_at ? $c->welcome_coupon : null,
            'welcome_coupon_pct' => $this->accounts->welcomePct(),
        ]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $error = $this->accounts->verify($request->user(), $data['code']);

        return $error ? $this->error($error, 422) : $this->success(null, 'Correo verificado.');
    }

    public function resendCode(Request $request)
    {
        $customer = $request->user();
        if ($customer->isVerified()) {
            return $this->success(null, 'Tu correo ya está verificado.');
        }
        $this->accounts->sendVerificationCode($customer);

        return $this->success(null, 'Te enviamos un código nuevo.');
    }

    /**
     * Pedidos del cliente autenticado (por su email), solo campos públicos.
     */
    public function orders(Request $request)
    {
        // Sin verificar, cualquiera podría registrarse con un correo ajeno y leer sus pedidos
        if (!$request->user()->isVerified()) {
            return $this->error('Verifica tu correo para ver tus pedidos.', 403);
        }
        $email = strtolower((string)$request->user()->email);

        $orders = Order::query()
            ->whereRaw('LOWER(customer_email) = ?', [$email])
            ->orderByDesc('created_at')
            ->get(['id', 'job_name', 'status', 'technology', 'material_name', 'qty', 'infill', 'dimensions_mm',
                'original_filename', 'models', 'extra_items', 'extras_cost', 'estimated_weight_g', 'estimated_duration_h',
                'total_price', 'is_paid', 'tracking_guide', 'tracking_carrier', 'created_at'])
            ->map(function ($o) {
                // Piezas sin rutas internas de almacenamiento (`path` es privado del taller)
                $models = collect($o->models ?? [])->map(fn ($m) => [
                    'name'          => $m['original_filename'] ?? null,
                    'qty'           => $m['qty'] ?? 1,
                    'dimensions_mm' => $m['dimensions_mm'] ?? null,
                    'weight_g'      => $m['weight_g'] ?? null,
                ])->values();
                if ($models->isEmpty() && $o->original_filename) {
                    $models = collect([['name' => $o->original_filename, 'qty' => $o->qty, 'dimensions_mm' => $o->dimensions_mm, 'weight_g' => $o->estimated_weight_g]]);
                }
                $extras = collect($o->extra_items ?? [])->map(fn ($e) => [
                    'name' => $e['name'] ?? $e['id'] ?? 'Extra',
                    'qty'  => $e['qty'] ?? 1,
                ])->values();

                return array_merge($o->only(['id', 'status', 'technology', 'material_name', 'qty', 'infill', 'estimated_weight_g',
                    'estimated_duration_h', 'extras_cost', 'total_price', 'is_paid', 'tracking_guide', 'tracking_carrier', 'created_at']), [
                    'project_name' => $o->job_name,
                    'models'       => $models,
                    'extras'       => $extras,
                ]);
            });

        return response()->json(['data' => $orders]);
    }

    /**
     * Compra del catálogo (carrito) de un cliente con sesión: un pedido con todos
     * los productos. Nombres y precios salen del catálogo guardado, no del navegador.
     * Acepta items[] o, por compatibilidad, product + qty.
     */
    public function catalogOrder(Request $request)
    {
        $data = $request->validate([
            'items' => 'required_without:product|array|min:1|max:30',
            'items.*.product' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1|max:99',
            'product' => 'required_without:items|string|max:255',
            'qty' => 'required_with:product|integer|min:1|max:99',
        ]);
        $customer = $request->user();
        if (!$customer->isVerified()) {
            return $this->error('Verifica tu correo para guardar pedidos en tu cuenta.', 403);
        }

        $items = $data['items'] ?? [['product' => $data['product'], 'qty' => $data['qty']]];
        $catalog = $this->activeCatalog();

        $lines = [];
        foreach ($items as $item) {
            $product = $catalog->first(fn ($p) => (isset($p['id']) && (string) $p['id'] === (string) $item['product']) || ($p['name'] ?? null) === $item['product']);
            $price = $product ? self::price($product['price'] ?? 0) : 0;
            if (!$product || $price <= 0) {
                return $this->error('«' . $item['product'] . '» ya no está disponible en el catálogo. Quítalo del carrito.', 422);
            }
            $key = (string) ($product['id'] ?? $product['name']);
            if (isset($lines[$key])) {
                $lines[$key]['qty'] += (int) $item['qty'];
                $lines[$key]['subtotal'] = round($price * $lines[$key]['qty'], 2);
                continue;
            }
            $lines[$key] = [
                'path' => null,
                'product_id' => $product['id'] ?? null,
                'original_filename' => $product['name'],
                'image' => $product['image'] ?? null,
                'qty' => (int) $item['qty'],
                'unit_price' => $price,
                'subtotal' => round($price * (int) $item['qty'], 2),
            ];
        }
        $lines = array_values($lines);
        $total = array_sum(array_column($lines, 'subtotal'));

        $order = Order::create([
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'job_name' => count($lines) === 1 ? 'Catálogo: ' . $lines[0]['original_filename'] : 'Catálogo: ' . count($lines) . ' productos',
            'technology' => 'CATALOGO',
            'material_id' => 'CATALOGO',
            'material_name' => count($lines) === 1 ? ($catalog->first(fn ($p) => ($p['name'] ?? null) === $lines[0]['original_filename'])['category'] ?? 'Producto de catálogo') : 'Productos de catálogo',
            'qty' => array_sum(array_column($lines, 'qty')),
            'models' => $lines,
            'volume_mm3' => 0,
            'scale_factor' => 1,
            'estimated_duration_h' => 0,
            'extras_cost' => 0,
            'total_price' => round($total, 2),
            'status' => 'pending',
            'comments' => 'Pedido desde el catálogo web (cierra la venta por WhatsApp).',
        ]);
        $this->mailer->received($order);

        return $this->success(['order_id' => $order->id, 'total_price' => $order->total_price], 'Pedido guardado en tu cuenta.', 201);
    }

    /** Productos activos del catálogo publicado. */
    private function activeCatalog(): \Illuminate\Support\Collection
    {
        $web = Setting::where('key', 'web')->value('value');
        $web = is_array($web) ? $web : (json_decode((string) $web, true) ?: []);

        return collect($web['catalog'] ?? [])->filter(fn ($p) => is_array($p) && ($p['status'] ?? 'active') === 'active' && !empty($p['name']));
    }

    /** Precio del catálogo: el admin lo guarda como texto ("185000", "$185.000"). */
    private static function price(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        // Formato colombiano: punto de miles y coma decimal
        return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', (string) $v)));
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Cierre de sesión exitoso']);
    }
}

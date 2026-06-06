<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Material;
use App\Services\CostCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct(
        protected CostCalculatorService $costCalculator
    ) {}

    public function index(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $settings = Setting::all()->pluck('value', 'key');
        
        // 1. Ingresos Totales (Ventas)
        $orderQuery = Order::where('status', '!=', 'cancelled');
        $allOrdersQuery = Order::query();

        if ($startDate) {
            $orderQuery->whereDate('created_at', '>=', $startDate);
            $allOrdersQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $orderQuery->whereDate('created_at', '<=', $endDate);
            $allOrdersQuery->whereDate('created_at', '<=', $endDate);
        }

        $totalRevenue = $orderQuery->sum('total_price');
        $collectedRevenue = (clone $orderQuery)->where('is_paid', true)->sum('total_price');
        $pendingRevenue = $totalRevenue - $collectedRevenue;
        
        $ordersCount = $allOrdersQuery->count();
        $completedCount = (clone $allOrdersQuery)->where('status', 'shipped')->count();

        // 2. Costos usando el servicio compartido
        $matFDMCost = 0;
        $matSLACost = 0;
        $materialCosts = 0;
        $luzCost = 0;
        $deprCost = 0;
        $mantCost = 0;
        $laborCost = 0;
        $totalHours = 0;
        $etiquetasCost = 0;
        $totalExtras = 0;
        $detailedOrders = [];
        
        /** @var \Illuminate\Database\Eloquent\Collection<int, Order> $activeOrders */
        $activeOrders = $orderQuery->get();

        foreach ($activeOrders as $order) {
            $bd = $this->costCalculator->calculateOrderBreakdown($order, $settings->toArray());

            // --- ACUMULACIÓN: Solo si está PAGADA ---
            if ($order->is_paid) {
                $luzCost += $bd['luz'];
                $deprCost += $bd['depr'];
                $mantCost += $bd['mant'];
                $laborCost += $bd['labor'];
                $etiquetasCost += $bd['etiquetas'];
                $totalHours += $bd['total_hours'];
                $totalExtras += $bd['extras'];
                
                if ($order->technology === 'SLA') $matSLACost += $bd['material'];
                else $matFDMCost += $bd['material'];
            }

            // Individual Order Audit
            $detailedOrders[] = [
                'id'          => $order->id,
                'customer'    => $order->customer_name,
                'file'        => $order->original_filename,
                'date'        => $order->created_at->format('Y-m-d'),
                'total_price' => round($order->total_price, 2),
                'expenses'    => $bd['total_cost'],
                'profit'      => $bd['margin'],
                'is_paid'     => (bool)$order->is_paid,
                'breakdown'   => [
                    'luz'       => $bd['luz'],
                    'labor'     => $bd['labor'],
                    'mant'      => $bd['mant'],
                    'depr'      => $bd['depr'],
                    'mat'       => $bd['material'],
                    'etiquetas' => $bd['etiquetas'],
                    'extras'    => $bd['extras'],
                ]
            ];
        }

        $materialCosts = $matFDMCost + $matSLACost;
        $totalOperationalCost = $luzCost + $deprCost + $mantCost + $laborCost + $etiquetasCost;
        $totalExpenses = $materialCosts + $totalOperationalCost + $totalExtras;
        $netProfit = $collectedRevenue - $totalExpenses;

        // Monthly Breakdown (usando el servicio)
        $monthlyQuery = Order::where('status', '!=', 'cancelled');
        if ($startDate) $monthlyQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $monthlyQuery->whereDate('created_at', '<=', $endDate);

        $monthlyData = $this->costCalculator->calculateMonthlyBreakdown($monthlyQuery->get(), $settings->toArray());

        // By Technology
        $techQuery = Order::where('status', '!=', 'cancelled');
        if ($startDate) $techQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $techQuery->whereDate('created_at', '<=', $endDate);

        $techData = $techQuery->select('technology', DB::raw('count(*) as count'))
            ->groupBy('technology')
            ->get();

        // Top Clientes (LTV)
        $customersQuery = Order::where('status', '!=', 'cancelled');
        if ($startDate) $customersQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $customersQuery->whereDate('created_at', '<=', $endDate);

        $topCustomers = $customersQuery->select('customer_name', DB::raw('SUM(total_price) as total_spent'), DB::raw('COUNT(*) as orders_count'))
            ->groupBy('customer_name')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        foreach ($topCustomers as $customer) {
            $lastOrder = Order::where('customer_name', $customer->customer_name)
                ->where('status', '!=', 'cancelled')
                ->latest()
                ->first(['id', 'original_filename', 'created_at']);
            
            $customer->last_order_id = $lastOrder->id ?? null;
            $customer->last_order_file = $lastOrder->original_filename ?? 'Sin archivo';
            $customer->last_order_date = $lastOrder->created_at ?? null;
        }

        // Tendencia MoM
        $momGrowthPct = 0;
        if (!$startDate && !$endDate) {
            $currentMonth = date('Y-m');
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            $currentMonthRevenue = Order::where('status', '!=', 'cancelled')
                ->whereRaw("substring(created_at::text from 1 for 7) = ?", [$currentMonth])
                ->sum('total_price');

            $lastMonthRevenue = Order::where('status', '!=', 'cancelled')
                ->whereRaw("substring(created_at::text from 1 for 7) = ?", [$lastMonth])
                ->sum('total_price');

            if ($lastMonthRevenue > 0) {
                $momGrowthPct = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
            } elseif ($currentMonthRevenue > 0) {
                $momGrowthPct = 100;
            }
        }

        // Índice de Desperdicio (Tasa de Fallos)
        $wasteCost = 0;
        $wasteWeight = 0;
        $cancelledQuery = Order::where('status', 'cancelled');
        if ($startDate) $cancelledQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $cancelledQuery->whereDate('created_at', '<=', $endDate);

        $cancelledOrders = $cancelledQuery->get();
        foreach ($cancelledOrders as $order) {
            $mat = Material::find($order->material_id);
            $costPerG = ($mat ? $mat->cost_per_kg : 85000) / 1000;
            $wasteCost += $order->estimated_weight_g * $costPerG;
            $wasteWeight += $order->estimated_weight_g;
        }

        return response()->json([
            'summary' => [
                'total_revenue'       => round($totalRevenue, 2),
                'collected_revenue'   => round($collectedRevenue, 2),
                'pending_revenue'     => round($pendingRevenue, 2),
                'total_material_cost' => round($materialCosts, 2),
                'operational_overhead'=> round($totalOperationalCost, 2),
                'total_extras_cost'   => round($totalExtras, 2),
                'total_expenses'      => round($totalExpenses, 2),
                'net_profit'          => round($netProfit, 2),
                'profit_margin_pct'   => $collectedRevenue > 0 ? round(($netProfit / $collectedRevenue) * 100, 1) : 0,
                'orders_count'        => $ordersCount,
                'completed_count'     => $completedCount,
                'total_hours'         => round($totalHours, 1),
                'total_weight_kg'     => round($activeOrders->where('technology', '!=', 'SLA')->sum('estimated_weight_g') / 1000, 2),
                'total_resin_ml'      => round($activeOrders->where('technology', 'SLA')->sum('estimated_weight_g'), 1),
                'mom_growth_pct'      => round($momGrowthPct, 1),
                'waste_cost'          => round($wasteCost, 2),
                'waste_weight_g'      => round($wasteWeight, 2),
                'breakdown' => [
                    'luz'       => round($luzCost, 2),
                    'depr'      => round($deprCost, 2),
                    'mant'      => round($mantCost, 2),
                    'labor'     => round($laborCost, 2),
                    'etiquetas' => round($etiquetasCost, 2),
                    'mat_fdm'   => round($matFDMCost, 2),
                    'mat_sla'   => round($matSLACost, 2),
                ]
            ],
            'detailed_orders' => $detailedOrders,
            'monthly'         => $monthlyData,
            'by_technology'   => $techData,
            'top_customers'   => $topCustomers,
            'settings'        => $settings,
            'filters' => [
                'start_date' => $startDate,
                'end_date'   => $endDate
            ]
        ]);
    }

    public function exportCsv()
    {
        $orders = Order::all();
        $csvFileName = 'ventas_n3xt_' . date('Y-m-d') . '.csv';
        $headers = [
            "Content-Type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Cliente', 'Tecnología', 'Material', 'Peso (g)', 'Precio (COP)', 'Estado', 'Estado Pago', 'Fecha'];

        $callback = function() use($orders, $columns) {
            $file = fopen('php://output', 'w');
            // Añadir BOM para que Excel reconozca UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->customer_name,
                    $order->technology,
                    $order->material_name,
                    $order->estimated_weight_g,
                    $order->total_price,
                    $order->status,
                    $order->is_paid ? 'PAGADO' : 'PENDIENTE',
                    $order->created_at->format('Y-m-d H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

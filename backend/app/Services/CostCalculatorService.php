<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Order;

/**
 * N3XT Cost Calculator Service
 * 
 * Centraliza toda la lógica de cálculo de costos de producción.
 * Elimina la duplicación que existía en AnalyticsController,
 * OrderController y el frontend.
 */
class CostCalculatorService
{
    /**
     * Calculate the production cost breakdown for a single order.
     *
     * @param Order $order
     * @param array $settings  Full settings array (keys: infra, prep, oper, margin)
     * @param float|null $materialCostPerKg  Override material cost (uses snapshot or DB otherwise)
     * @return array
     */
    public function calculateOrderBreakdown(Order $order, array $settings, ?float $materialCostPerKg = null): array
    {
        $snap = $order->cost_snapshot;
        if (is_string($snap)) $snap = json_decode($snap, true);
        $hasSnapshot = !empty($snap) && isset($snap['settings']);

        $s = $hasSnapshot ? $snap['settings'] : $settings;
        $orderHours = (float)($order->estimated_duration_h ?? 0);

        $loadFactor = (float)($s['infra']['load_factor'] ?? 0.4);
        $prepTimePct = (float)($s['prep']['prep_time_pct'] ?? 10) / 100;

        // Infrastructure costs
        $luz = $orderHours * $loadFactor * (float)($s['infra']['luz_hr'] ?? 0);
        $depr = $orderHours * (float)($s['infra']['depr_hr'] ?? 0);
        $mant = $orderHours * (float)($s['infra']['mant_hr'] ?? 0);
        $labor = $orderHours * $prepTimePct * (float)($s['prep']['mano_obra_hr'] ?? 0);
        $etiquetas = (float)($s['infra']['etiquetas'] ?? 0);

        // Material cost
        $matCostPerKg = $materialCostPerKg;
        if ($matCostPerKg === null) {
            if ($hasSnapshot && isset($snap['material_cost_per_kg'])) {
                $matCostPerKg = (float)$snap['material_cost_per_kg'];
            } else {
                $material = Material::find($order->material_id);
                $matCostPerKg = $material ? (float)$material->cost_per_kg : 85000;
            }
        }
        $matCost = (($order->estimated_weight_g ?? 0) / 1000) * $matCostPerKg;

        // Extras
        $extras = (float)($order->extras_cost ?? 0);

        $productionCost = $matCost + $luz + $depr + $mant + $labor + $etiquetas + $extras;

        return [
            'material'       => round($matCost, 2),
            'luz'            => round($luz, 2),
            'labor'          => round($labor, 2),
            'depr'           => round($depr, 2),
            'mant'           => round($mant, 2),
            'etiquetas'      => round($etiquetas, 2),
            'extras'         => round($extras, 2),
            'total_cost'     => round($productionCost, 2),
            'margin'         => round((float)$order->total_price - $productionCost, 2),
            'total_hours'    => $orderHours,
            'mat_cost_per_kg' => $matCostPerKg,
        ];
    }

    /**
     * Calculate the total price for an order including margins and IVA.
     *
     * @param float $productionCost  Base production cost
     * @param array $settings  Settings containing oper and margin data
     * @param array $overrides  Optional overrides for transporte, marketing, fallos, ganancia, iva percentages
     * @return array  { subtotal, logistics, marketing, failures, profit_amount, iva, total }
     */
    public function calculateFinalPrice(float $productionCost, array $settings, array $overrides = []): array
    {
        $transportePct = $overrides['transporte_pct'] ?? (float)($settings['oper']['transporte'] ?? 0);
        $marketingPct  = $overrides['marketing_pct']  ?? (float)($settings['oper']['marketing'] ?? 0);
        $fallosPct     = $overrides['fallos_pct']     ?? (float)($settings['oper']['fallos'] ?? 0);
        $gananciaPct   = $overrides['ganancia_pct']   ?? (float)($settings['oper']['ganancia'] ?? 0);
        $ivaRate       = $overrides['iva_rate']        ?? (float)($settings['margin']['iva'] ?? 19) / 100;

        $logistics     = $productionCost * ($transportePct / 100);
        $marketing     = $productionCost * ($marketingPct / 100);
        $failures      = $productionCost * ($fallosPct / 100);
        $profitAmount  = $productionCost * ($gananciaPct / 100);

        $subtotal = $productionCost + $logistics + $marketing + $failures + $profitAmount;

        $discount = 0;
        if (!empty($overrides['discount_pct'])) {
            $discount = $subtotal * ($overrides['discount_pct'] / 100);
            $subtotal = max($subtotal - $discount, $productionCost);
        }

        $iva = $subtotal * $ivaRate;
        $total = $subtotal + $iva;

        return [
            'production_cost' => round($productionCost, 2),
            'logistics'       => round($logistics, 2),
            'marketing'       => round($marketing, 2),
            'failures'        => round($failures, 2),
            'profit_amount'   => round($profitAmount, 2),
            'discount'        => round($discount, 2),
            'subtotal'        => round($subtotal, 2),
            'iva'             => round($iva, 2),
            'total'           => round($total, 2),
            'profit_margin_pct' => $productionCost > 0 ? round((($subtotal - $productionCost) / $subtotal) * 100, 1) : 0,
        ];
    }

    /**
     * Calculate the cost of an extra item based on material unit type.
     *
     * @param float $costPerKg  Material cost_per_kg
     * @param string $unit  Unit type (g, ml, kg, l, pieza, servicio, etc.)
     * @param float $qty  Quantity
     * @return float
     */
    public function calculateExtraCost(float $costPerKg, string $unit, float $qty): float
    {
        $isWeightOrVolume = in_array(strtolower($unit), ['g', 'ml', 'kg', 'l']);
        return $isWeightOrVolume ? ($costPerKg * ($qty / 1000)) : ($costPerKg * $qty);
    }

    /**
     * Calculate aggregated monthly analytics from a collection of orders.
     *
     * @param \Illuminate\Support\Collection $orders
     * @param array $settings
     * @return array
     */
    public function calculateMonthlyBreakdown($orders, array $settings): array
    {
        return $orders
            ->groupBy(function ($val) {
                return $val->created_at->format('Y-m');
            })
            ->map(function ($items, $month) use ($settings) {
                $rev = $items->sum('total_price');
                $exp = 0;
                foreach ($items as $order) {
                    $breakdown = $this->calculateOrderBreakdown($order, $settings);
                    $exp += $breakdown['total_cost'];
                }
                return [
                    'month'    => $month,
                    'revenue'  => round($rev, 2),
                    'expenses' => round($exp, 2),
                    'profit'   => round($rev - $exp, 2),
                    'count'    => count($items),
                ];
            })
            ->values()
            ->toArray();
    }
}

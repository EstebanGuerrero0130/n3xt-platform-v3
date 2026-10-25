<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Setting;

/**
 * Cotizador del servidor.
 *
 * Porta FIELMENTE la fórmula del frontend:
 *   - frontend/src/views/QuoteView.vue        (fetchSettings + calculatePrice)
 *   - frontend/src/services/costCalculator.ts (calcProductionCost, calcFinalPrice, calcExtraCost)
 *   - OrderController::simulateCuraAnalysis   (peso/tiempo FDM, vía fdmFactors())
 *
 * El orden de las operaciones de coma flotante se mantiene igual que en JS para que
 * el resultado coincida (bit a bit en la práctica) con el estimado del navegador.
 * Math.round(x) de JS se replica con floor(x + 0.5).
 */
class QuotePricingService
{
    public const SLA_ALCOHOL_ID = 'Alco_ML_05';
    public const SLA_CURING_ID = 'Cicl_Serv_06';
    public const SLA_ALCOHOL_ML_PER_PIECE = 50;

    public function __construct(
        protected CostCalculatorService $costCalculator
    ) {}

    // ------------------------------------------------------------------
    // FDM: simulador "CuraEngine N3XT" (misma lógica que /process-stl)
    // ------------------------------------------------------------------

    /**
     * Factores de laminado FDM. Es exactamente el cálculo que hacía
     * OrderController::simulateCuraAnalysis (que ahora delega aquí).
     */
    public function fdmFactors(float $volumeMm3, float $totalArea, float $supportArea, float $density, int $infill, float $layerHeight): array
    {
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
        $purgeWeight = 3.0;
        $estimatedWeight = $shellWeight + $internalWeight + $supportWeight + $purgeWeight;

        $totalVolumePlastic = ($estimatedWeight / $density) * 1000;
        $filamentLengthM = $totalVolumePlastic / 2.405 / 1000;

        // 5. DESGLOSE DE TIEMPO (Perfil Bambu A1 EQUILIBRADO + 5min Setup)
        $volumetricFlowHr = 15500;
        $resolutionFactor = 0.2 / $layerHeight;
        $prepTimeH = 0.233;
        $printTimeH = (($totalVolumePlastic / $volumetricFlowHr) * $resolutionFactor);

        return [
            'resolution' => $resolutionFactor,
            'infill_density' => $infillDensityFactor,
            'shell_weight_g' => (float)$shellWeight,
            'internal_weight_g' => (float)$internalWeight,
            'support_weight_g' => (float)$supportWeight,
            'purge_weight_g' => $purgeWeight,
            'filament_length_m' => (float)$filamentLengthM,
            'prep_time_h' => (float)$prepTimeH,
            'print_time_h' => (float)$printTimeH,
        ];
    }

    // ------------------------------------------------------------------
    // Cotización (función pura: sin BD)
    // ------------------------------------------------------------------

    /**
     * @param array $input [
     *   'technology'   => 'FDM'|'SLA',
     *   'infill'       => int (FDM, por defecto 15),
     *   'layer_height' => float (FDM, por defecto 0.2),
     *   'models' => [[
     *       'name', 'qty' (>=1), 'scale_factor' (1 = 100%),
     *       'volume_mm3', 'total_area_mm2', 'support_area_mm2'  (SIN escalar, en mm),
     *       'bbox_mm' => [x, y, z] (SIN escalar, opcional),
     *       'dimensions_mm' => "X x Y x Z" (cliente, ya escalado, opcional),
     *       'infill'?, 'layer_height'?  (override por modelo)
     *   ]],
     *   'extras' => [['material_id', 'qty']],
     * ]
     * @param array      $settings  Settings crudos (claves infra, prep, oper, margin).
     * @param array      $material  ['id', 'cost_per_kg', 'density'].
     * @param array      $utilities Insumos indexados por id: ['id','name','cost_per_kg','unit'].
     * @param array|null $coupon    Cupón ya validado: ['code','discount_pct','label'].
     */
    public function quote(array $input, array $settings, array $material, array $utilities, ?array $coupon = null): array
    {
        $cfg = self::normalizeSettings($settings);
        $technology = strtoupper(trim((string)($input['technology'] ?? 'FDM')));
        $isFdm = $technology === 'FDM'; // el frontend usa "if FDM ... else (lógica SLA)"

        $costPerKg = self::jsOr($material['cost_per_kg'] ?? null, 0.0);

        $defaultInfill = (int)(self::jsOr($input['infill'] ?? null, 15.0));
        $defaultLayer = self::jsOr($input['layer_height'] ?? null, 0.2);

        $modelsIn = array_values($input['models'] ?? []);
        $models = [];

        foreach ($modelsIn as $i => $m) {
            $qty = max(1, (int)floor(self::jsOr($m['qty'] ?? null, 1.0)));

            $s = self::jsOr($m['scale_factor'] ?? null, 1.0);
            $s = abs($s);
            // Igual que StlViewer: volumen × |sx·sy·sz|, áreas × |sx·sz|
            $volume = (float)($m['volume_mm3'] ?? 0) * abs($s * $s * $s);
            $totalArea = (float)($m['total_area_mm2'] ?? 0) * abs($s * $s);
            $supportArea = (float)($m['support_area_mm2'] ?? 0) * abs($s * $s);

            $row = [
                'name' => (string)($m['name'] ?? ('Modelo ' . ($i + 1))),
                'qty' => $qty,
                'scale_factor' => $s,
                'volume_mm3' => $volume,
                'total_area_mm2' => $totalArea,
                'support_area_mm2' => $supportArea,
                'weight_g' => 0.0,
                'duration_h' => 0.0,
            ];

            if ($isFdm) {
                $infill = (int)(self::jsOr($m['infill'] ?? null, (float)$defaultInfill));
                $layer = self::jsOr($m['layer_height'] ?? null, $defaultLayer);
                $densityRaw = self::jsNumber($material['density'] ?? null);
                $density = is_nan($densityRaw) ? 0.0 : $densityRaw;

                $cf = $this->fdmFactors($volume, $totalArea, $supportArea, $density, $infill, $layer);
                $row['weight_g'] = $cf['shell_weight_g'] + $cf['internal_weight_g'] + $cf['support_weight_g'] + (self::jsOr($cf['purge_weight_g'], 3.0));
                $row['duration_h'] = $cf['prep_time_h'] + $cf['print_time_h'];
                $row['infill'] = $infill;
                $row['layer_height'] = $layer;
            } else {
                // SLA: volumen mm³ → cm³
                $volCm3 = $volume / 1000;
                $density = self::jsOr($material['density'] ?? null, 1.1);
                $row['weight_g'] = $volCm3 * $density * 1.1;
                $heightMm = $this->slaHeight($m, $s);
                $row['height_mm'] = $heightMm;
                $row['duration_h'] = max(0.5, $heightMm / 25);
            }

            $models[] = $row;
        }

        // Totales: igual que calcQuoteEstimate (cada modelo × su qty; piezas = Σqty)
        $totalQty = 0;
        $totalWeight = 0.0;
        $totalDuration = 0.0;
        $totalVolume = 0.0;
        foreach ($models as $row) {
            $totalQty += $row['qty'];
            $totalVolume += $row['volume_mm3'] * $row['qty'];
            $totalWeight += $row['weight_g'] * $row['qty'];
            $totalDuration += $row['duration_h'] * $row['qty'];
        }
        // Piezas del pedido: divisor del costo unitario y multiplicador del subtotal
        $qtyForPrice = $totalQty;

        // Extras (auto-SLA + seleccionados)
        $utilityCost = 0.0;
        $extras = [];
        if (!$isFdm) {
            $alcohol = $utilities[self::SLA_ALCOHOL_ID] ?? null;
            if ($alcohol) {
                $q = self::SLA_ALCOHOL_ML_PER_PIECE * $qtyForPrice;
                $cost = $this->extraCost($alcohol, 'ml', $q);
                $utilityCost += $cost;
                $extras[] = $this->extraRow($alcohol, $q, $cost, true);
            }
            $curing = $utilities[self::SLA_CURING_ID] ?? null;
            if ($curing) {
                $q = $qtyForPrice;
                $cost = $this->extraCost($curing, 'servicio', $q);
                $utilityCost += $cost;
                $extras[] = $this->extraRow($curing, $q, $cost, true);
            }
        }
        foreach (($input['extras'] ?? []) as $item) {
            $id = (string)($item['material_id'] ?? $item['id'] ?? '');
            $extra = $utilities[$id] ?? null;
            if ($extra) {
                $q = self::jsNumber($item['qty'] ?? 1);
                if (is_nan($q)) $q = 0.0;
                $cost = $this->extraCost($extra, 'g', $q);
                $utilityCost += $cost;
                $extras[] = $this->extraRow($extra, $q, $cost, false);
            }
        }

        // calcProductionCost
        $infra = $cfg['infra'];
        $prep = $cfg['prep'];
        $loadFactor = $infra['load_factor'];
        $prepTimePct = $prep['prep_time_pct'] / 100;

        $matCost = ($totalWeight / 1000) * $costPerKg;
        $luz = $totalDuration * $loadFactor * $infra['luz_hr'];
        $labor = ($totalDuration * $prepTimePct) * $prep['mano_obra_hr'];
        $depr = $totalDuration * $infra['depr_hr'];
        $mant = $totalDuration * $infra['mant_hr'];
        $etiquetas = $infra['etiquetas'];
        $productionCost = $matCost + $luz + $labor + $depr + $mant + $etiquetas + $utilityCost;

        // calcFinalPrice sobre el costo unitario
        $baseUnitCost = $qtyForPrice > 0 ? $productionCost / $qtyForPrice : 0;
        $oper = $cfg['oper'];
        $logistics = $baseUnitCost * ($oper['transporte'] / 100);
        $marketing = $baseUnitCost * ($oper['marketing'] / 100);
        $failures = $baseUnitCost * ($oper['fallos'] / 100);
        $profit = $baseUnitCost * ($oper['ganancia'] / 100);
        $unitSubtotal = $baseUnitCost + $logistics + $marketing + $failures + $profit;
        $unitPrice = self::jsRound($unitSubtotal);

        $subtotal = $unitPrice * $qtyForPrice;

        $discountPct = $coupon ? (float)$coupon['discount_pct'] : 0.0;
        $discountAmount = $coupon ? ($subtotal * ($discountPct / 100)) : 0;
        $finalSubtotal = self::jsRound($subtotal - $discountAmount);
        $ivaPct = $cfg['margin']['iva'];
        $ivaRate = $ivaPct / 100;
        $iva = self::jsRound($finalSubtotal * $ivaRate);
        $total = $finalSubtotal + $iva;

        $marginCost = (self::jsRound($logistics) + self::jsRound($marketing) + self::jsRound($failures) + self::jsRound($profit)) * $qtyForPrice;

        // Reparto informativo del subtotal (antes de descuento) entre modelos,
        // proporcional a su costo de producción directo. Suma exacta = subtotal.
        $hourRate = $loadFactor * $infra['luz_hr'] + $prepTimePct * $prep['mano_obra_hr'] + $infra['depr_hr'] + $infra['mant_hr'];
        $shares = [];
        foreach ($models as $row) {
            $shares[] = (($row['weight_g'] * $row['qty']) / 1000) * $costPerKg + ($row['duration_h'] * $row['qty']) * $hourRate;
        }
        $shareSum = array_sum($shares);
        $allocated = 0;
        $lastIdx = count($models) - 1;
        $outModels = [];
        foreach ($models as $idx => $row) {
            if ($idx === $lastIdx) {
                $modelSubtotal = (int)$subtotal - $allocated;
            } else {
                $ratio = $shareSum > 0 ? $shares[$idx] / $shareSum : 1 / count($models);
                $modelSubtotal = (int)self::jsRound($subtotal * $ratio);
                $allocated += $modelSubtotal;
            }
            $out = [
                'name' => $row['name'],
                'qty' => $row['qty'],
                'scale_factor' => $row['scale_factor'],
                'volume_mm3' => round($row['volume_mm3'], 2),
                'weight_g' => round($row['weight_g'], 2),
                'duration_h' => round($row['duration_h'], 3),
                'subtotal' => $modelSubtotal,
            ];
            if (isset($row['infill'])) {
                $out['infill'] = $row['infill'];
                $out['layer_height'] = $row['layer_height'];
            }
            if (isset($row['height_mm'])) {
                $out['height_mm'] = round($row['height_mm'], 2);
            }
            $out['raw'] = [
                'volume_mm3' => $row['volume_mm3'],
                'total_area_mm2' => $row['total_area_mm2'],
                'support_area_mm2' => $row['support_area_mm2'],
                'weight_g' => $row['weight_g'],
                'duration_h' => $row['duration_h'],
            ];
            $outModels[] = $out;
        }

        return [
            'technology' => $isFdm ? 'FDM' : 'SLA',
            'models' => $outModels,
            'qty' => $totalQty,
            'weight_g' => $totalWeight,
            'duration_h' => $totalDuration,
            'volume_mm3' => $totalVolume,
            'production' => [
                'material' => $matCost,
                'luz' => $luz,
                'labor' => $labor,
                'depr' => $depr,
                'mant' => $mant,
                'etiquetas' => $etiquetas,
                'extras' => $utilityCost,
                'total' => $productionCost,
            ],
            'extras' => $extras,
            'extras_cost' => $utilityCost,
            'unit_price' => (int)$unitPrice,
            'margin' => (int)$marginCost,
            'subtotal' => (int)$subtotal,
            'coupon' => $coupon ? [
                'code' => $coupon['code'],
                'discount_pct' => $discountPct,
                'label' => $coupon['label'] ?? null,
            ] : null,
            'discount' => (int)self::jsRound($discountAmount),
            'subtotal_after_discount' => (int)$finalSubtotal,
            'iva_pct' => $ivaPct,
            'iva' => (int)$iva,
            'total' => (int)$total,
        ];
    }

    // ------------------------------------------------------------------
    // Cupones
    // ------------------------------------------------------------------

    /**
     * Busca un cupón activo (case-insensitive) en la lista de settings `coupons`.
     *
     * @return array{code: string, discount_pct: float, label: string|null}|null
     */
    public static function findCoupon(?string $code, array $coupons): ?array
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return null;
        }
        foreach ($coupons as $c) {
            if (!is_array($c) || !isset($c['code'])) continue;
            if (strtoupper(trim((string)$c['code'])) !== $code) continue;
            if (array_key_exists('active', $c) && !filter_var($c['active'], FILTER_VALIDATE_BOOLEAN)) continue;
            $pct = self::jsNumber($c['discount_pct'] ?? null);
            if (is_nan($pct) || $pct <= 0 || $pct > 100) continue;
            return [
                'code' => strtoupper(trim((string)$c['code'])),
                'discount_pct' => $pct,
                'label' => isset($c['label']) ? (string)$c['label'] : null,
            ];
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Acceso a BD (lo usa el controlador)
    // ------------------------------------------------------------------

    public function loadSettings(): array
    {
        return Setting::all()->pluck('value', 'key')->toArray();
    }

    public function findActiveCoupon(?string $code, ?array $settings = null): ?array
    {
        $settings ??= $this->loadSettings();
        $coupons = $settings['coupons'] ?? [];
        return self::findCoupon($code, is_array($coupons) ? $coupons : []);
    }

    /**
     * Insumos/servicios indexados por id (en el frontend: materiales con type !== 'material').
     */
    public function loadUtilities(): array
    {
        $out = [];
        $rows = Material::query()
            ->where(function ($q) {
                $q->where('type', '!=', 'material')->orWhereNull('type');
            })
            ->get();
        foreach ($rows as $u) {
            $out[$u->id] = [
                'id' => $u->id,
                'name' => $u->name,
                'cost_per_kg' => $u->cost_per_kg,
                'unit' => $u->unit,
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Normaliza settings exactamente como QuoteView::fetchSettings
     * (Number(x) || default; IVA permite 0).
     */
    public static function normalizeSettings(array $settings): array
    {
        $pick = static fn ($key) => (isset($settings[$key]) && is_array($settings[$key])) ? $settings[$key] : [];
        $infra = array_merge(['luz_hr' => 0, 'depr_hr' => 0, 'mant_hr' => 0, 'etiquetas' => 0, 'load_factor' => 0.4], $pick('infra'));
        $prep = array_merge(['mano_obra_hr' => 0, 'prep_time_pct' => 10], $pick('prep'));
        $oper = array_merge(['transporte' => 0, 'ganancia' => 0, 'marketing' => 0, 'fallos' => 0], $pick('oper'));
        $margin = array_merge(['iva' => 19], $pick('margin'));

        $ivaRaw = $margin['iva'] ?? null;
        $ivaNum = ($ivaRaw === null || (is_string($ivaRaw) && $ivaRaw === '')) ? NAN : self::jsNumber($ivaRaw);

        return [
            'infra' => [
                'luz_hr' => self::jsOr($infra['luz_hr'] ?? null, 0.0),
                'depr_hr' => self::jsOr($infra['depr_hr'] ?? null, 0.0),
                'mant_hr' => self::jsOr($infra['mant_hr'] ?? null, 0.0),
                'etiquetas' => self::jsOr($infra['etiquetas'] ?? null, 0.0),
                'load_factor' => self::jsOr($infra['load_factor'] ?? null, 0.4),
            ],
            'prep' => [
                'mano_obra_hr' => self::jsOr($prep['mano_obra_hr'] ?? null, 0.0),
                'prep_time_pct' => self::jsOr($prep['prep_time_pct'] ?? null, 10.0),
            ],
            'oper' => [
                'transporte' => self::jsOr($oper['transporte'] ?? null, 0.0),
                'ganancia' => self::jsOr($oper['ganancia'] ?? null, 0.0),
                'marketing' => self::jsOr($oper['marketing'] ?? null, 0.0),
                'fallos' => self::jsOr($oper['fallos'] ?? null, 0.0),
            ],
            'margin' => [
                'iva' => is_finite($ivaNum) ? $ivaNum : 19.0,
            ],
        ];
    }

    /** Equivalente a Number(v) de JS para los tipos que llegan de JSON/BD. */
    public static function jsNumber(mixed $v): float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if (is_bool($v)) return $v ? 1.0 : 0.0;
        if ($v === null) return 0.0;
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '') return 0.0;
            return is_numeric($t) ? (float)$t : NAN;
        }
        return NAN;
    }

    /** Equivalente a `Number(v) || def` de JS (0 y NaN caen al valor por defecto). */
    public static function jsOr(mixed $v, float $default): float
    {
        $n = self::jsNumber($v);
        return (is_nan($n) || $n == 0.0) ? $default : $n;
    }

    /** Math.round de JS (mitades hacia +∞). */
    public static function jsRound(float $x): float
    {
        return floor($x + 0.5);
    }

    /**
     * Altura SLA (el frontend usa dimensions.z || dimensions.y del visor, ya escalado).
     * Si el cliente manda dimensions_mm se usa, acotado a lo geométricamente posible
     * para la malla (entre su menor lado y su diagonal); si no, el bbox del archivo.
     */
    private function slaHeight(array $m, float $scale): float
    {
        $bbox = isset($m['bbox_mm']) && is_array($m['bbox_mm']) ? array_values($m['bbox_mm']) : null;
        $hasBbox = $bbox !== null && count($bbox) >= 3;

        $client = self::parseDimensions($m['dimensions_mm'] ?? null);
        $candidate = 0.0;
        if ($client !== null) {
            $candidate = $client[2] > 0 ? $client[2] : ($client[1] > 0 ? $client[1] : 0.0);
        }

        if ($candidate > 0) {
            if ($hasBbox) {
                $sx = (float)$bbox[0] * $scale;
                $sy = (float)$bbox[1] * $scale;
                $sz = (float)$bbox[2] * $scale;
                $lo = min($sx, $sy, $sz);
                $hi = sqrt($sx * $sx + $sy * $sy + $sz * $sz);
                $candidate = min(max($candidate, $lo), $hi);
            }
            return $candidate;
        }

        if ($hasBbox) {
            $z = (float)$bbox[2] * $scale;
            return $z > 0 ? $z : (float)$bbox[1] * $scale;
        }
        return 0.0;
    }

    /** "12.0x3.5x4" o "12 x 3.5 x 4" → [12.0, 3.5, 4.0] */
    public static function parseDimensions(mixed $dims): ?array
    {
        if (!is_string($dims) || $dims === '') return null;
        if (!preg_match_all('/-?\d+(?:[.,]\d+)?/', $dims, $mm) || count($mm[0]) < 3) return null;
        $vals = array_map(static fn ($v) => abs((float)str_replace(',', '.', $v)), array_slice($mm[0], 0, 3));
        return $vals;
    }

    private function extraCost(array $utility, string $defaultUnit, float $qty): float
    {
        $unit = (string)($utility['unit'] ?? '');
        if ($unit === '') $unit = $defaultUnit;
        return $this->costCalculator->calculateExtraCost(self::jsOr($utility['cost_per_kg'] ?? null, 0.0), $unit, $qty);
    }

    private function extraRow(array $utility, float $qty, float $cost, bool $auto): array
    {
        return [
            'material_id' => $utility['id'] ?? null,
            'name' => $utility['name'] ?? null,
            'qty' => $qty,
            'unit' => $utility['unit'] ?? null,
            'cost' => $cost,
            'auto' => $auto,
        ];
    }
}

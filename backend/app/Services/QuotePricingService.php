<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Printer;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    /** Descuento máximo de un cupón (%), aunque el admin guarde más. */
    public const MAX_COUPON_PCT = 50;

    public const SLA_ALCOHOL_ID = 'Alco_ML_05';
    public const SLA_CURING_ID = 'Cicl_Serv_06';
    public const SLA_ALCOHOL_ML_PER_PIECE = 50;

    /** FDM: preparación (h) y purga (g) de cada cama, no de cada pieza. */
    public const FDM_PREP_H = 0.233;
    public const FDM_PURGE_G = 3.0;
    /** Separación entre piezas (mm) y parte de la cama que se aprovecha. */
    public const BED_SPACING_MM = 5;
    public const BED_PACKING = 0.7;
    /** Cama por defecto si ninguna impresora activa tiene medidas (settings `beds`). */
    public const DEFAULT_BEDS = ['FDM' => ['x' => 256, 'y' => 256, 'z' => 256], 'SLA' => ['x' => 218, 'y' => 123, 'z' => 250]];
    public const AXES = ['x' => 0, 'y' => 1, 'z' => 2];

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
     *   'urgent'       => bool (recargo `rules.rush_pct`),
     *   'bed'          => ['x', 'y', 'z'] mm de la cama (el controlador la lee de las impresoras;
     *                     si falta se usa settings `beds`),
     *   'models' => [[
     *       'name', 'qty' (>=1), 'scale_factor' (1 = 100%),
     *       'volume_mm3', 'total_area_mm2', 'support_area_mm2'  (SIN escalar, en mm),
     *       'down_area_mm2'   => ['x','y','z'] (SIN escalar, opcional: área hacia abajo por eje),
     *       'bed_contact_mm2' => ['x','y','z'] (SIN escalar, opcional: parte que apoya en la cama),
     *       'bbox_mm' => [x, y, z] (SIN escalar, opcional),
     *       'dimensions_mm' => "X x Y x Z" (cliente, solo informativo: no entra al precio),
     *       'infill'?, 'layer_height'?  (override por modelo)
     *   ]],
     *   'extras' => [['material_id', 'qty']],
     * ]
     * @param array      $settings  Settings crudos (infra, prep, oper, margin, beds, rules, calibration).
     * @param array      $material  ['id', 'cost_per_kg', 'density', 'speed_factor'?, 'waste_pct'?, 'failure_pct'?].
     * @param array      $utilities Insumos indexados por id: ['id','name','cost_per_kg','unit'].
     * @param array|null $coupon    Cupón ya validado: ['code','discount_pct','label'].
     */
    public function quote(array $input, array $settings, array $material, array $utilities, ?array $coupon = null): array
    {
        $cfg = self::normalizeSettings($settings);
        $technology = strtoupper(trim((string)($input['technology'] ?? 'FDM')));
        $isFdm = $technology === 'FDM'; // el frontend usa "if FDM ... else (lógica SLA)"
        $tech = $isFdm ? 'FDM' : 'SLA';

        $costPerKg = self::jsOr($material['cost_per_kg'] ?? null, 0.0);
        $params = self::materialParams($material);
        $calibration = self::calibrationFactor($cfg['calibration'], $tech, (string)($material['id'] ?? ''));
        // Tiempo de impresión × velocidad del material × calibración del taller
        $speed = $params['speed_factor'] * $calibration;
        $waste = 1 + $params['waste_pct'] / 100;
        $bed = self::normalizeBed($input['bed'] ?? null, $cfg['beds'][$tech]);

        $defaultInfill = (int)(self::jsOr($input['infill'] ?? null, 15.0));
        $defaultLayer = self::jsOr($input['layer_height'] ?? null, 0.2);
        $densityRaw = self::jsNumber($material['density'] ?? null);
        $fdmDensity = is_nan($densityRaw) ? 0.0 : $densityRaw;

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
            $bbox = self::scaledBbox($m['bbox_mm'] ?? null, $s);
            $axis = 'y';

            $row = [
                'name' => (string)($m['name'] ?? ('Modelo ' . ($i + 1))),
                'qty' => $qty,
                'scale_factor' => $s,
                'volume_mm3' => $volume,
                'total_area_mm2' => $totalArea,
                'weight_g' => 0.0,
                'hours' => 0.0,
            ];

            // Orientación con menos soporte (si llegan las áreas por eje; si no, la del visor).
            // FDM y SLA usan el mismo eje vertical para la huella y la altura.
            if (isset($m['down_area_mm2']) && is_array($m['down_area_mm2'])) {
                $o = self::chooseOrientation(
                    self::scaleAxes($m['down_area_mm2'], $s * $s),
                    isset($m['bed_contact_mm2']) && is_array($m['bed_contact_mm2']) ? self::scaleAxes($m['bed_contact_mm2'], $s * $s) : null
                );
                $axis = $o['axis'];
                $supportArea = $o['support_area_mm2'];
            }

            if ($isFdm) {
                $infill = (int)(self::jsOr($m['infill'] ?? null, (float)$defaultInfill));
                $layer = self::jsOr($m['layer_height'] ?? null, $defaultLayer);

                $cf = $this->fdmFactors($volume, $totalArea, $supportArea, $fdmDensity, $infill, $layer);
                // Peso y tiempo de UNA pieza sin purga ni preparación (esas van por cama)
                $net = $cf['shell_weight_g'] + $cf['internal_weight_g'] + $cf['support_weight_g'];
                $row['weight_g'] = $net * $waste;
                $row['hours'] = self::fdmPrintHours($net, $fdmDensity, $layer) * $speed;
                $row['infill'] = $infill;
                $row['layer_height'] = $layer;
            } else {
                // SLA: volumen mm³ → cm³
                $volCm3 = $volume / 1000;
                $density = self::jsOr($material['density'] ?? null, 1.1);
                $row['weight_g'] = $volCm3 * $density * 1.1 * $waste;
            }
            // Altura medida por el servidor (bbox), nunca las medidas que manda el cliente
            $row['height_mm'] = $bbox ? $bbox[self::AXES[$axis]] : 0.0;
            $row['support_area_mm2'] = $supportArea;
            $row['orientation'] = $axis;
            $row['footprint'] = self::footprint($bbox, $axis);

            $models[] = $row;
        }

        $totalQty = 0;
        $totalVolume = 0.0;
        foreach ($models as $row) {
            $totalQty += $row['qty'];
            $totalVolume += $row['volume_mm3'] * $row['qty'];
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

        $discountPct = $coupon ? (float)$coupon['discount_pct'] : 0.0;
        $est = self::estimate([
            'technology' => $tech,
            'models' => array_map(fn ($r) => [
                'qty' => $r['qty'],
                'weightG' => $r['weight_g'],
                'hours' => $r['hours'],
                'footprint' => $r['footprint'],
                'heightMm' => $r['height_mm'],
            ], $models),
            'costPerKg' => $costPerKg,
            'infra' => $cfg['infra'],
            'prep' => $cfg['prep'],
            'oper' => $cfg['oper'],
            'margin' => $cfg['margin'],
            'extrasCost' => $utilityCost,
            'discountPct' => $discountPct,
            'bed' => $bed,
            'bedHours' => self::FDM_PREP_H + self::fdmPrintHours(self::FDM_PURGE_G, $fdmDensity, $defaultLayer) * $speed,
            'bedWeightG' => self::FDM_PURGE_G,
            'bedTimeFactor' => $speed,
            'failurePct' => $params['failure_pct'],
            'rules' => $cfg['rules'],
            'urgent' => filter_var($input['urgent'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        $subtotal = $est['grossSubtotal'];

        // Reparto informativo del subtotal (antes de descuento) entre modelos,
        // proporcional a su costo de producción directo. Suma exacta = subtotal.
        $infra = $cfg['infra'];
        $prep = $cfg['prep'];
        $hourRate = $infra['load_factor'] * $infra['luz_hr'] + ($prep['prep_time_pct'] / 100) * $prep['mano_obra_hr'] + $infra['depr_hr'] + $infra['mant_hr'];
        $pieceLabor = ($cfg['rules']['labor_per_piece_min'] / 60) * $prep['mano_obra_hr'];
        $shares = [];
        foreach ($est['models'] as $em) {
            $shares[] = (($em['weightG'] * $em['qty']) / 1000) * $costPerKg + ($em['hours'] * $em['qty']) * $hourRate + $pieceLabor * $em['qty'];
        }
        $shareSum = array_sum($shares);
        $allocated = 0;
        $lastIdx = count($models) - 1;
        $outModels = [];
        foreach ($models as $idx => $row) {
            $em = $est['models'][$idx];
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
                'weight_g' => round($em['weightG'], 2),
                'duration_h' => round($em['hours'], 3),
                'subtotal' => $modelSubtotal,
                'orientation' => $row['orientation'],
                'pieces_per_bed' => $em['piecesPerBed'],
            ];
            if (isset($row['infill'])) {
                $out['infill'] = $row['infill'];
                $out['layer_height'] = $row['layer_height'];
            }
            if (!$isFdm) {
                $out['height_mm'] = round($row['height_mm'], 2);
            }
            // weight_g / duration_h: por pieza, con su parte de purga, preparación o cama
            $out['raw'] = [
                'volume_mm3' => $row['volume_mm3'],
                'total_area_mm2' => $row['total_area_mm2'],
                'support_area_mm2' => $row['support_area_mm2'],
                'weight_g' => $em['weightG'],
                'duration_h' => $em['hours'],
            ];
            $outModels[] = $out;
        }

        $p = $est['production'];
        return [
            'technology' => $tech,
            'models' => $outModels,
            'qty' => $totalQty,
            'weight_g' => $est['weight'],
            'duration_h' => $est['duration'],
            'volume_mm3' => $totalVolume,
            'bed' => $bed,
            'beds' => $est['beds'],
            'pieces_per_bed' => $est['piecesPerBed'],
            'calibration_factor' => $calibration,
            'urgent' => $est['urgent'],
            'production' => [
                'material' => $p['material'],
                'luz' => $p['luz'],
                'labor' => $p['labor'],
                'labor_pieces' => $p['laborPieces'],
                'depr' => $p['depr'],
                'mant' => $p['mant'],
                'etiquetas' => $p['etiquetas'],
                'extras' => $utilityCost,
                'total' => $p['total'],
            ],
            'extras' => $extras,
            'extras_cost' => $utilityCost,
            'unit_price' => (int)$est['unitPrice'],
            'margin' => (int)$est['marginCost'],
            'subtotal' => (int)$subtotal,
            'adjustments' => $est['adjustments'],
            'volume_discount' => (int)$est['volumeDiscount'],
            'coupon' => $coupon ? [
                'code' => $coupon['code'],
                'discount_pct' => $discountPct,
                'label' => $coupon['label'] ?? null,
            ] : null,
            'discount' => (int)$est['discount'],
            'subtotal_after_discount' => (int)$est['subtotal'],
            'iva_pct' => $est['ivaPct'],
            'iva' => (int)$est['iva'],
            'total' => (int)$est['total'],
        ];
    }

    /**
     * Núcleo del precio: espejo EXACTO de calcQuoteEstimate (frontend/src/services/costCalculator.ts),
     * con las mismas claves de entrada y salida. Las dos versiones se prueban con
     * tests/Fixtures/quote_core_vectors.json.
     *
     * Sin `technology` es el cálculo histórico (cada pieza es un trabajo aparte). Con
     * `technology` las piezas comparten cama:
     *   - FDM: `hours`/`weightG` son de impresión por pieza; preparación y purga
     *     (`bedHours`, `bedWeightG`) se cobran una vez por cama.
     *   - SLA: cada cama tarda max(0.5, pieza más alta / 25) × `bedTimeFactor`.
     */
    public static function estimate(array $in): array
    {
        $tech = $in['technology'] ?? null;
        $tech = ($tech === 'FDM' || $tech === 'SLA') ? $tech : null;

        $models = [];
        foreach (array_values($in['models'] ?? []) as $m) {
            $q = self::jsNumber($m['qty'] ?? null);
            $q = is_nan($q) ? 0 : (int)floor($q);
            $models[] = [
                'qty' => max(1, $q ?: 1),
                'weightG' => self::jsOr($m['weightG'] ?? null, 0.0),
                'hours' => self::jsOr($m['hours'] ?? null, 0.0),
                'footprint' => isset($m['footprint']) && is_array($m['footprint']) ? $m['footprint'] : null,
                'heightMm' => self::jsOr($m['heightMm'] ?? null, 0.0),
            ];
        }
        $n = count($models);
        $pieces = 0;
        $weight = 0.0;
        $duration = 0.0;
        foreach ($models as $m) {
            $pieces += $m['qty'];
            $weight += $m['weightG'] * $m['qty'];
            $duration += $m['hours'] * $m['qty'];
        }

        // Camas: cuántas piezas caben y en qué cama va cada una
        $ppb = array_fill(0, $n, 1);
        $beds = [];
        if ($tech !== null) {
            $parts = array_fill(0, $n, 1);
            $zParts = array_fill(0, $n, 1);
            foreach ($models as $i => $m) {
                $ppb[$i] = self::piecesPerBed($m['footprint'], $in['bed'] ?? null);
                $parts[$i] = self::bedsPerPiece($m['footprint'], $m['heightMm'], $in['bed'] ?? null);
                $zParts[$i] = self::bedsPerPiece(null, $m['heightMm'], $in['bed'] ?? null);
            }
            $beds = self::packBeds($models, $ppb, $parts, $zParts);
        }
        $bedCount = $tech !== null ? count($beds) : $pieces;
        $maxPerBed = 1;
        foreach ($beds as $b) $maxPerBed = max($maxPerBed, $b['pieces']);

        $unitW = array_map(fn ($m) => $m['weightG'], $models);
        $unitH = array_map(fn ($m) => $m['hours'], $models);
        if ($tech === 'FDM' && $pieces > 0) {
            $bedW = self::jsOr($in['bedWeightG'] ?? null, 0.0);
            $bedH = self::jsOr($in['bedHours'] ?? null, 0.0);
            $weight += $bedW * $bedCount;
            $duration += $bedH * $bedCount;
            foreach ($models as $i => $m) {
                $unitW[$i] = $m['weightG'] + ($bedW * $bedCount) / $pieces;
                $unitH[$i] = $m['hours'] + ($bedH * $bedCount) / $pieces;
            }
        } elseif ($tech === 'SLA') {
            $f = self::jsOr($in['bedTimeFactor'] ?? null, 1.0);
            $duration = 0.0;
            $share = array_fill(0, $n, 0.0);
            foreach ($beds as $b) {
                $t = max(0.5, $b['height'] / 25) * $f;
                $duration += $t;
                foreach ($b['items'] as $i => $c) {
                    if ($c > 0) $share[$i] += $t * $c / $b['pieces'];
                }
            }
            foreach ($models as $i => $m) {
                $unitH[$i] = $share[$i] / $m['qty'];
            }
        }

        // Costo de producción (calcProductionCost) + mano de obra por pieza
        $infra = $in['infra'] ?? [];
        $prep = $in['prep'] ?? [];
        $oper = $in['oper'] ?? [];
        $rules = $in['rules'] ?? self::normalizeRules(null);
        $loadFactor = $infra['load_factor'] ?? 0.4;
        $prepTimePct = ($prep['prep_time_pct'] ?? 10) / 100;
        $manoObra = $prep['mano_obra_hr'] ?? 0;
        $costPerKg = (float)($in['costPerKg'] ?? 0);
        $extrasCost = (float)($in['extrasCost'] ?? 0);

        $matCost = ($weight / 1000) * $costPerKg;
        $luz = $duration * $loadFactor * ($infra['luz_hr'] ?? 0);
        $labor = ($duration * $prepTimePct) * $manoObra;
        $depr = $duration * ($infra['depr_hr'] ?? 0);
        $mant = $duration * ($infra['mant_hr'] ?? 0);
        $etiquetas = $infra['etiquetas'] ?? 0;
        $laborPieces = $pieces * ($rules['labor_per_piece_min'] / 60) * $manoObra;
        $production = $matCost + $luz + $labor + $depr + $mant + $etiquetas + $extrasCost + $laborPieces;

        // calcFinalPrice sobre el costo unitario (la reserva por fallos puede venir del material)
        $unitCost = $pieces > 0 ? $production / $pieces : 0;
        $failPct = $in['failurePct'] ?? null;
        $failPct = $failPct ?? ($oper['fallos'] ?? 0);
        $logistics = $unitCost * (($oper['transporte'] ?? 0) / 100);
        $marketing = $unitCost * (($oper['marketing'] ?? 0) / 100);
        $failures = $unitCost * ($failPct / 100);
        $profit = $unitCost * (($oper['ganancia'] ?? 0) / 100);
        $unitPrice = self::jsRound($unitCost + $logistics + $marketing + $failures + $profit);

        $adjustments = [];
        $urgent = !empty($in['urgent']) && $rules['rush_pct'] > 0;
        if ($urgent) {
            $withRush = self::jsRound($unitPrice * (1 + $rules['rush_pct'] / 100));
            $adjustments[] = self::adjustment('rush', 'Urgente', $rules['rush_pct'], ($withRush - $unitPrice) * $pieces);
            $unitPrice = $withRush;
        }
        if ($rules['round_to'] > 0) {
            $rounded = ceil($unitPrice / $rules['round_to']) * $rules['round_to'];
            if ($rounded != $unitPrice) {
                $adjustments[] = self::adjustment('rounding', 'Redondeo', null, ($rounded - $unitPrice) * $pieces);
            }
            $unitPrice = $rounded;
        }
        $subtotal = $unitPrice * $pieces;

        // Descuento por volumen (antes del cupón); juntos no pasan de MAX_COUPON_PCT
        $volumePct = 0;
        foreach ($rules['volume_tiers'] as $t) {
            if ($pieces >= $t['min_qty']) $volumePct = $t['discount_pct'];
        }
        $volumeAmount = $volumePct > 0 ? $subtotal * ($volumePct / 100) : 0;
        $couponPct = self::jsOr($in['discountPct'] ?? null, 0.0);
        $couponAmount = $couponPct ? ($subtotal - $volumeAmount) * ($couponPct / 100) : 0;
        $maxOff = $subtotal * (self::MAX_COUPON_PCT / 100);
        if ($volumeAmount + $couponAmount > $maxOff) {
            $couponAmount = max(0, $maxOff - $volumeAmount);
        }
        if ($volumePct > 0) {
            $adjustments[] = self::adjustment('volume', 'Descuento por volumen', -$volumePct, -self::jsRound($volumeAmount));
        }
        $finalSubtotal = self::jsRound($subtotal - $volumeAmount - $couponAmount);

        $minTotal = self::jsRound($rules['min_order_total']);
        if ($minTotal > 0 && $finalSubtotal < $minTotal) {
            $adjustments[] = self::adjustment('min_order', 'Pedido mínimo', null, $minTotal - $finalSubtotal);
            $finalSubtotal = $minTotal;
        }

        $ivaPct = $in['margin']['iva'] ?? 19;
        $iva = self::jsRound($finalSubtotal * ($ivaPct / 100));
        $total = $finalSubtotal + $iva;

        $outModels = [];
        foreach ($models as $i => $m) {
            $outModels[] = ['qty' => $m['qty'], 'piecesPerBed' => $ppb[$i], 'weightG' => $unitW[$i], 'hours' => $unitH[$i]];
        }

        return [
            'pieces' => $pieces,
            'weight' => $weight,
            'duration' => $duration,
            'beds' => $bedCount,
            'piecesPerBed' => $maxPerBed,
            'matCost' => $matCost,
            'infraCost' => $luz + $depr + $mant,
            'laborCost' => $labor + $laborPieces,
            'utilityCost' => $extrasCost,
            'productionCost' => $production,
            'marginCost' => (self::jsRound($logistics) + self::jsRound($marketing) + self::jsRound($failures) + self::jsRound($profit)) * $pieces,
            'unitPrice' => $unitPrice,
            'grossSubtotal' => $subtotal,
            'volumeDiscount' => self::jsRound($volumeAmount),
            'discount' => self::jsRound($couponAmount),
            'subtotal' => $finalSubtotal,
            'iva' => $iva,
            'ivaPct' => $ivaPct,
            'total' => $total,
            'urgent' => $urgent,
            'adjustments' => $adjustments,
            'models' => $outModels,
            'production' => [
                'material' => $matCost, 'luz' => $luz, 'labor' => $labor, 'laborPieces' => $laborPieces,
                'depr' => $depr, 'mant' => $mant, 'etiquetas' => $etiquetas, 'total' => $production,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Camas y orientación (espejo de costCalculator.ts)
    // ------------------------------------------------------------------

    /** Horas de impresión FDM de $weightG gramos (mismo flujo volumétrico que fdmFactors). */
    public static function fdmPrintHours(float $weightG, float $density, float $layerHeight): float
    {
        if ($density <= 0) $density = 1.25;
        if ($layerHeight <= 0) $layerHeight = 0.2;
        return ((($weightG / $density) * 1000) / 15500) * (0.2 / $layerHeight);
    }

    /**
     * Eje vertical con menos soporte: área hacia abajo menos la que apoya en la cama.
     * Gana 'y' (la orientación del visor) salvo que otro eje ahorre más de 1 mm².
     *
     * @return array{axis: string, support_area_mm2: float}
     */
    public static function chooseOrientation(array $down, ?array $contact = null): array
    {
        $support = static fn (string $k) => max(0.0, self::jsOr($down[$k] ?? null, 0.0) - self::jsOr($contact[$k] ?? null, 0.0));
        $best = 'y';
        $bestArea = $support('y');
        foreach (['z', 'x'] as $k) {
            $a = $support($k);
            if ($a < $bestArea - 1.0) {
                $best = $k;
                $bestArea = $a;
            }
        }
        return ['axis' => $best, 'support_area_mm2' => $bestArea];
    }

    /** Huella en la cama {x, y} con $axis vertical (los otros dos lados del bbox escalado). */
    public static function footprint(?array $bbox, string $axis): ?array
    {
        if ($bbox === null) return null;
        $rest = array_values(array_diff_key($bbox, [self::AXES[$axis] => true]));
        return ['x' => $rest[0], 'y' => $rest[1]];
    }

    /** Piezas de esa huella que caben en una cama (separación + factor de aprovechamiento). */
    public static function piecesPerBed(?array $footprint, ?array $bed): int
    {
        $bx = self::jsOr($bed['x'] ?? null, 0.0);
        $by = self::jsOr($bed['y'] ?? null, 0.0);
        $fx = self::jsOr($footprint['x'] ?? null, 0.0);
        $fy = self::jsOr($footprint['y'] ?? null, 0.0);
        if ($bx <= 0 || $by <= 0 || $fx <= 0 || $fy <= 0) return 1;
        // Una pieza más grande que la cama ocupa una cama entera (ver bedsPerPiece)
        if (!(($fx <= $bx && $fy <= $by) || ($fx <= $by && $fy <= $bx))) return 1;
        $sp = self::BED_SPACING_MM;
        $area = floor(($bx * $by * self::BED_PACKING) / (($fx + $sp) * ($fy + $sp)));
        // Por área no basta: también deben caber en filas (con la pieza girada o no)
        $grid = max(
            floor(($bx + $sp) / ($fx + $sp)) * floor(($by + $sp) / ($fy + $sp)),
            floor(($bx + $sp) / ($fy + $sp)) * floor(($by + $sp) / ($fx + $sp))
        );
        return max(1, (int)min($area, $grid));
    }

    /**
     * Camas que ocupa UNA pieza: 1 si cabe; si no, se imprime en partes y cada eje que
     * se pasa de la cama multiplica por ceil(lado / cama) (la huella puede girarse).
     */
    public static function bedsPerPiece(?array $footprint, float $heightMm, ?array $bed): int
    {
        $bx = self::jsOr($bed['x'] ?? null, 0.0);
        $by = self::jsOr($bed['y'] ?? null, 0.0);
        $bz = self::jsOr($bed['z'] ?? null, 0.0);
        $fx = self::jsOr($footprint['x'] ?? null, 0.0);
        $fy = self::jsOr($footprint['y'] ?? null, 0.0);
        $xy = 1;
        if ($bx > 0 && $by > 0 && $fx > 0 && $fy > 0) {
            $xy = (int)min(ceil($fx / $bx) * ceil($fy / $by), ceil($fx / $by) * ceil($fy / $bx));
        }
        $z = ($bz > 0 && $heightMm > $bz) ? (int)ceil($heightMm / $bz) : 1;
        return max(1, $xy) * $z;
    }

    /**
     * Reparte las piezas en camas: de la más alta a la más baja, llenando cada cama
     * (cada pieza ocupa 1/piezas_por_cama de la suya). Una pieza que no cabe ocupa
     * $parts camas enteras, cada una con la altura de su parte.
     *
     * @return array<int, array{height: float, pieces: int, used: float, items: int[]}>
     */
    private static function packBeds(array $models, array $ppb, array $parts, array $zParts): array
    {
        $n = count($models);
        $order = array_keys($models);
        usort($order, fn ($a, $b) => ($models[$b]['heightMm'] <=> $models[$a]['heightMm']) ?: ($a <=> $b));
        $beds = [];
        $cur = -1;
        foreach ($order as $i) {
            if ($parts[$i] > 1) {
                $items = array_fill(0, $n, 0);
                $items[$i] = 1;
                for ($p = 0; $p < $models[$i]['qty'] * $parts[$i]; $p++) {
                    $beds[] = ['height' => $models[$i]['heightMm'] / $zParts[$i], 'pieces' => 1, 'used' => 1.0, 'items' => $items];
                    $cur++;
                }
                continue;
            }
            $need = 1 / $ppb[$i];
            for ($p = 0; $p < $models[$i]['qty']; $p++) {
                if ($cur < 0 || $beds[$cur]['used'] + $need > 1 + 1e-9) {
                    $beds[] = ['height' => $models[$i]['heightMm'], 'pieces' => 0, 'used' => 0.0, 'items' => array_fill(0, $n, 0)];
                    $cur++;
                }
                $beds[$cur]['used'] += $need;
                $beds[$cur]['pieces']++;
                $beds[$cur]['items'][$i]++;
            }
        }
        return $beds;
    }

    private static function adjustment(string $type, string $label, int|float|null $pct, int|float $amount): array
    {
        return ['type' => $type, 'label' => $label, 'pct' => $pct, 'amount' => (int)$amount];
    }

    /** [x, y, z] del archivo × escala, o null si no hay bbox válido. */
    private static function scaledBbox(mixed $bbox, float $scale): ?array
    {
        if (!is_array($bbox) || count($bbox) < 3) return null;
        $b = array_values($bbox);
        return [(float)$b[0] * $scale, (float)$b[1] * $scale, (float)$b[2] * $scale];
    }

    private static function scaleAxes(array $v, float $f): array
    {
        $out = [];
        foreach (['x', 'y', 'z'] as $k) {
            $out[$k] = self::jsOr($v[$k] ?? null, 0.0) * $f;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Cupones
    // ------------------------------------------------------------------

    /**
     * Busca un cupón activo (case-insensitive) en la lista de settings `coupons`.
     *
     * @return array{code: string, discount_pct: float, label: string|null}|null
     */
    /**
     * @param array $uses Usos por código (setting `coupon_uses`, solo lo escribe el servidor)
     */
    public static function findCoupon(?string $code, array $coupons, array $uses = []): ?array
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
            // Tope: ningún cupón deja un pedido a menos de la mitad del precio
            $pct = min($pct, self::MAX_COUPON_PCT);
            $max = (int) ($c['max_uses'] ?? 0);
            if ($max > 0 && (int) ($uses[$code] ?? 0) >= $max) continue;
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

    /**
     * Cama de cada tecnología: la impresora no apagada de mayor volumen con medidas
     * (columnas build_*_mm); si ninguna las tiene, settings `beds`.
     */
    public function loadBeds(?array $settings = null): array
    {
        $beds = self::normalizeBeds(($settings ?? $this->loadSettings())['beds'] ?? null);
        if (!Schema::hasColumn('printers', 'build_x_mm')) return $beds;
        $printers = Printer::query()
            ->where('status', '!=', 'offline')
            ->whereNotNull('build_x_mm')->whereNotNull('build_y_mm')->whereNotNull('build_z_mm')
            ->get(['technology', 'build_x_mm', 'build_y_mm', 'build_z_mm']);
        foreach (array_keys($beds) as $tech) {
            $best = $printers
                ->filter(fn ($p) => strtoupper((string)$p->technology) === $tech && $p->build_x_mm > 0 && $p->build_y_mm > 0 && $p->build_z_mm > 0)
                ->sortByDesc(fn ($p) => $p->build_x_mm * $p->build_y_mm * $p->build_z_mm)
                ->first();
            if ($best) {
                $beds[$tech] = ['x' => (float)$best->build_x_mm, 'y' => (float)$best->build_y_mm, 'z' => (float)$best->build_z_mm];
            }
        }
        return $beds;
    }

    /**
     * Modelo para quote() a partir del análisis de MeshVolumeService (lo usa el
     * controlador; así las pruebas cotizan con exactamente los mismos datos).
     */
    public static function pricingModelFromMesh(array $mesh, array $meta = []): array
    {
        return [
            'qty' => $meta['qty'] ?? 1,
            'scale_factor' => $meta['scale_factor'] ?? 1,
            'volume_mm3' => $mesh['volume_mm3'],
            'total_area_mm2' => $mesh['total_area_mm2'],
            // Orientación del visor (normales hacia -Y) si no llegan las áreas por eje
            'support_area_mm2' => $mesh['down_area_mm2']['y'],
            'down_area_mm2' => $mesh['down_area_mm2'],
            'bed_contact_mm2' => $mesh['bed_contact_mm2'] ?? null,
            'bbox_mm' => $mesh['bbox']['size'],
            'dimensions_mm' => $meta['dimensions_mm'] ?? null,
            'infill' => $meta['infill'] ?? null,
            'layer_height' => $meta['layer_height'] ?? null,
        ];
    }

    public function findActiveCoupon(?string $code, ?array $settings = null): ?array
    {
        $settings ??= $this->loadSettings();
        $coupons = $settings['coupons'] ?? [];
        $uses = $settings['coupon_uses'] ?? [];
        return self::findCoupon($code, is_array($coupons) ? $coupons : [], is_array($uses) ? $uses : []);
    }

    /** Suma un uso al cupón (fila bloqueada: dos pedidos a la vez no pisan el contador). */
    public function recordCouponUse(string $code): void
    {
        $code = strtoupper(trim($code));
        // La fila debe existir antes de bloquearla: en el primer uso, dos pedidos a la vez
        // chocaban al crearla (clave única) y uno respondía 500 con el pedido ya hecho
        Setting::query()->insertOrIgnore(['key' => 'coupon_uses', 'value' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::transaction(function () use ($code) {
            $row = Setting::where('key', 'coupon_uses')->lockForUpdate()->first();
            $uses = $row && is_array($row->value) ? $row->value : [];
            $uses[$code] = (int) ($uses[$code] ?? 0) + 1;
            Setting::updateOrCreate(['key' => 'coupon_uses'], ['value' => $uses]);
        });
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
            'beds' => self::normalizeBeds($settings['beds'] ?? null),
            'rules' => self::normalizeRules($settings['rules'] ?? null),
            'calibration' => self::normalizeCalibration($settings['calibration'] ?? null),
        ];
    }

    /** Número finito (número o texto numérico) o null; igual que finiteNum de costCalculator.ts. */
    public static function finiteNum(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) return is_finite((float)$v) ? (float)$v : null;
        if (is_string($v) && trim($v) !== '' && is_numeric(trim($v))) {
            $n = (float)trim($v);
            return is_finite($n) ? $n : null; // "1e400" → INF, igual que Number() → null
        }
        return null;
    }

    /** Objeto JSON (no lista), como isObj de costCalculator.ts; [] vacío cuenta como objeto. */
    private static function isObj(mixed $v): bool
    {
        return is_array($v) && ($v === [] || !array_is_list($v));
    }

    /** Camas por tecnología (settings `beds`): cada medida > 0 o la de DEFAULT_BEDS. */
    public static function normalizeBeds(mixed $raw): array
    {
        $out = [];
        foreach (self::DEFAULT_BEDS as $tech => $default) {
            $out[$tech] = self::normalizeBed(self::isObj($raw) ? ($raw[$tech] ?? null) : null, $default);
        }
        return $out;
    }

    public static function normalizeBed(mixed $raw, array $default): array
    {
        $out = [];
        foreach (['x', 'y', 'z'] as $k) {
            $v = self::isObj($raw) ? self::finiteNum($raw[$k] ?? null) : null;
            $out[$k] = $v !== null && $v > 0 ? $v : (float)$default[$k];
        }
        return $out;
    }

    /**
     * Reglas comerciales (settings `rules`). Sin configurar no cambian el precio.
     *
     * @return array{min_order_total: float, volume_tiers: array<int, array{min_qty: int, discount_pct: float}>, round_to: float, labor_per_piece_min: float, rush_pct: float}
     */
    public static function normalizeRules(mixed $raw): array
    {
        $r = self::isObj($raw) ? $raw : [];
        $pos = static function (mixed $v, float $max = INF): float {
            $n = self::finiteNum($v);
            return $n !== null && $n > 0 ? min($n, $max) : 0.0;
        };
        $tiers = [];
        foreach ((isset($r['volume_tiers']) && is_array($r['volume_tiers']) && array_is_list($r['volume_tiers']) ? $r['volume_tiers'] : []) as $t) {
            if (!self::isObj($t)) continue;
            $min = self::finiteNum($t['min_qty'] ?? null);
            $pct = self::finiteNum($t['discount_pct'] ?? null);
            if ($min === null || $pct === null || floor($min) < 1 || $pct <= 0) continue;
            $tiers[] = ['min_qty' => (int)floor($min), 'discount_pct' => min($pct, (float)self::MAX_COUPON_PCT)];
        }
        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);
        return [
            'min_order_total' => $pos($r['min_order_total'] ?? null),
            'volume_tiers' => $tiers,
            'round_to' => $pos($r['round_to'] ?? null),
            'labor_per_piece_min' => $pos($r['labor_per_piece_min'] ?? null),
            'rush_pct' => $pos($r['rush_pct'] ?? null, 200.0),
        ];
    }

    /**
     * Calibración del tiempo (settings `calibration`). Solo valen factores entre 0.3 y 3:
     * por tecnología, si no, 1; por material, si no, se ignora (cae al de la tecnología).
     */
    public static function normalizeCalibration(mixed $raw): array
    {
        $ok = static function (mixed $v): ?float {
            $n = self::finiteNum($v);
            return $n !== null && $n >= 0.3 && $n <= 3 ? $n : null;
        };
        $raw = self::isObj($raw) ? $raw : [];
        $tf = self::isObj($raw['time_factor'] ?? null) ? $raw['time_factor'] : [];
        $byMaterial = [];
        foreach ((self::isObj($raw['by_material'] ?? null) ? $raw['by_material'] : []) as $id => $v) {
            $f = $ok($v);
            if ($f !== null) $byMaterial[(string)$id] = $f;
        }
        return [
            'time_factor' => ['FDM' => $ok($tf['FDM'] ?? null) ?? 1.0, 'SLA' => $ok($tf['SLA'] ?? null) ?? 1.0],
            'by_material' => $byMaterial,
        ];
    }

    /** by_material[id] ?? time_factor[tecnología] ?? 1 (sobre la calibración normalizada). */
    public static function calibrationFactor(array $calibration, string $technology, string $materialId): float
    {
        return $calibration['by_material'][$materialId] ?? $calibration['time_factor'][$technology] ?? 1.0;
    }

    /**
     * Parámetros de cotización del material: velocidad (× tiempo), desperdicio (% extra de
     * material) y reserva por fallos propia (null = la global oper.fallos).
     *
     * @return array{speed_factor: float, waste_pct: float, failure_pct: float|null}
     */
    public static function materialParams(array $material): array
    {
        $speed = self::finiteNum($material['speed_factor'] ?? null);
        $waste = self::finiteNum($material['waste_pct'] ?? null);
        $fail = self::finiteNum($material['failure_pct'] ?? null);
        return [
            'speed_factor' => $speed !== null && $speed > 0 ? min($speed, 10.0) : 1.0,
            'waste_pct' => $waste !== null && $waste > 0 ? min($waste, 100.0) : 0.0,
            'failure_pct' => $fail !== null && $fail >= 0 ? min($fail, 100.0) : null,
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

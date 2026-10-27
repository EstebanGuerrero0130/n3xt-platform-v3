<?php

namespace Tests\Unit;

use App\Services\CostCalculatorService;
use App\Services\QuotePricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Compara QuotePricingService (PHP) con los vectores generados replicando el
 * cálculo del frontend (tests/Fixtures/pricing_vectors.json).
 *
 * Los casos con varias piezas (los que tienen `note`) cambiaron a propósito: ahora las
 * piezas comparten cama, así que la preparación y la purga FDM se cobran por cama y el
 * tiempo SLA es el de cada cama (pieza más alta / 25), no el de cada pieza. Los casos de
 * una sola pieza siguen dando lo mismo que antes. Los casos SLA también cambiaron: la
 * altura usa el mismo eje vertical que la huella (Y del visor si no hay áreas por eje).
 * La paridad del núcleo con el frontend
 * se prueba en QuoteCoreParityTest.
 */
class QuotePricingServiceTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../Fixtures/pricing_vectors.json';

    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function vectorProvider(): array
    {
        $out = [];
        foreach (self::fixture()['cases'] as $case) {
            $out[$case['name']] = [$case];
        }
        return $out;
    }

    private function service(): QuotePricingService
    {
        return new QuotePricingService(new CostCalculatorService());
    }

    #[DataProvider('vectorProvider')]
    public function test_matches_frontend_vectors(array $case): void
    {
        $utilities = [];
        foreach (self::fixture()['utilities'] as $u) {
            $utilities[$u['id']] = $u;
        }

        $in = $case['input'];
        $result = $this->service()->quote(
            [
                'technology' => $in['technology'],
                'infill' => $in['infill'],
                'layer_height' => $in['layer_height'],
                'models' => $in['models'],
                'extras' => $in['extras'],
            ],
            $in['settings'],
            $in['material'],
            $utilities,
            $in['coupon']
        );
        $exp = $case['expected'];

        foreach (['total', 'iva', 'discount', 'subtotal', 'subtotal_after_discount', 'unit_price'] as $key) {
            $this->assertEqualsWithDelta($exp[$key], $result[$key], 1, "{$case['name']}: $key");
        }
        $this->assertEqualsWithDelta($exp['iva_pct'], $result['iva_pct'], 1e-9, "{$case['name']}: iva_pct");
        $this->assertEqualsWithDelta($exp['production_cost'], $result['production']['total'], 0.01, "{$case['name']}: production");
        $this->assertEqualsWithDelta($exp['extras_cost'], $result['extras_cost'], 0.01, "{$case['name']}: extras");
        $this->assertEqualsWithDelta($exp['weight_g'], $result['weight_g'], 1e-6, "{$case['name']}: weight");
        $this->assertEqualsWithDelta($exp['duration_h'], $result['duration_h'], 1e-6, "{$case['name']}: duration");

        if (isset($exp['beds'])) {
            $this->assertSame($exp['beds'], $result['beds'], "{$case['name']}: beds");
        }

        $this->assertCount(count($exp['models']), $result['models']);
        $allocated = 0;
        foreach ($exp['models'] as $i => $m) {
            $this->assertEqualsWithDelta($m['weight_g'], $result['models'][$i]['raw']['weight_g'], 1e-6);
            $this->assertEqualsWithDelta($m['duration_h'], $result['models'][$i]['raw']['duration_h'], 1e-6);
            $allocated += $result['models'][$i]['subtotal'];
        }
        // El reparto por modelo suma exactamente el subtotal antes de descuento
        $this->assertSame($result['subtotal'], $allocated);
        $this->assertSame($result['subtotal_after_discount'] + $result['iva'], $result['total']);
    }

    public function test_fdm_factors_match_legacy_simulator_shape(): void
    {
        $f = $this->service()->fdmFactors(8000, 2400, 400, 1.24, 15, 0.2);
        $this->assertSame(
            ['resolution', 'infill_density', 'shell_weight_g', 'internal_weight_g', 'support_weight_g', 'purge_weight_g', 'filament_length_m', 'prep_time_h', 'print_time_h'],
            array_keys($f)
        );
        // shell: área*0.8 = 1920 mm³ → 2.3808 g
        $this->assertEqualsWithDelta(1920 / 1000 * 1.24, $f['shell_weight_g'], 1e-12);
        $this->assertEqualsWithDelta((8000 - 1920) / 1000 * 0.15 * 1.24, $f['internal_weight_g'], 1e-12);
        // Guardas: densidad 0 → 1.25, capa 0 → 0.2
        $g = $this->service()->fdmFactors(8000, 2400, 400, 0, 15, 0);
        $this->assertEqualsWithDelta(1920 / 1000 * 1.25, $g['shell_weight_g'], 1e-12);
        $this->assertEqualsWithDelta(1.0, $g['resolution'], 1e-12);
    }

    public function test_iva_zero_is_respected_and_missing_iva_defaults_to_19(): void
    {
        $this->assertSame(0.0, QuotePricingService::normalizeSettings(['margin' => ['iva' => 0]])['margin']['iva']);
        $this->assertSame(0.0, QuotePricingService::normalizeSettings(['margin' => ['iva' => '0']])['margin']['iva']);
        $this->assertSame(19.0, QuotePricingService::normalizeSettings(['margin' => ['iva' => '']])['margin']['iva']);
        $this->assertSame(19.0, QuotePricingService::normalizeSettings([])['margin']['iva']);
        // `Number(x) || def`: load_factor 0 cae a 0.4 (igual que el frontend)
        $this->assertSame(0.4, QuotePricingService::normalizeSettings(['infra' => ['load_factor' => 0]])['infra']['load_factor']);
    }

    public function test_find_coupon(): void
    {
        $coupons = [
            ['code' => 'N3XT10', 'discount_pct' => 10, 'label' => 'Cupón Bienvenida', 'active' => true],
            ['code' => 'OFF', 'discount_pct' => 50, 'label' => 'Inactivo', 'active' => false],
            ['code' => 'BAD', 'discount_pct' => 150, 'label' => 'Inválido'],
        ];
        $c = QuotePricingService::findCoupon('  n3xt10 ', $coupons);
        $this->assertNotNull($c);
        $this->assertSame('N3XT10', $c['code']);
        $this->assertSame(10.0, $c['discount_pct']);
        $this->assertNull(QuotePricingService::findCoupon('OFF', $coupons));
        $this->assertNull(QuotePricingService::findCoupon('BAD', $coupons));
        $this->assertNull(QuotePricingService::findCoupon('', $coupons));
        $this->assertNull(QuotePricingService::findCoupon('NOPE', $coupons));
    }

    public function test_per_model_qty_and_sla_height_from_bbox(): void
    {
        $svc = $this->service();
        $base = ['volume_mm3' => 8000, 'total_area_mm2' => 2400, 'support_area_mm2' => 400, 'bbox_mm' => [20, 20, 20], 'scale_factor' => 1];
        $r = $svc->quote(
            ['technology' => 'FDM', 'infill' => 15, 'layer_height' => 0.2, 'models' => [
                $base + ['name' => 'a', 'qty' => 1],
                $base + ['name' => 'b', 'qty' => 3],
            ]],
            ['oper' => ['ganancia' => 50]],
            ['cost_per_kg' => 85000, 'density' => 1.24],
            []
        );
        $this->assertSame(4, $r['qty']);
        $this->assertEqualsWithDelta($r['models'][0]['raw']['weight_g'] * 4, $r['weight_g'], 1e-9);

        // SLA: la altura sale del bbox medido por el servidor, no de dimensions_mm del cliente
        foreach (['20x20x9999', '1x1x1'] as $dims) {
            $sla = $svc->quote(
                ['technology' => 'SLA', 'models' => [$base + ['name' => 'r', 'qty' => 1, 'dimensions_mm' => $dims]]],
                [],
                ['cost_per_kg' => 250000, 'density' => 1.1],
                []
            );
            $this->assertEqualsWithDelta(20, $sla['models'][0]['height_mm'], 1e-9, $dims);
        }
    }

    public function test_sla_uses_one_vertical_axis_for_footprint_and_height(): void
    {
        // Placa 100×100×2 (Y vertical en el visor): de pie, huella 100×2 y 100 mm de alto
        $plate = [
            'name' => 'placa', 'qty' => 25, 'scale_factor' => 1,
            'volume_mm3' => 20000, 'total_area_mm2' => 20800, 'support_area_mm2' => 200,
            'bbox_mm' => [100, 100, 2], 'dimensions_mm' => '100.0 x 100.0 x 2.0',
            'down_area_mm2' => ['x' => 200, 'y' => 200, 'z' => 10000],
            'bed_contact_mm2' => ['x' => 200, 'y' => 200, 'z' => 10000],
        ];
        $r = $this->service()->quote(
            ['technology' => 'SLA', 'bed' => ['x' => 218, 'y' => 123, 'z' => 250], 'models' => [$plate]],
            [],
            ['id' => 'RES', 'cost_per_kg' => 250000, 'density' => 1.1],
            []
        );
        $this->assertSame('y', $r['models'][0]['orientation']);
        $this->assertEqualsWithDelta(100, $r['models'][0]['height_mm'], 1e-9);
        $this->assertSame(1, $r['beds']);
        $this->assertSame(25, $r['pieces_per_bed']);
        $this->assertEqualsWithDelta(4.0, $r['duration_h'], 1e-9);

        // Si conviene acostarla (Z vertical), huella y altura cambian juntas
        $flat = $plate + [];
        $flat['down_area_mm2'] = ['x' => 200, 'y' => 5000, 'z' => 10000];
        $flat['bed_contact_mm2'] = ['x' => 0, 'y' => 0, 'z' => 10000];
        $f = $this->service()->quote(
            ['technology' => 'SLA', 'bed' => ['x' => 218, 'y' => 123, 'z' => 250], 'models' => [$flat]],
            [],
            ['id' => 'RES', 'cost_per_kg' => 250000, 'density' => 1.1],
            []
        );
        $this->assertSame('z', $f['models'][0]['orientation']);
        $this->assertEqualsWithDelta(2, $f['models'][0]['height_mm'], 1e-9);
        $this->assertSame(1, $f['pieces_per_bed']);
        $this->assertSame(25, $f['beds']);
    }

    public function test_bed_packing_by_rows_and_oversize_parts(): void
    {
        // 130×130 en 256×256: por área serían 2, en filas cabe 1
        $this->assertSame(1, QuotePricingService::piecesPerBed(['x' => 130, 'y' => 130], ['x' => 256, 'y' => 256]));
        $two = $this->fdmQuote([$this->cube(['qty' => 2, 'bbox_mm' => [130, 20, 130]])]);
        $this->assertSame(2, $two['beds']);

        // Cubo de 400 mm en cama de 256: 2×2×2 partes, una cama cada una
        $big = $this->fdmQuote([$this->cube(['bbox_mm' => [400, 400, 400]])]);
        $this->assertSame(8, $big['beds']);
        $this->assertSame(1, $big['pieces_per_bed']);
    }

    public function test_finite_num_and_json_lists_like_frontend(): void
    {
        $this->assertNull(QuotePricingService::finiteNum('1e400'));
        $this->assertSame(12.5, QuotePricingService::finiteNum(' 12.5 '));
        // Una lista JSON no es un objeto de settings (isObj del frontend)
        $this->assertSame(0.0, QuotePricingService::normalizeRules([['min_order_total' => 5000]])['min_order_total']);
        $this->assertSame([], QuotePricingService::normalizeRules(['volume_tiers' => ['a' => ['min_qty' => 2, 'discount_pct' => 5]]])['volume_tiers']);
        $cal = QuotePricingService::normalizeCalibration(['time_factor' => [1.5], 'by_material' => [1.2]]);
        $this->assertSame(['FDM' => 1.0, 'SLA' => 1.0], $cal['time_factor']);
        $this->assertSame([], $cal['by_material']);
        // Sin recargo infinito por un texto fuera de rango
        $this->assertSame(0.0, QuotePricingService::normalizeRules(['rush_pct' => '1e400'])['rush_pct']);
    }

    private function cube(array $extra = []): array
    {
        return $extra + ['volume_mm3' => 8000, 'total_area_mm2' => 2400, 'support_area_mm2' => 400, 'bbox_mm' => [20, 20, 20], 'scale_factor' => 1, 'qty' => 1];
    }

    private function fdmQuote(array $models, array $settings = [], array $material = [], array $input = []): array
    {
        return $this->service()->quote(
            $input + ['technology' => 'FDM', 'infill' => 15, 'layer_height' => 0.2, 'models' => $models],
            $settings + ['infra' => ['luz_hr' => 926, 'depr_hr' => 400, 'mant_hr' => 700], 'prep' => ['mano_obra_hr' => 1000], 'oper' => ['ganancia' => 50]],
            $material + ['id' => 'PLA_BLACK', 'cost_per_kg' => 85000, 'density' => 1.24],
            []
        );
    }

    public function test_fdm_pieces_share_prep_and_purge_per_bed(): void
    {
        $one = $this->fdmQuote([$this->cube()]);
        $ten = $this->fdmQuote([$this->cube(['qty' => 10])]);
        $this->assertSame(1, $ten['beds']);
        $this->assertSame(10, $ten['pieces_per_bed']);
        $purgeH = QuotePricingService::fdmPrintHours(QuotePricingService::FDM_PURGE_G, 1.24, 0.2);
        $net = $one['weight_g'] - QuotePricingService::FDM_PURGE_G;
        $this->assertEqualsWithDelta($net * 10 + QuotePricingService::FDM_PURGE_G, $ten['weight_g'], 1e-9);
        // Preparación y purga una sola vez
        $print = $one['duration_h'] - QuotePricingService::FDM_PREP_H - $purgeH;
        $this->assertEqualsWithDelta($print * 10 + QuotePricingService::FDM_PREP_H + $purgeH, $ten['duration_h'], 1e-9);
        // Una cama más pequeña obliga a más camas: (50·50·0.7) / (25·25) = 2 por cama
        $small = $this->fdmQuote([$this->cube(['qty' => 10])], [], [], ['bed' => ['x' => 50, 'y' => 50, 'z' => 50]]);
        $this->assertSame(5, $small['beds']);
        // Sin cama en la entrada se usa settings `beds`
        $fromSettings = $this->fdmQuote([$this->cube(['qty' => 10])], ['beds' => ['FDM' => ['x' => 50, 'y' => 50, 'z' => 50]]]);
        $this->assertSame(5, $fromSettings['beds']);
    }

    public function test_sla_time_is_per_bed_not_per_piece(): void
    {
        $r = $this->service()->quote(
            ['technology' => 'SLA', 'models' => [$this->cube(['qty' => 6])]],
            [],
            ['id' => 'RES', 'cost_per_kg' => 250000, 'density' => 1.1],
            []
        );
        $this->assertSame(1, $r['beds']);
        $this->assertEqualsWithDelta(max(0.5, 20 / 25), $r['duration_h'], 1e-9);
    }

    public function test_orientation_with_least_support(): void
    {
        // Voladizos grandes en Y y pocos en Z: se imprime con Z vertical
        $r = $this->fdmQuote([$this->cube([
            'bbox_mm' => [40, 10, 20],
            'down_area_mm2' => ['x' => 900, 'y' => 1200, 'z' => 300],
            'bed_contact_mm2' => ['x' => 0, 'y' => 0, 'z' => 0],
        ])]);
        $this->assertSame('z', $r['models'][0]['orientation']);
        $this->assertEqualsWithDelta(300, $r['models'][0]['raw']['support_area_mm2'], 1e-9);
        // Placa plana: su cara de abajo apoya en la cama, sigue acostada
        $plate = $this->fdmQuote([$this->cube([
            'down_area_mm2' => ['x' => 30, 'y' => 1500, 'z' => 50],
            'bed_contact_mm2' => ['x' => 30, 'y' => 1500, 'z' => 0],
        ])]);
        $this->assertSame('y', $plate['models'][0]['orientation']);
        // Sin áreas por eje (pedidos viejos): orientación del visor y su área de soporte
        $legacy = $this->fdmQuote([$this->cube()]);
        $this->assertSame('y', $legacy['models'][0]['orientation']);
        $this->assertEqualsWithDelta(400, $legacy['models'][0]['raw']['support_area_mm2'], 1e-9);
    }

    public function test_material_params_and_calibration(): void
    {
        $base = $this->fdmQuote([$this->cube()]);
        $prep = QuotePricingService::FDM_PREP_H;
        $waste = $this->fdmQuote([$this->cube()], [], ['waste_pct' => 10]);
        $this->assertEqualsWithDelta(($base['weight_g'] - 3) * 1.1 + 3, $waste['weight_g'], 1e-9);

        $slow = $this->fdmQuote([$this->cube()], [], ['speed_factor' => 2]);
        $this->assertEqualsWithDelta(($base['duration_h'] - $prep) * 2 + $prep, $slow['duration_h'], 1e-9);

        $cal = $this->fdmQuote([$this->cube()], ['calibration' => ['time_factor' => ['FDM' => 2], 'by_material' => ['OTRO' => 0.5]]]);
        $this->assertEqualsWithDelta($slow['duration_h'], $cal['duration_h'], 1e-9);
        $this->assertSame(2.0, $cal['calibration_factor']);
        $byMat = $this->fdmQuote([$this->cube()], ['calibration' => ['time_factor' => ['FDM' => 2], 'by_material' => ['PLA_BLACK' => 1]]]);
        $this->assertEqualsWithDelta($base['duration_h'], $byMat['duration_h'], 1e-9);
        // Fuera de rango se ignora
        $bad = $this->fdmQuote([$this->cube()], ['calibration' => ['time_factor' => ['FDM' => 50]]]);
        $this->assertSame($base['total'], $bad['total']);
        $this->assertSame(1.0, $bad['calibration_factor']);

        $noFail = $this->fdmQuote([$this->cube()], ['oper' => ['ganancia' => 50, 'fallos' => 30]], ['failure_pct' => 0]);
        $this->assertSame($base['total'], $noFail['total']);
    }

    public function test_commercial_rules_from_settings(): void
    {
        $base = $this->fdmQuote([$this->cube(['qty' => 3])]);
        $this->assertSame([], $base['adjustments']);

        $rules = ['rules' => ['rush_pct' => 20, 'round_to' => 500, 'volume_tiers' => [['min_qty' => 3, 'discount_pct' => 5]]]];
        $plain = $this->fdmQuote([$this->cube(['qty' => 3])], $rules);
        $this->assertSame(0, $plain['unit_price'] % 500);
        $this->assertSame(['rounding', 'volume'], array_column($plain['adjustments'], 'type'));
        $this->assertFalse($plain['urgent']);

        $urgent = $this->fdmQuote([$this->cube(['qty' => 3])], $rules, [], ['urgent' => true]);
        $this->assertTrue($urgent['urgent']);
        $this->assertSame('rush', $urgent['adjustments'][0]['type']);
        $this->assertGreaterThan($plain['total'], $urgent['total']);

        $labor = $this->fdmQuote([$this->cube(['qty' => 3])], ['rules' => ['labor_per_piece_min' => 6]]);
        $this->assertEqualsWithDelta($base['production']['total'] + 3 * 0.1 * 1000, $labor['production']['total'], 1e-6);

        $min = $this->fdmQuote([$this->cube()], ['rules' => ['min_order_total' => 100000]]);
        $this->assertSame(100000, $min['subtotal_after_discount']);
        $this->assertSame('min_order', $min['adjustments'][0]['type']);

        // Volumen + cupón: nunca más del 50 %
        $capped = $this->service()->quote(
            ['technology' => 'FDM', 'models' => [$this->cube(['qty' => 3])]],
            ['oper' => ['ganancia' => 50], 'rules' => ['volume_tiers' => [['min_qty' => 1, 'discount_pct' => 40]]]],
            ['id' => 'PLA_BLACK', 'cost_per_kg' => 85000, 'density' => 1.24],
            [],
            ['code' => 'X', 'discount_pct' => 50, 'label' => null]
        );
        $this->assertSame((int) round($capped['subtotal'] * 0.5), $capped['subtotal_after_discount']);
    }

    public function test_new_settings_absent_keep_single_piece_price(): void
    {
        $plain = $this->fdmQuote([$this->cube()]);
        $empty = $this->fdmQuote([$this->cube()], ['rules' => [], 'calibration' => [], 'beds' => []], ['speed_factor' => null, 'waste_pct' => null, 'failure_pct' => null]);
        $this->assertSame($plain['total'], $empty['total']);
        $this->assertSame([], $empty['adjustments']);
    }
}

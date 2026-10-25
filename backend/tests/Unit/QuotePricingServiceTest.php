<?php

namespace Tests\Unit;

use App\Services\CostCalculatorService;
use App\Services\QuotePricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Compara QuotePricingService (PHP) con los vectores generados replicando el
 * cálculo del frontend (tests/Fixtures/pricing_vectors.json).
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

    public function test_per_model_qty_and_sla_height_clamp(): void
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

        // SLA: dimensions_mm del cliente acotadas a [lado menor, diagonal] del bbox escalado
        $sla = $svc->quote(
            ['technology' => 'SLA', 'models' => [$base + ['name' => 'r', 'qty' => 1, 'dimensions_mm' => '20x20x9999']]],
            [],
            ['cost_per_kg' => 250000, 'density' => 1.1],
            []
        );
        $this->assertEqualsWithDelta(sqrt(3 * 400), $sla['models'][0]['height_mm'], 0.01);
    }
}

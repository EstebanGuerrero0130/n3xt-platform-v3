<?php

namespace Tests\Unit;

use App\Services\QuotePricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Paridad del núcleo del cotizador: QuotePricingService::estimate y sus helpers deben dar
 * lo mismo que calcQuoteEstimate del frontend (tests/Fixtures/quote_core_vectors.json,
 * generado con costCalculator.ts y comprobado también en quoteCore.test.ts).
 */
class QuoteCoreParityTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../Fixtures/quote_core_vectors.json';

    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function caseProvider(): array
    {
        $out = [];
        foreach (self::fixture()['cases'] as $c) {
            $out[$c['name']] = [$c];
        }
        return $out;
    }

    public static function helperProvider(): array
    {
        $out = [];
        foreach (self::fixture()['helpers'] as $i => $h) {
            $out["{$h['fn']} #{$i}"] = [$h];
        }
        return $out;
    }

    #[DataProvider('caseProvider')]
    public function test_estimate_matches_frontend(array $case): void
    {
        $in = $case['input'];
        $in['rules'] = QuotePricingService::normalizeRules($in['rules'] ?? null);
        $result = QuotePricingService::estimate($in);
        foreach ($case['expected'] as $key => $value) {
            $this->assertArrayHasKey($key, $result, "{$case['name']}: $key");
            $this->assertClose($value, $result[$key], "{$case['name']}.$key");
        }
    }

    #[DataProvider('helperProvider')]
    public function test_helpers_match_frontend(array $h): void
    {
        $a = $h['args'];
        $actual = match ($h['fn']) {
            'normalizeRules' => QuotePricingService::normalizeRules($a[0]),
            'normalizeCalibration' => QuotePricingService::normalizeCalibration($a[0]),
            'calibrationFactor' => QuotePricingService::calibrationFactor(QuotePricingService::normalizeCalibration($a[0]), $a[1], $a[2]),
            'normalizeBeds' => QuotePricingService::normalizeBeds($a[0]),
            'materialParams' => QuotePricingService::materialParams($a[0]),
            'chooseOrientation' => (function () use ($a) {
                $o = QuotePricingService::chooseOrientation($a[0], $a[1]);
                return ['axis' => $o['axis'], 'supportArea' => $o['support_area_mm2']];
            })(),
            'piecesPerBed' => QuotePricingService::piecesPerBed($a[0], $a[1]),
            'fdmPrintHours' => QuotePricingService::fdmPrintHours($a[0], $a[1], $a[2]),
            'bedsPerPiece' => QuotePricingService::bedsPerPiece($a[0], $a[1], $a[2]),
            'finiteNum' => QuotePricingService::finiteNum($a[0]),
        };
        $this->assertClose($h['expected'], $actual, $h['fn']);
    }

    private function assertClose(mixed $expected, mixed $actual, string $path): void
    {
        if (is_array($expected)) {
            $this->assertIsArray($actual, $path);
            $this->assertCount(count($expected), $actual, $path);
            foreach ($expected as $k => $v) {
                $this->assertArrayHasKey($k, $actual, "$path.$k");
                $this->assertClose($v, $actual[$k], "$path.$k");
            }
        } elseif (is_int($expected) || is_float($expected)) {
            $this->assertEqualsWithDelta($expected, $actual, 1e-9 * max(1, abs($expected)), $path);
        } else {
            $this->assertSame($expected, $actual, $path);
        }
    }
}

<?php

namespace Tests\Unit;

use App\Services\LeadTimeService;
use App\Services\ProductionSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** Mismos casos que frontend/src/__tests__/productionSchedule.test.ts */
class ProductionScheduleTest extends TestCase
{
    private CarbonImmutable $now;

    private array $printers = [
        ['id' => 1, 'name' => 'Ender', 'technology' => 'FDM', 'status' => 'printing'],
        ['id' => 2, 'name' => 'Bambu', 'technology' => 'FDM', 'status' => 'idle'],
        ['id' => 3, 'name' => 'Saturn', 'technology' => 'SLA', 'status' => 'maintenance'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new CarbonImmutable('2026-10-28T10:00:00Z');
    }

    private static function iso(float $ms): string
    {
        return CarbonImmutable::createFromTimestampMs((int) $ms)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    private function ids(array $row): array
    {
        return array_map(fn ($s) => $s['order']['id'], $row['slots']);
    }

    public function test_places_printing_jobs_from_their_start(): void
    {
        $rows = ProductionSchedule::build($this->printers, [
            ['id' => 10, 'status' => 'printing', 'printer_id' => 1, 'estimated_duration_h' => 4, 'printing_started_at' => '2026-10-28T08:00:00Z'],
        ], $this->now)['rows'];
        $slot = $rows[0]['slots'][0];
        $this->assertSame('printing', $slot['state']);
        $this->assertSame('2026-10-28T12:00:00.000Z', self::iso($slot['end']));
        $this->assertSame('2026-10-28T12:00:00.000Z', self::iso($rows[0]['freeAt']));
    }

    public function test_queue_goes_fifo_to_soonest_free_compatible_printer(): void
    {
        $rows = ProductionSchedule::build($this->printers, [
            ['id' => 10, 'status' => 'printing', 'printer_id' => 1, 'estimated_duration_h' => 4, 'printing_started_at' => '2026-10-28T08:00:00Z'],
            ['id' => 21, 'status' => 'pending', 'technology' => 'FDM', 'estimated_duration_h' => 3, 'created_at' => '2026-10-27T09:00:00Z'],
            ['id' => 20, 'status' => 'pending', 'technology' => 'fdm', 'estimated_duration_h' => 5, 'created_at' => '2026-10-27T08:00:00Z'],
        ], $this->now)['rows'];
        $this->assertSame([20], $this->ids($rows[1]));
        $this->assertSame('2026-10-28T10:00:00.000Z', self::iso($rows[1]['slots'][0]['start']));
        $this->assertSame([10, 21], $this->ids($rows[0]));
        $this->assertSame('2026-10-28T12:00:00.000Z', self::iso($rows[0]['slots'][1]['start']));
    }

    public function test_two_free_printers_share_the_queue(): void
    {
        $rows = ProductionSchedule::build($this->printers, [
            ['id' => 50, 'status' => 'pending', 'technology' => 'FDM', 'estimated_duration_h' => 0, 'created_at' => '2026-10-27T08:00:00Z'],
            ['id' => 51, 'status' => 'pending', 'technology' => 'FDM', 'estimated_duration_h' => 0, 'created_at' => '2026-10-27T09:00:00Z'],
        ], $this->now)['rows'];
        $this->assertSame([50], $this->ids($rows[0]));
        $this->assertSame([51], $this->ids($rows[1]));
    }

    public function test_skips_maintenance_printers_and_catalog_orders(): void
    {
        $result = ProductionSchedule::build($this->printers, [
            ['id' => 30, 'status' => 'pending', 'technology' => 'SLA', 'estimated_duration_h' => 2],
            ['id' => 31, 'status' => 'pending', 'technology' => 'CATALOGO'],
            ['id' => 32, 'status' => 'printing', 'printer_id' => null, 'estimated_duration_h' => 1],
        ], $this->now);
        $this->assertSame([], $result['rows'][2]['slots']);
        $this->assertSame([32, 30], array_column($result['unscheduled'], 'id'));
    }

    public function test_flags_overdue_jobs(): void
    {
        $rows = ProductionSchedule::build($this->printers, [
            ['id' => 40, 'status' => 'printing', 'printer_id' => 2, 'estimated_duration_h' => 1, 'printing_started_at' => '2026-10-28T06:00:00Z'],
        ], $this->now)['rows'];
        $this->assertTrue($rows[1]['slots'][0]['overdue']);
        $this->assertSame('2026-10-28T10:00:00.000Z', self::iso($rows[1]['freeAt']));
    }

    public function test_new_job_takes_soonest_free_printer_after_the_queue(): void
    {
        $orders = [
            ['id' => 10, 'status' => 'printing', 'printer_id' => 1, 'estimated_duration_h' => 4, 'printing_started_at' => '2026-10-28T08:00:00Z'],
            ['id' => 20, 'status' => 'pending', 'technology' => 'FDM', 'estimated_duration_h' => 5, 'created_at' => '2026-10-27T08:00:00Z'],
        ];
        // Ender libre a las 12, Bambu a las 15 (pedido 20): el nuevo de 2 h va a la Ender
        $end = LeadTimeService::slotEnd($this->printers, $orders, 'fdm', 2, $this->now);
        $this->assertSame('2026-10-28T14:00:00.000Z', $end->utc()->format('Y-m-d\TH:i:s.v\Z'));
        // Sin máquina SLA disponible (en mantenimiento): sin fecha
        $this->assertNull(LeadTimeService::slotEnd($this->printers, $orders, 'SLA', 2, $this->now));
    }

    public function test_post_processing_adds_days_and_skips_sundays(): void
    {
        // Miércoles 28 (Bogotá) + 24 h → jueves 29
        $this->assertSame('2026-10-29', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-10-28T20:00:00Z'))->toDateString());
        // Sábado 31 + 1 día → domingo: pasa al lunes 2
        $this->assertSame('2026-11-02', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-10-31T15:00:00Z'))->toDateString());
        // Viernes 30 + 48 h → sábado 31, (domingo no cuenta) lunes 2
        $this->assertSame('2026-11-02', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-10-30T15:00:00Z'), ['post_hours' => 48])->toDateString());
        // 16 h de trabajo a 8 h por día = 2 días
        $this->assertSame('2026-10-30', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-10-28T15:00:00Z'), ['post_hours' => 16, 'work_hours' => 8])->toDateString());
        // Sin posproceso, termina en domingo: se entrega el lunes
        $this->assertSame('2026-11-02', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-11-01T15:00:00Z'), ['post_hours' => 0])->toDateString());
        // La fecha se toma en hora de Bogotá: 03:00 UTC del 29 es aún el 28 allá
        $this->assertSame('2026-10-29', LeadTimeService::addPostProcessing(new CarbonImmutable('2026-10-29T03:00:00Z'))->toDateString());
    }

    public function test_spanish_label(): void
    {
        $this->assertSame('jueves 29 de octubre', LeadTimeService::label(new CarbonImmutable('2026-10-29T15:00:00Z')));
    }
}

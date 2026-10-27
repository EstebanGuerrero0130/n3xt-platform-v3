<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Calendario de producción: port de frontend/src/utils/productionSchedule.ts.
 * Mantener las dos versiones equivalentes (mismos casos en sus pruebas).
 *
 * Lo que está en impresión empieza en printing_started_at; la cola (pendientes,
 * sin catálogo) se reparte, urgentes primero y luego en orden de llegada, a la
 * máquina compatible libre más pronto. Tiempos en milisegundos para igualar al
 * cálculo del frontend.
 */
class ProductionSchedule
{
    private const HOUR_MS = 3_600_000;

    /**
     * @param  array<int, array<string, mixed>>  $printers  id, technology, status
     * @param  array<int, array<string, mixed>>  $orders    id, status, technology, printer_id, estimated_duration_h, printing_started_at, updated_at, created_at, is_urgent
     * @return array{rows: array<int, array{printer: array, slots: array, freeAt: float, inMaintenance: bool, offline: bool}>, unscheduled: array}
     */
    public static function build(array $printers, array $orders, DateTimeInterface $now): array
    {
        $nowMs = self::ms($now);
        $rows = array_map(fn ($p) => [
            'printer' => $p, 'slots' => [], 'freeAt' => $nowMs,
            'inMaintenance' => ($p['status'] ?? null) === 'maintenance',
            'offline' => ($p['status'] ?? null) === 'offline',
        ], array_values($printers));
        $index = [];
        foreach ($rows as $i => $r) {
            $index[(string) $r['printer']['id']] = $i;
        }
        $unscheduled = [];

        // 1. Lo que ya se está imprimiendo
        foreach ($orders as $o) {
            if (($o['status'] ?? null) !== 'printing') continue;
            $i = isset($o['printer_id']) ? ($index[(string) $o['printer_id']] ?? null) : null;
            if ($i === null) { $unscheduled[] = $o; continue; }
            $start = self::ms($o['printing_started_at'] ?? null) ?? self::ms($o['updated_at'] ?? null) ?? $nowMs;
            $end = $start + self::hoursOf($o) * self::HOUR_MS;
            $rows[$i]['slots'][] = ['order' => $o, 'start' => $start, 'end' => $end, 'state' => 'printing', 'overdue' => $end < $nowMs];
            if ($end > $rows[$i]['freeAt']) $rows[$i]['freeAt'] = $end;
        }

        // 2. La cola: urgentes primero y luego en orden de llegada (usort es estable, como sort en JS)
        $queue = array_values(array_filter($orders, fn ($o) => ($o['status'] ?? null) === 'pending' && self::tech($o['technology'] ?? null) !== 'CATALOGO'));
        usort($queue, fn ($a, $b) => (self::isUrgent($b) <=> self::isUrgent($a))
            ?: ((self::ms($a['created_at'] ?? null) ?? 0) <=> (self::ms($b['created_at'] ?? null) ?? 0)));

        foreach ($queue as $o) {
            $i = self::pickRow($rows, self::tech($o['technology'] ?? null), $o['printer_id'] ?? null);
            if ($i === null) { $unscheduled[] = $o; continue; }
            $start = $rows[$i]['freeAt'];
            $end = $start + self::hoursOf($o) * self::HOUR_MS;
            $rows[$i]['slots'][] = ['order' => $o, 'start' => $start, 'end' => $end, 'state' => 'queued', 'overdue' => false];
            $rows[$i]['freeAt'] = $end;
        }

        return ['rows' => $rows, 'unscheduled' => $unscheduled];
    }

    /** Máquina compatible: la preferida si se pidió; si no, la libre más pronto (empate: menos trabajos). */
    public static function pickRow(array $rows, string $technology, $preferredId = null): ?int
    {
        $candidates = [];
        foreach ($rows as $i => $r) {
            if (!$r['inMaintenance'] && !$r['offline'] && self::tech($r['printer']['technology'] ?? null) === $technology) {
                $candidates[] = $i;
            }
        }
        if ($preferredId !== null) {
            foreach ($candidates as $i) {
                if ((string) $rows[$i]['printer']['id'] === (string) $preferredId) return $i;
            }
        }
        usort($candidates, fn ($a, $b) => ($rows[$a]['freeAt'] <=> $rows[$b]['freeAt']) ?: (count($rows[$a]['slots']) <=> count($rows[$b]['slots'])));

        return $candidates[0] ?? null;
    }

    public static function tech($v): string
    {
        return strtoupper((string) $v);
    }

    /** Pagó el recargo urgente (columna is_urgent) */
    public static function isUrgent(array $o): bool
    {
        return in_array($o['is_urgent'] ?? false, [true, 1, '1', 'true'], true);
    }

    private static function hoursOf(array $o): float
    {
        return max((float) ($o['estimated_duration_h'] ?? 0), 0);
    }

    /** Milisegundos desde epoch, o null si no hay fecha válida */
    public static function ms($v): ?float
    {
        if ($v === null || $v === '') return null;
        try {
            $d = $v instanceof DateTimeInterface ? $v : new CarbonImmutable($v);
        } catch (\Throwable) {
            return null;
        }

        return (float) $d->format('U') * 1000 + (int) $d->format('v');
    }
}

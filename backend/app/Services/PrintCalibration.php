<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Printer;
use App\Models\Setting;

/**
 * Calibración del tiempo de impresión: compara la duración real (printing_started_at
 * → printing_finished_at) con la estimada y sugiere un factor por tecnología y por
 * material.
 *
 * Ajuste guardado en settings `calibration` (CONTRATO con el cotizador):
 *   { time_factor: { FDM?: number, SLA?: number }, by_material?: { [material_id]: number } }
 * El cotizador multiplica el tiempo estimado por
 *   by_material[material_id] ?? time_factor[technology] ?? 1
 * y guarda en cost_snapshot.calibration_factor el factor que usó.
 *
 * Por eso el estimado de un pedido ya calibrado no es el "crudo": la razón se
 * calcula contra estimated / calibration_factor, así el factor sugerido siempre
 * es relativo al cálculo sin calibrar y se puede aplicar tal cual.
 */
class PrintCalibration
{
    public const SETTING_KEY = 'calibration';
    public const TECHNOLOGIES = ['FDM', 'SLA'];
    public const MIN_FACTOR = 0.3;
    public const MAX_FACTOR = 3.0;
    /** Muestras mínimas para sugerir un factor. */
    public const MIN_SAMPLES = 5;
    /** Impresiones más cortas no dicen nada (pruebas, cancelaciones de facto). */
    private const MIN_DURATION_H = 10 / 60;

    /** Razón real/estimado de cada impresión válida (solo datos del pedido). */
    public function samples(): array
    {
        $orders = Order::query()
            ->whereNotNull('printing_started_at')
            ->whereNotNull('printing_finished_at')
            ->where('estimated_duration_h', '>', 0)
            ->whereIn('technology', self::TECHNOLOGIES)
            ->whereNotIn('status', ['printing', 'cancelled'])
            ->get(['id', 'technology', 'material_id', 'material_name', 'printer_id', 'estimated_duration_h', 'printing_started_at', 'printing_finished_at', 'cost_snapshot']);

        $samples = [];
        foreach ($orders as $order) {
            $actual = $order->printing_started_at->diffInSeconds($order->printing_finished_at) / 3600;
            if ($actual < self::MIN_DURATION_H) {
                continue;
            }
            $factor = $order->cost_snapshot['calibration_factor'] ?? null;
            $raw = (float) $order->estimated_duration_h;
            if (is_numeric($factor) && $factor > 0) {
                $raw /= (float) $factor;
            }
            $ratio = $actual / $raw;
            if ($ratio < self::MIN_FACTOR || $ratio > self::MAX_FACTOR) {
                continue;
            }
            $samples[] = [
                'technology' => $order->technology,
                'material_id' => $order->material_id,
                'material_name' => $order->material_name,
                'printer_id' => $order->printer_id,
                'ratio' => $ratio,
            ];
        }
        return $samples;
    }

    /** Estadísticas por tecnología, material e impresora, más los factores aplicados. */
    public function stats(): array
    {
        $samples = collect($this->samples());
        $printers = Printer::pluck('name', 'id');

        $group = fn (string $key, callable $extra) => $samples->whereNotNull($key)->groupBy($key)
            ->map(fn ($rows, $id) => array_merge($extra($id, $rows), self::summary($rows->pluck('ratio')->all())))
            ->values()->all();

        return [
            'min_samples' => self::MIN_SAMPLES,
            'by_technology' => $group('technology', fn ($id) => ['technology' => $id]),
            'by_material' => $group('material_id', fn ($id, $rows) => [
                'material_id' => (string) $id,
                'material_name' => $rows->first()['material_name'],
                'technology' => $rows->first()['technology'],
            ]),
            // Solo informativo: el cotizador no calibra por máquina
            'by_printer' => array_map(fn ($row) => array_diff_key($row, ['suggested_factor' => 1]), $group('printer_id', fn ($id) => [
                'printer_id' => (int) $id,
                'printer_name' => $printers[$id] ?? null,
            ])),
            'applied' => self::current(),
        ];
    }

    /** Muestras, mediana y factor sugerido (solo con suficientes muestras). */
    public static function summary(array $ratios): array
    {
        $median = self::median($ratios);
        return [
            'samples' => count($ratios),
            'median_ratio' => $median === null ? null : round($median, 4),
            'suggested_factor' => $median !== null && count($ratios) >= self::MIN_SAMPLES ? round($median, 2) : null,
        ];
    }

    public static function median(array $values): ?float
    {
        if (!$values) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** Factores aplicados hoy, normalizados al contrato. */
    public static function current(): array
    {
        $value = Setting::where('key', self::SETTING_KEY)->value('value');
        $value = is_array($value) ? $value : [];
        $valid = fn ($v) => is_numeric($v) && $v >= self::MIN_FACTOR && $v <= self::MAX_FACTOR;
        $time = array_map('floatval', array_filter(array_intersect_key((array) ($value['time_factor'] ?? []), array_flip(self::TECHNOLOGIES)), $valid));
        $byMaterial = array_map('floatval', array_filter((array) ($value['by_material'] ?? []), $valid));

        return ['time_factor' => $time, 'by_material' => $byMaterial];
    }

    /**
     * Aplica o quita factores (null = quitar) y guarda la forma exacta del contrato.
     * $changes = ['time_factor' => ['FDM' => 1.12|null], 'by_material' => ['PLA_BLACK' => 0.95|null]]
     */
    public static function apply(array $changes): array
    {
        $current = self::current();
        foreach (['time_factor', 'by_material'] as $group) {
            foreach ($changes[$group] ?? [] as $id => $factor) {
                if ($factor === null) {
                    unset($current[$group][$id]);
                } else {
                    $current[$group][$id] = round((float) $factor, 2);
                }
            }
        }

        // Objetos JSON aunque estén vacíos o las claves parezcan índices
        $value = ['time_factor' => (object) $current['time_factor']];
        if ($current['by_material']) {
            $value['by_material'] = (object) $current['by_material'];
        }
        Setting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => $value]);

        return self::current();
    }
}

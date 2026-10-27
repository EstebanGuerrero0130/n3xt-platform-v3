<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Printer;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * ¿Cuándo estaría listo un pedido nuevo? Lo ubica en el calendario de producción
 * (ProductionSchedule) y suma el posproceso (ajuste `lead_time`), sin contar domingos.
 * Solo devuelve una fecha: nunca datos de otros pedidos.
 */
class LeadTimeService
{
    /** Zona del taller para hablar de "qué día" */
    public const TIMEZONE = 'America/Bogota';

    public const DEFAULT_POST_HOURS = 24;

    /**
     * Fecha de entrega estimada, o null si no hay máquina disponible para esa tecnología.
     * $urgent: el trabajo pasa al frente de la cola (justo después de lo que se imprime).
     */
    public function estimate(string $technology, float $hours, bool $urgent = false, ?DateTimeInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());
        $printers = Printer::query()->get(['id', 'technology', 'status'])->toArray();
        $orders = Order::query()
            ->whereIn('status', $urgent ? ['printing'] : ['printing', 'pending'])
            ->get(['id', 'status', 'technology', 'printer_id', 'estimated_duration_h', 'printing_started_at', 'updated_at', 'created_at'])
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'status' => $o->status,
                'technology' => $o->technology,
                'printer_id' => $o->printer_id,
                'estimated_duration_h' => $o->estimated_duration_h,
                'printing_started_at' => $o->printing_started_at,
                'updated_at' => $o->updated_at,
                'created_at' => $o->created_at,
            ])->all();

        $end = self::slotEnd($printers, $orders, $technology, $hours, $now);
        if ($end === null) return null;

        $lead = Setting::where('key', 'lead_time')->value('value');

        return self::addPostProcessing($end, is_array($lead) ? $lead : []);
    }

    /** Fin del turno del trabajo nuevo, que llega último a la cola recibida. */
    public static function slotEnd(array $printers, array $orders, string $technology, float $hours, DateTimeInterface $now): ?CarbonImmutable
    {
        $rows = ProductionSchedule::build($printers, $orders, $now)['rows'];
        $i = ProductionSchedule::pickRow($rows, ProductionSchedule::tech($technology));
        if ($i === null) return null;
        $endMs = $rows[$i]['freeAt'] + max($hours, 0) * 3_600_000;

        return CarbonImmutable::createFromTimestampMs((int) round($endMs));
    }

    /**
     * Posproceso en días: post_hours de trabajo a work_hours por día (24 si no se
     * define). Cada día suma una fecha de calendario; los domingos no cuentan.
     */
    public static function addPostProcessing(DateTimeInterface $end, array $lead = []): CarbonImmutable
    {
        $postHours = max((float) ($lead['post_hours'] ?? self::DEFAULT_POST_HOURS), 0);
        $workHours = (float) ($lead['work_hours'] ?? 24);
        if ($workHours <= 0 || $workHours > 24) $workHours = 24;

        $date = CarbonImmutable::instance($end)->setTimezone(self::TIMEZONE);
        // Tope de un año: un ajuste absurdo no debe colgar la página pública
        $days = (int) min(ceil($postHours / $workHours), 365);
        for ($d = 0; $d < $days; $d++) {
            $date = $date->addDay();
            while ($date->isSunday()) $date = $date->addDay();
        }
        while ($date->isSunday()) $date = $date->addDay();

        return $date;
    }

    /** "jueves 30 de octubre" */
    public static function label(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->locale('es')->isoFormat('dddd D [de] MMMM');
    }
}

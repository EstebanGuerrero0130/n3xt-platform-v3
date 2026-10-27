<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use App\Services\LeadTimeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Datos públicos del cotizador: tamaño máximo de pieza y fecha estimada de entrega. */
class QuoteInfoController extends Controller
{
    use ApiResponse;

    /** Volumen típico si ninguna máquina tiene medidas cargadas (mm) */
    public const DEFAULT_CAPACITY = [
        'FDM' => ['x' => 256, 'y' => 256, 'z' => 256],
        'SLA' => ['x' => 218, 'y' => 123, 'z' => 250],
    ];

    /** Mayor volumen de impresión por tecnología entre las máquinas que no están apagadas. */
    public function capacity()
    {
        $printers = Printer::query()
            ->where('status', '!=', 'offline')
            ->whereNotNull('build_x_mm')->whereNotNull('build_y_mm')->whereNotNull('build_z_mm')
            ->get(['technology', 'build_x_mm', 'build_y_mm', 'build_z_mm']);

        $result = [];
        foreach (self::DEFAULT_CAPACITY as $tech => $default) {
            $best = $printers
                ->filter(fn ($p) => strtoupper((string) $p->technology) === $tech)
                ->sortByDesc(fn ($p) => $p->build_x_mm * $p->build_y_mm * $p->build_z_mm)
                ->first();
            $result[$tech] = $best
                ? ['x' => (int) $best->build_x_mm, 'y' => (int) $best->build_y_mm, 'z' => (int) $best->build_z_mm]
                : $default;
        }

        return $this->success($result);
    }

    /** Fecha aproximada en que estaría listo un pedido nuevo de esa tecnología y horas. */
    public function leadTime(Request $request, LeadTimeService $leadTime)
    {
        $data = $request->validate([
            'technology' => 'required|in:FDM,SLA',
            'hours' => 'required|numeric|min:0|max:2000',
            'urgent' => 'sometimes|boolean',
        ]);

        $date = $leadTime->estimate($data['technology'], (float) $data['hours'], (bool) ($data['urgent'] ?? false));

        return $this->success([
            'date' => $date?->toDateString(),
            'label' => $date ? LeadTimeService::label($date) : null,
        ]);
    }
}

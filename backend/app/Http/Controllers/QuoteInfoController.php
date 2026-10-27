<?php

namespace App\Http\Controllers;

use App\Services\QuotePricingService;
use App\Services\LeadTimeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Datos públicos del cotizador: tamaño máximo de pieza y fecha estimada de entrega. */
class QuoteInfoController extends Controller
{
    use ApiResponse;

    /** Volumen típico si ninguna máquina tiene medidas cargadas (mm) */
    public const DEFAULT_CAPACITY = QuotePricingService::DEFAULT_BEDS;

    /** Mayor volumen de impresión por tecnología entre las máquinas que no están apagadas. */
    public function capacity(QuotePricingService $pricing)
    {
        // Misma cama con la que el servidor cotiza (sin máquinas con medidas: settings `beds`)
        $result = array_map(
            fn ($bed) => array_map(fn ($v) => $v == (int) $v ? (int) $v : $v, $bed),
            $pricing->loadBeds()
        );

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

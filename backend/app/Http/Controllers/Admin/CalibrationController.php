<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\PrintCalibration;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Calibración del tiempo de impresión. Leer: Maquinaria; aplicar: Parámetros
 * (escribe el ajuste `calibration` que usa el cotizador).
 */
class CalibrationController extends Controller
{
    use ApiResponse;

    public function index(PrintCalibration $calibration)
    {
        $stats = $calibration->stats();
        $stats['applied'] = self::asObjects($stats['applied']);
        return $this->success($stats);
    }

    /** Body: { time_factor?: { FDM?: n|null, SLA?: n|null }, by_material?: { [id]: n|null } }; null quita. */
    public function apply(Request $request)
    {
        $range = 'nullable|numeric|between:' . PrintCalibration::MIN_FACTOR . ',' . PrintCalibration::MAX_FACTOR;
        $data = $request->validate([
            'time_factor' => 'sometimes|array:' . implode(',', PrintCalibration::TECHNOLOGIES),
            'time_factor.*' => $range,
            'by_material' => 'sometimes|array|max:200',
            'by_material.*' => $range,
        ]);
        if (empty($data['time_factor']) && empty($data['by_material'])) {
            return $this->error('Indica qué factor aplicar o quitar.', 422);
        }

        // Quitar acepta materiales ya borrados; aplicar exige que el material exista
        $toApply = array_map('strval', array_keys(array_filter($data['by_material'] ?? [], fn ($v) => $v !== null)));
        if ($toApply) {
            $known = Material::whereIn('id', $toApply)->pluck('id')->map(fn ($id) => (string) $id)->all();
            if ($missing = array_diff($toApply, $known)) {
                return $this->error('Material desconocido: ' . implode(', ', $missing), 422);
            }
        }

        $applied = PrintCalibration::apply([
            'time_factor' => $data['time_factor'] ?? [],
            'by_material' => $data['by_material'] ?? [],
        ]);

        return $this->success(['applied' => self::asObjects($applied)], 'Calibración actualizada');
    }

    /** En JSON siempre objetos ({}), nunca listas ([]). */
    private static function asObjects(array $applied): array
    {
        return ['time_factor' => (object) $applied['time_factor'], 'by_material' => (object) $applied['by_material']];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $printers = Printer::with(['orders' => function($query) {
                $query->where('status', 'printing');
            }])->get();
            return $this->success($printers);
        } catch (\Exception $e) {
            return $this->error('Error al listar impresoras', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'model' => 'required|string|max:255',
                'technology' => 'required|in:FDM,SLA'
            ]);

            $validated['status'] = 'idle';
            $validated['maintenance_interval_h'] = 200;
            $printer = Printer::create($validated);
            
            return $this->success($printer, 'Impresora creada con éxito.', 201);
        } catch (\Exception $e) {
            return $this->error('Error al crear impresora: ' . $e->getMessage(), 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $printer = Printer::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'model' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:idle,printing,maintenance,offline',
                'next_maintenance' => 'nullable|date',
                'last_maintenance' => 'nullable|date',
                'total_hours_run' => 'sometimes|numeric',
                'maintenance_interval_h' => 'sometimes|integer',
                'maintenance_notes' => 'nullable|string'
            ]);

            $printer->update($validated);
            return $this->success($printer, 'Impresora actualizada.');
        } catch (\Exception $e) {
            return $this->error('Error al actualizar impresora', 422);
        }
    }

    public function maintenanceComplete($id)
    {
        try {
            $printer = Printer::findOrFail($id);
            $printer->total_hours_run = 0;
            $printer->last_maintenance = now();
            $printer->next_maintenance = now()->addMonths(3); 
            $printer->status = 'idle';
            $printer->save();

            return $this->success($printer, 'Mantenimiento registrado. Contador de horas reiniciado.');
        } catch (\Exception $e) {
            return $this->error('Error al completar mantenimiento', 500);
        }
    }

    public function reset($id)
    {
        try {
            $printer = Printer::findOrFail($id);
            $printer->status = 'idle';
            $printer->save();
            return $this->success($printer, 'Máquina reiniciada a estado Libre');
        } catch (\Exception $e) {
            return $this->error('Error al reiniciar máquina', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $printer = Printer::findOrFail($id);
            $printer->delete();
            return $this->success(null, 'Impresora eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->error('Error al eliminar impresora', 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    public function exportAll()
    {
        // Tablas estandarizadas para el Mirror Protocol v5
        $tables = [
            'orders' => 'orders',
            'materials' => 'materials',
            'inventories' => 'inventories',
            'purchases' => 'purchases',
            'printers' => 'printers',
            'recurrent_suppliers' => 'recurrent_suppliers',
            'recurrent_customers' => 'recurrent_customers', // Consistente con el modelo RecurrentCustomer
            'settings' => 'settings',
            'inventario_3d' => 'inventario_3d'
        ];

        $data = [];
        foreach ($tables as $dbTable => $apiKey) {
            if (Schema::hasTable($dbTable)) {
                $data[$apiKey] = DB::table($dbTable)->get();
            }
        }

        $filename = 'n3xt_system_backup_' . date('Y-m-d_H-i-s') . '.json';
        
        return response()->json([
            'system' => 'N3XT 3D BACKUP',
            'version' => '5.0',
            'timestamp' => now()->toDateTimeString(),
            'payload' => $data
        ])->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function importAll(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file'
        ]);

        try {
            $content = json_decode(file_get_contents($request->file('backup_file')->getRealPath()), true);
            
            if (!$content || !isset($content['payload'])) {
                return response()->json(['message' => 'Archivo de backup inválido'], 400);
            }

            return DB::transaction(function () use ($content) {
                // Disable foreign keys to allow mass deletion/reinsertion
                Schema::disableForeignKeyConstraints();

                foreach ($content['payload'] as $table => $rows) {
                    $targetTable = $table;
                    // Asegurar consistencia en nombres de tablas
                    if ($table === 'recurrent_customers') $targetTable = 'recurrent_customers';
                    
                    if (Schema::hasTable($targetTable)) {
                        DB::table($targetTable)->truncate();
                        foreach ($rows as $row) {
                            $data = (array)$row;
                            // Limpieza de campos que no existan en la base de datos de PC para evitar errores
                            $columns = Schema::getColumnListing($targetTable);
                            $cleanData = array_intersect_key($data, array_flip($columns));
                            
                            if (!empty($cleanData)) {
                                // Asegurar que campos complejos sean JSON strings para la BD
                                foreach ($cleanData as $key => $val) {
                                    if (is_array($val) || is_object($val)) {
                                        $cleanData[$key] = json_encode($val);
                                    }
                                }
                                DB::table($targetTable)->insert($cleanData);
                            }
                        }
                    }
                }

                Schema::enableForeignKeyConstraints();

                return response()->json(['message' => 'Sistema restaurado exitosamente. Sincronización completa.']);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error en la restauración: ' . $e->getMessage()], 500);
        }
    }
}

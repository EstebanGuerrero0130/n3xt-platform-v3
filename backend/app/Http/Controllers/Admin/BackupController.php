<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    /**
     * Tablas del Mirror Protocol v5, en orden de dependencias (padres primero).
     * Es también la lista blanca del import: ninguna otra tabla se toca.
     */
    private const TABLES = [
        'settings',
        'materials',
        'inventories',
        'printers',
        'recurrent_customers',
        'recurrent_suppliers',
        'purchases',
        'orders',
        'inventario_3d',
    ];

    /** Columnas sensibles que nunca salen ni entran por backup. */
    private const SENSITIVE_COLUMNS = ['password', 'remember_token', 'verification_code_hash'];

    public function exportAll()
    {
        $data = [];
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $data[$table] = DB::table($table)->get()->map(
                    fn ($row) => array_diff_key((array)$row, array_flip(self::SENSITIVE_COLUMNS))
                );
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
            'backup_file' => 'required|file|extensions:json|max:51200'
        ]);

        $content = json_decode(file_get_contents($request->file('backup_file')->getRealPath()), true);

        if (!is_array($content) || !isset($content['payload']) || !is_array($content['payload'])) {
            return response()->json(['message' => 'Archivo de backup inválido'], 400);
        }

        $payload = $content['payload'];
        $tables = array_values(array_filter(
            self::TABLES,
            fn ($t) => array_key_exists($t, $payload) && is_array($payload[$t]) && Schema::hasTable($t)
        ));

        try {
            DB::transaction(function () use ($payload, $tables) {
                Schema::disableForeignKeyConstraints();

                // 1. Vaciar todo primero (hijos antes que padres) para que un TRUNCATE ... CASCADE
                //    de Postgres no borre datos ya importados.
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }

                // 2. Insertar en orden de dependencias
                foreach ($tables as $table) {
                    $columns = array_flip(array_diff(Schema::getColumnListing($table), self::SENSITIVE_COLUMNS));
                    foreach ($payload[$table] as $row) {
                        $cleanData = array_intersect_key((array)$row, $columns);
                        if (empty($cleanData)) continue;

                        // Campos complejos como JSON string para la BD
                        foreach ($cleanData as $key => $val) {
                            if (is_array($val) || is_object($val)) {
                                $cleanData[$key] = json_encode($val);
                            }
                        }
                        DB::table($table)->insert($cleanData);
                    }
                }

                Schema::enableForeignKeyConstraints();
            });
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Error en la restauración: el archivo no coincide con la estructura actual.'], 500);
        }

        // 3. En Postgres, reajustar las secuencias tras insertar ids explícitos
        if (DB::getDriverName() === 'pgsql') {
            foreach ($tables as $table) {
                if (Schema::hasColumn($table, 'id')) {
                    DB::statement(
                        "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false) WHERE pg_get_serial_sequence(?, 'id') IS NOT NULL",
                        [$table, $table]
                    );
                }
            }
        }

        return response()->json(['message' => 'Sistema restaurado exitosamente. Sincronización completa.']);
    }
}

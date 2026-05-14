<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UtilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $utilities = [
            ['id' => 'Argo_Piez_01', 'name' => 'Argolla metalica', 'category' => 'General', 'type' => 'utility', 'unit' => 'pieza', 'cost_per_kg' => 150, 'stock' => 100],
            ['id' => 'Caja_Piez_02', 'name' => 'Caja carton microrrugado', 'category' => 'Empaque', 'type' => 'utility', 'unit' => 'pieza', 'cost_per_kg' => 900, 'stock' => 100],
            ['id' => 'Bols_Unid_03', 'name' => 'Bolsas empaque pequeña', 'category' => 'Empaque', 'type' => 'utility', 'unit' => 'unidad', 'cost_per_kg' => 90, 'stock' => 50],
            ['id' => 'Mano_Serv_04', 'name' => 'Mano de obra Postprocesado', 'category' => 'Servicio', 'type' => 'service', 'unit' => 'servicio', 'cost_per_kg' => 100, 'stock' => 60],
            ['id' => 'Alco_ML_05', 'name' => 'Alcohol isopropílico', 'category' => 'SLA', 'type' => 'utility', 'unit' => 'ml', 'cost_per_kg' => 30, 'stock' => 1000],
            ['id' => 'Cicl_Serv_06', 'name' => 'Ciclo de Lavado y Curado UV', 'category' => 'SLA', 'type' => 'service', 'unit' => 'servicio', 'cost_per_kg' => 2000, 'stock' => 1],
            ['id' => 'Plás_Metr_07', 'name' => 'Plástico de Burbuja', 'category' => 'Empaque', 'type' => 'utility', 'unit' => 'metro', 'cost_per_kg' => 0, 'stock' => 50],
            ['id' => 'Cint_Serv_08', 'name' => 'Cinta de embalaje', 'category' => 'Empaque', 'type' => 'utility', 'unit' => 'servicio', 'cost_per_kg' => 0, 'stock' => 100],
            ['id' => 'Kit_Serv_09', 'name' => 'Kit de Lijado', 'category' => 'Servicio', 'type' => 'service', 'unit' => 'servicio', 'cost_per_kg' => 0, 'stock' => 50],
            ['id' => 'Prim_Serv_10', 'name' => 'Primer', 'category' => 'Servicio', 'type' => 'utility', 'unit' => 'servicio', 'cost_per_kg' => 325, 'stock' => 200],
        ];

        foreach ($utilities as $u) {
            $material = \App\Models\Material::updateOrCreate(
                ['id' => $u['id']],
                [
                    'name' => $u['name'],
                    'category' => $u['category'],
                    'type' => $u['type'],
                    'unit' => $u['unit'],
                    'cost_per_kg' => $u['cost_per_kg'],
                    'is_active' => true
                ]
            );

            \App\Models\Inventory::updateOrCreate(
                ['material_id' => $material->id],
                [
                    'stock_available' => $u['stock'],
                    'low_stock_threshold' => 10
                ]
            );
        }
    }
}

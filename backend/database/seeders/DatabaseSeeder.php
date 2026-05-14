<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;
use App\Models\Material;
use App\Models\Inventory;
use App\Models\Printer;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@n3xt3d.com'],
            [
                'name' => 'Admin N3XT',
                'password' => 'N3xt_Admin!2026#'
            ]
        );

        $settings = [
            'infra' => ['luz_hr' => 926, 'depr_hr' => 400, 'mant_hr' => 700],
            'prep' => ['mano_obra_hr' => 1000],
            'oper' => ['transporte' => 50, 'ganancia' => 50, 'marketing' => 50, 'fallos' => 30],
            'margin' => ['iva' => 19],
            'security' => ['workshop_pin' => '2026']
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Semillas de Materiales e Inventario
        $materials = [
            ['id' => 'PLA_BLACK', 'name' => 'PLA Alta Resistencia', 'category' => 'FDM', 'cost_per_kg' => 85000, 'color' => '#000000'],
            ['id' => 'PLA_WHITE', 'name' => 'PLA Alta Resistencia', 'category' => 'FDM', 'cost_per_kg' => 85000, 'color' => '#FFFFFF'],
            ['id' => 'PETG_BLUE', 'name' => 'PETG Funcional', 'category' => 'FDM', 'cost_per_kg' => 95000, 'color' => '#0000FF'],
            ['id' => 'SLA_GRAY', 'name' => 'Resina Estándar', 'category' => 'SLA', 'cost_per_kg' => 250000, 'color' => '#808080'],
        ];

        foreach ($materials as $m) {
            $mat = Material::updateOrCreate(['id' => $m['id']], $m);
            Inventory::updateOrCreate(
                ['material_id' => $mat->id],
                ['stock_available' => 5000, 'low_stock_threshold' => 1000] // Iniciamos con 5kg de cada uno
            );
        }

        // Semillas de Impresoras
        $printers = [
            ['name' => 'Alpha-1', 'model' => 'Ender 3 V2', 'technology' => 'FDM'],
            ['name' => 'Alpha-2', 'model' => 'Ender 3 V2', 'technology' => 'FDM'],
            ['name' => 'Beta-1', 'model' => 'Prusa i3 MK3S+', 'technology' => 'FDM'],
            ['name' => 'Gamma-1', 'model' => 'Anycubic Photon', 'technology' => 'SLA'],
        ];

        foreach ($printers as $p) {
            Printer::updateOrCreate(['name' => $p['name']], $p);
        }
    }
}

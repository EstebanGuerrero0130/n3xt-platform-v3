<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Parámetros del cotizador por material: se crean, se editan y salen en GET /materials. */
class MaterialQuoteParamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_edit_and_list_quote_params(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/materials', [
            'id' => 'PETG_NEGRO', 'name' => 'PETG Negro', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g',
            'cost_per_kg' => 90000, 'initial_stock' => 1000, 'speed_factor' => 1.2, 'waste_pct' => 4,
        ])->assertCreated();

        $m = Material::findOrFail('PETG_NEGRO');
        $this->assertEqualsWithDelta(1.2, (float) $m->speed_factor, 1e-9);
        $this->assertEqualsWithDelta(4, (float) $m->waste_pct, 1e-9);
        $this->assertNull($m->failure_pct);

        // Vacío vuelve al valor por defecto
        $this->patchJson('/api/materials/PETG_NEGRO', ['speed_factor' => null, 'failure_pct' => 8])->assertOk();
        $m->refresh();
        $this->assertNull($m->speed_factor);
        $this->assertEqualsWithDelta(8, (float) $m->failure_pct, 1e-9);

        $this->patchJson('/api/materials/PETG_NEGRO', ['speed_factor' => 50])->assertStatus(422);

        $row = collect($this->getJson('/api/materials')->assertOk()->json('data'))->firstWhere('id', 'PETG_NEGRO');
        $this->assertArrayHasKey('waste_pct', $row);
        $this->assertArrayHasKey('failure_pct', $row);
    }

    public function test_inventory_staff_cannot_change_quote_params(): void
    {
        Material::create(['id' => 'PLA_X', 'name' => 'PLA X', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 80000, 'speed_factor' => 1.5]);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['inventory']]), 'web');

        $this->patchJson('/api/materials/PLA_X', ['speed_factor' => 0.1])->assertForbidden();
        // El modal manda todos los campos: si no cambian, se puede guardar lo demás
        $this->patchJson('/api/materials/PLA_X', ['name' => 'PLA Gris', 'speed_factor' => 1.5, 'waste_pct' => null])->assertOk();
        $this->assertSame('PLA Gris', Material::find('PLA_X')->name);
        $this->postJson('/api/materials', [
            'id' => 'PLA_Y', 'name' => 'PLA Y', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g',
            'cost_per_kg' => 80000, 'initial_stock' => 0, 'failure_pct' => 100,
        ])->assertForbidden();
    }

    public function test_public_list_hides_stock_and_location(): void
    {
        Material::create(['id' => 'PLA_Z', 'name' => 'PLA Z', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 80000, 'location' => 'Estante 3']);
        \App\Models\Inventory::create(['material_id' => 'PLA_Z', 'stock_available' => 750, 'low_stock_threshold' => 200]);

        $row = collect($this->getJson('/api/materials')->assertOk()->json('data'))->firstWhere('id', 'PLA_Z');
        $this->assertArrayNotHasKey('inventory', $row);
        $this->assertArrayNotHasKey('location', $row);
        $this->assertArrayHasKey('cost_per_kg', $row); // el cotizador del navegador lo usa

        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['kanban']]), 'web');
        $row = collect($this->getJson('/api/materials')->assertOk()->json('data'))->firstWhere('id', 'PLA_Z');
        $this->assertEquals(750, $row['inventory']['stock_available']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        return User::factory()->create(['role' => 'staff', 'permissions' => $permissions]);
    }

    public function test_existing_admins_are_owners_and_see_everything(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');
        $this->getJson('/api/user')->assertJsonPath('team_role', 'owner')->assertJsonFragment(['sections' => [...User::SECTIONS, 'team']]);
        $this->getJson('/api/admin/team')->assertOk();
        $this->getJson('/api/admin/purchases')->assertOk();
    }

    public function test_staff_only_reaches_its_sections(): void
    {
        $this->actingAs($this->staff(['kanban']), 'web');

        $this->getJson('/api/user')->assertJsonPath('team_role', 'staff')->assertJsonPath('sections', ['kanban']);
        $this->getJson('/api/admin/orders')->assertOk();
        $this->getJson('/api/admin/printers')->assertOk();
        $this->getJson('/api/admin/contacts/customers')->assertOk();

        $this->getJson('/api/admin/purchases')->assertForbidden();
        $this->getJson('/api/admin/analytics')->assertForbidden();
        $this->getJson('/api/admin/team')->assertForbidden();
        $this->getJson('/api/admin/backup/export')->assertForbidden();
        $this->postJson('/api/materials', [])->assertForbidden();
        $this->postJson('/api/admin/printers', [])->assertForbidden();
    }

    public function test_staff_settings_save_only_touches_its_keys_and_hides_coupons(): void
    {
        Setting::updateOrCreate(['key' => 'company'], ['value' => ['name' => 'N3XT']]);
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [['code' => 'SECRETO', 'discount_pct' => 10, 'active' => true]]]);
        $this->actingAs($this->staff(['web']), 'web');

        $this->getJson('/api/settings')->assertJsonMissingPath('coupons');
        $this->postJson('/api/admin/settings', ['settings' => [
            'web' => ['catalog' => []],
            'company' => ['name' => 'Hackeado'],
            'coupons' => [],
        ]])->assertOk();

        $this->assertSame(['catalog' => []], Setting::where('key', 'web')->value('value'));
        $this->assertSame('N3XT', Setting::where('key', 'company')->value('value')['name']);
        $this->assertCount(1, Setting::where('key', 'coupons')->value('value'));
    }

    public function test_staff_without_production_cannot_set_manual_prices(): void
    {
        Material::create(['id' => 'PLA_BLACK', 'name' => 'PLA', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000, 'density' => 1.24]);
        $this->actingAs($this->staff(['web']), 'web');

        // Sin producción se trata como un pedido público: exige archivo
        $this->postJson('/api/orders', [
            'customer_name' => 'X', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK',
            'volume_mm3' => 1, 'estimated_weight_g' => 1, 'total_price' => 1,
        ])->assertStatus(422);
    }

    public function test_owner_manages_team_and_keeps_one_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');

        $id = $this->postJson('/api/admin/team', [
            'name' => 'Operario', 'email' => 'Operario@Example.com', 'password' => 'clave-segura-1',
            'role' => 'staff', 'permissions' => ['kanban', 'machines', 'kanban'],
        ])->assertCreated()->assertJsonPath('data.permissions', ['kanban', 'machines'])->json('data.id');
        $this->assertSame('operario@example.com', User::find($id)->email);

        $this->postJson('/api/admin/team', ['name' => 'X', 'email' => 'y@example.com', 'password' => 'clave-segura-1', 'role' => 'staff', 'permissions' => ['root']])->assertStatus(422);

        $this->patchJson("/api/admin/team/{$id}", ['permissions' => ['inventory']])->assertOk();
        $this->assertSame(['inventory'], User::find($id)->permissions);

        // No puede quitarse el último dueño ni borrarse a sí mismo
        $this->patchJson("/api/admin/team/{$owner->id}", ['role' => 'staff'])->assertStatus(422);
        $this->deleteJson("/api/admin/team/{$owner->id}")->assertStatus(422);
        $this->deleteJson("/api/admin/team/{$id}")->assertOk();
        $this->assertNull(User::find($id));
    }
}

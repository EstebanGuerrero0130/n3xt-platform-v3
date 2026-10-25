<?php

namespace Tests\Feature;

use App\Models\RecurrentCustomer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): RecurrentCustomer
    {
        return RecurrentCustomer::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_guest_cannot_reach_admin_routes(): void
    {
        $this->getJson('/api/admin/orders')->assertUnauthorized();
    }

    public function test_customer_session_cannot_reach_admin_routes(): void
    {
        $this->actingAs($this->customer(), 'customer');

        $this->getJson('/api/admin/orders')->assertStatus(401);
        $this->getJson('/api/admin/backup/export')->assertStatus(401);
        $this->postJson('/api/admin/purge-all', ['confirm_purge' => 'PURGE_ALL_CONFIRMED'])->assertStatus(401);
    }

    public function test_admin_can_reach_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/admin/orders')->assertOk();
    }

    public function test_analytics_runs_on_sqlite(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson('/api/admin/analytics')->assertOk();
    }

    public function test_user_endpoint_reports_explicit_role(): void
    {
        $this->getJson('/api/user')->assertOk()->assertExactJson(['role' => null]);

        $this->actingAs($this->customer(), 'customer');
        $this->getJson('/api/user')->assertOk()->assertJson(['role' => 'customer'])->assertJsonMissing(['password']);
    }

    public function test_admin_user_endpoint_role(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'servicion3xt@gmail.com']), 'web');
        $this->getJson('/api/user')->assertOk()->assertJson(['role' => 'admin']);
    }

    public function test_customer_routes_use_customer_guard(): void
    {
        $this->getJson('/api/customer/profile')->assertUnauthorized();

        $this->actingAs($this->customer(), 'customer');
        $this->getJson('/api/customer/profile')->assertOk()->assertJson(['email' => 'cliente@example.com']);
    }

    public function test_public_settings_hide_private_keys(): void
    {
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [['code' => 'N3XT10', 'discount_pct' => 10]]]);
        Setting::updateOrCreate(['key' => 'security'], ['value' => ['workshop_pin' => '2026']]);
        Setting::updateOrCreate(['key' => 'web'], ['value' => ['title' => 'N3XT']]);

        $this->getJson('/api/settings')->assertOk()
            ->assertJsonMissingPath('coupons')
            ->assertJsonMissingPath('security')
            ->assertJsonPath('web.title', 'N3XT');

        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('coupons.0.code', 'N3XT10');
    }

    public function test_backup_export_excludes_password_hashes(): void
    {
        $this->customer();
        $this->actingAs(User::factory()->create(), 'web');

        $json = $this->get('/api/admin/backup/export')->assertOk()->json();
        $row = $json['payload']['recurrent_customers'][0];
        $this->assertSame('cliente@example.com', $row['email']);
        $this->assertArrayNotHasKey('password', $row);
    }

    public function test_backup_import_ignores_tables_outside_whitelist(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'web');

        $payload = json_encode(['payload' => [
            'users' => [['name' => 'Intruso', 'email' => 'evil@example.com', 'password' => 'x']],
            'settings' => [['key' => 'web', 'value' => json_encode(['title' => 'restaurado'])]],
        ]]);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('backup.json', $payload);

        $this->post('/api/admin/backup/import', ['backup_file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
        $this->assertSame(['title' => 'restaurado'], Setting::where('key', 'web')->first()->value);
    }
}

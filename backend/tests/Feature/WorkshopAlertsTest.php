<?php

namespace Tests\Feature;

use App\Mail\CustomerMail;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkshopAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.alerts.email' => 'taller@example.com', 'services.alerts.cron_secret' => 'cron-secret']);
        Mail::fake();
        Material::create(['id' => 'PLA_BLACK', 'name' => 'PLA Negro', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000]);
        Inventory::create(['material_id' => 'PLA_BLACK', 'stock_available' => 1000, 'low_stock_threshold' => 500]);
        $this->actingAs(User::factory()->create(), 'web');
    }

    private function sentAlerts(): int
    {
        return Mail::sent(CustomerMail::class, fn ($m) => $m->mailView === 'mail.workshop-alert')->count();
    }

    public function test_low_stock_alerts_once_until_restocked(): void
    {
        $this->postJson('/api/materials/PLA_BLACK/stock', ['stock_available' => 300])->assertOk();
        $this->assertSame(1, $this->sentAlerts());
        Mail::assertSent(CustomerMail::class, fn ($m) => $m->hasTo('taller@example.com') && str_contains($m->mailSubject, 'stock bajo de PLA Negro'));

        // Sigue bajo: no repite
        $this->postJson('/api/materials/PLA_BLACK/stock', ['stock_available' => 200])->assertOk();
        $this->assertSame(1, $this->sentAlerts());

        // Repuesto y vuelve a bajar: avisa de nuevo
        $this->postJson('/api/materials/PLA_BLACK/stock', ['stock_available' => 2000])->assertOk();
        $this->assertNull(Inventory::first()->low_stock_alerted_at);
        $this->postJson('/api/materials/PLA_BLACK/stock', ['stock_available' => 100])->assertOk();
        $this->assertSame(2, $this->sentAlerts());
    }

    public function test_maintenance_alert_by_hours_and_daily_cron(): void
    {
        $printer = Printer::create(['name' => 'Ender', 'model' => 'E3', 'technology' => 'FDM', 'status' => 'idle', 'maintenance_interval_h' => 200, 'total_hours_run' => 199]);
        $this->postJson("/api/admin/printers/{$printer->id}/reset")->assertOk();
        $this->assertSame(0, $this->sentAlerts());

        $printer->update(['total_hours_run' => 201]);
        $this->postJson("/api/admin/printers/{$printer->id}/reset")->assertOk();
        Mail::assertSent(CustomerMail::class, fn ($m) => str_contains($m->mailSubject, 'mantenimiento de Ender'));
        $this->assertNotNull($printer->fresh()->maintenance_alerted_at);

        // Registrar el mantenimiento limpia la marca
        $this->postJson("/api/admin/printers/{$printer->id}/maintenance-complete")->assertOk();
        $this->assertNull($printer->fresh()->maintenance_alerted_at);

        // El cron diario avisa por fecha y exige el secreto
        $printer->forceFill(['next_maintenance' => now()->subDay()->toDateString()])->saveQuietly();
        $this->getJson('/api/cron/alerts')->assertUnauthorized();
        $this->getJson('/api/cron/alerts', ['Authorization' => 'Bearer cron-secret'])
            ->assertOk()->assertJson(['maintenance' => 1]);
    }
}

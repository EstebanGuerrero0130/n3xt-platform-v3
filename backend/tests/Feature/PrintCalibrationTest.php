<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\User;
use App\Services\PrintCalibration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PrintCalibrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Material::create(['id' => 'PLA_BLACK', 'name' => 'PLA Negro', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000]);
        Material::create(['id' => 'RESIN', 'name' => 'Resina', 'category' => 'SLA', 'type' => 'material', 'unit' => 'ml', 'cost_per_kg' => 200000]);
    }

    /** Impresión terminada: estimado en horas, real en horas. */
    private function print(float $estimated, float $actual, array $extra = []): Order
    {
        $start = now()->subDays(2);
        return Order::create(array_merge([
            'customer_name' => 'Cliente', 'customer_email' => 'c@example.com', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK',
            'material_name' => 'PLA Negro', 'volume_mm3' => 1, 'qty' => 1, 'total_price' => 1, 'status' => 'completed',
            'estimated_duration_h' => $estimated,
            'printing_started_at' => $start,
            'printing_finished_at' => $start->copy()->addSeconds((int) round($actual * 3600)),
        ], $extra));
    }

    private function staff(array $permissions): User
    {
        // El guard sanctum recuerda al usuario anterior dentro del mismo test
        $this->app['auth']->forgetGuards();
        return User::factory()->create(['role' => 'staff', 'permissions' => $permissions]);
    }

    public function test_status_changes_record_start_and_finish(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $printer = Printer::create(['name' => 'Ender', 'model' => 'E3', 'technology' => 'FDM', 'status' => 'idle']);
        $order = Order::create(['customer_name' => 'X', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK', 'volume_mm3' => 1, 'qty' => 1, 'total_price' => 1, 'status' => 'pending', 'estimated_duration_h' => 2]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'printing', 'printer_id' => $printer->id])->assertOk();
        $this->assertNotNull($order->fresh()->printing_started_at);
        $this->assertNull($order->fresh()->printing_finished_at);

        $this->travel(3)->hours();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'post-processing'])->assertOk();
        $fresh = $order->fresh();
        $this->assertEqualsWithDelta(3 * 3600, $fresh->printing_started_at->diffInSeconds($fresh->printing_finished_at), 5);

        // Más adelante en el flujo no se mueve
        $this->travel(1)->hours();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertOk();
        $this->assertEquals($fresh->printing_finished_at, $order->fresh()->printing_finished_at);
    }

    public function test_cancelling_a_print_does_not_record_finish(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $order = Order::create(['customer_name' => 'X', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK', 'volume_mm3' => 1, 'qty' => 1, 'total_price' => 1, 'status' => 'pending']);
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'printing'])->assertOk();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();
        $this->assertNull($order->fresh()->printing_finished_at);
    }

    public function test_stats_median_outliers_and_min_samples(): void
    {
        foreach ([1.1, 1.2, 1.3, 1.4] as $r) {
            $this->print(10, 10 * $r);
        }
        // Atípicos y descartes: no cuentan
        $this->print(10, 40);                               // 4x
        $this->print(10, 2);                                // 0.2x
        $this->print(0.1, 0.1);                             // menos de 10 min
        $this->print(10, 10, ['technology' => 'CATALOGO']);
        $this->print(10, 10, ['status' => 'cancelled']);
        $this->print(0, 5);

        $stats = (new PrintCalibration)->stats();
        $fdm = collect($stats['by_technology'])->firstWhere('technology', 'FDM');
        $this->assertSame(4, $fdm['samples']);
        $this->assertEqualsWithDelta(1.25, $fdm['median_ratio'], 0.001);
        $this->assertNull($fdm['suggested_factor']); // faltan muestras

        $this->print(10, 15);
        $stats = (new PrintCalibration)->stats();
        $fdm = collect($stats['by_technology'])->firstWhere('technology', 'FDM');
        $this->assertSame(5, $fdm['samples']);
        $this->assertSame(1.3, $fdm['suggested_factor']);
        $this->assertCount(1, $stats['by_technology']);
        $mat = collect($stats['by_material'])->firstWhere('material_id', 'PLA_BLACK');
        $this->assertSame(5, $mat['samples']);
        $this->assertSame(1.3, $mat['suggested_factor']);
    }

    public function test_raw_ratio_undoes_the_factor_used_at_quote_time(): void
    {
        // Cotizado con factor 1.2: estimado 12 h (crudo 10 h); tardó 15 h → razón cruda 1.5
        foreach (range(1, 5) as $i) {
            $this->print(12, 15, ['cost_snapshot' => ['calibration_factor' => 1.2]]);
        }
        $fdm = collect((new PrintCalibration)->stats()['by_technology'])->firstWhere('technology', 'FDM');
        $this->assertEqualsWithDelta(1.5, $fdm['median_ratio'], 0.001);
        $this->assertSame(1.5, $fdm['suggested_factor']);
    }

    public function test_permissions(): void
    {
        $this->actingAs($this->staff(['kanban']), 'web');
        $this->getJson('/api/admin/calibration')->assertForbidden();
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 1.1]])->assertForbidden();

        $this->actingAs($this->staff(['machines']), 'web');
        $this->getJson('/api/admin/calibration')->assertOk()->assertJsonStructure(['data' => ['by_technology', 'by_material', 'by_printer', 'applied' => ['time_factor', 'by_material'], 'min_samples']]);
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 1.1]])->assertForbidden();
        $this->assertNull(Setting::where('key', 'calibration')->first());

        $this->actingAs($this->staff(['settings']), 'web');
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 1.1]])->assertOk();
    }

    public function test_stats_never_include_customer_data(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        foreach (range(1, 5) as $i) {
            $this->print(10, 11, ['customer_name' => 'Laura Secreta', 'customer_email' => 'laura@secreta.com']);
        }
        $body = $this->getJson('/api/admin/calibration')->assertOk()->getContent();
        $this->assertStringNotContainsString('Laura', $body);
        $this->assertStringNotContainsString('secreta.com', $body);
    }

    public function test_apply_and_reset_write_exact_shape(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 1.234]])
            ->assertOk()->assertJsonPath('data.applied.time_factor.FDM', 1.23);
        $this->assertSame(['time_factor' => ['FDM' => 1.23]], Setting::where('key', 'calibration')->value('value'));

        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['SLA' => 0.9], 'by_material' => ['PLA_BLACK' => 1.05]])->assertOk();
        $this->assertSame(['time_factor' => ['FDM' => 1.23, 'SLA' => 0.9], 'by_material' => ['PLA_BLACK' => 1.05]], Setting::where('key', 'calibration')->value('value'));

        // Quitar
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => null, 'SLA' => null], 'by_material' => ['PLA_BLACK' => null]])->assertOk();
        $raw = Setting::where('key', 'calibration')->first()->getRawOriginal('value');
        $this->assertSame('{"time_factor":{}}', $raw);
        $this->getJson('/api/admin/calibration')->assertOk()->assertJsonPath('data.applied.time_factor', []);
    }

    public function test_apply_validates_input(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 3.5]])->assertStatus(422);
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 0.2]])->assertStatus(422);
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['CATALOGO' => 1]])->assertStatus(422);
        $this->postJson('/api/admin/calibration/apply', ['by_material' => ['NO_EXISTE' => 1]])->assertStatus(422);
        $this->postJson('/api/admin/calibration/apply', [])->assertStatus(422);
        $this->assertNull(Setting::where('key', 'calibration')->first());
    }

    public function test_general_settings_save_cannot_overwrite_calibration(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/admin/calibration/apply', ['time_factor' => ['FDM' => 1.2]])->assertOk();
        $this->postJson('/api/admin/settings', ['settings' => ['calibration' => ['time_factor' => ['FDM' => 9]]]])->assertOk();
        $this->assertSame(['time_factor' => ['FDM' => 1.2]], Setting::where('key', 'calibration')->value('value'));
    }
}

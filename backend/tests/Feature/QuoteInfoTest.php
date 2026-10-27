<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuoteInfoTest extends TestCase
{
    use RefreshDatabase;

    private function printer(array $attrs): Printer
    {
        return Printer::create(array_merge(['name' => 'Máquina', 'model' => 'M', 'technology' => 'FDM', 'status' => 'idle'], $attrs));
    }

    private function order(array $attrs): Order
    {
        return Order::forceCreate(array_merge([
            'customer_name' => 'Laura Secreta', 'customer_email' => 'laura@example.com', 'volume_mm3' => 0, 'qty' => 1,
            'technology' => 'FDM', 'material_id' => 'PLA', 'total_price' => 50000, 'status' => 'pending',
            'scale_factor' => 1, 'estimated_duration_h' => 0, 'extras_cost' => 0, 'stock_deducted' => false,
            'is_paid' => false, 'hours_added_to_printer' => false,
        ], $attrs));
    }

    public function test_printers_have_build_volume_columns_and_validation(): void
    {
        foreach (['build_x_mm', 'build_y_mm', 'build_z_mm'] as $col) {
            $this->assertTrue(Schema::hasColumn('printers', $col));
        }
        $this->actingAs(User::factory()->create(), 'web');

        $id = $this->postJson('/api/admin/printers', ['name' => 'Bambu', 'model' => 'X1', 'technology' => 'FDM', 'build_x_mm' => 256, 'build_y_mm' => 256, 'build_z_mm' => 256])
            ->assertCreated()->json('data.id');
        $this->assertSame(256, (int) Printer::find($id)->build_z_mm);

        // Opcional: se puede crear sin medidas
        $this->postJson('/api/admin/printers', ['name' => 'Ender', 'model' => 'E3', 'technology' => 'FDM'])->assertCreated();

        $this->patchJson("/api/admin/printers/{$id}", ['build_x_mm' => 0])->assertStatus(422);
        $this->patchJson("/api/admin/printers/{$id}", ['build_x_mm' => 2001])->assertStatus(422);
        $this->patchJson("/api/admin/printers/{$id}", ['build_x_mm' => 300, 'build_z_mm' => null])->assertOk();
        $fresh = Printer::find($id);
        $this->assertSame(300, (int) $fresh->build_x_mm);
        $this->assertNull($fresh->build_z_mm);
    }

    public function test_capacity_uses_defaults_without_dimensions(): void
    {
        $this->printer(['technology' => 'SLA']);
        $this->getJson('/api/quote/capacity')->assertOk()->assertExactJson([
            'status' => 'Success', 'message' => null,
            'data' => ['FDM' => ['x' => 256, 'y' => 256, 'z' => 256], 'SLA' => ['x' => 218, 'y' => 123, 'z' => 250]],
        ]);
    }

    public function test_capacity_takes_largest_non_offline_printer(): void
    {
        $this->printer(['name' => 'Chica', 'build_x_mm' => 220, 'build_y_mm' => 220, 'build_z_mm' => 250]);
        $this->printer(['name' => 'Grande', 'status' => 'maintenance', 'build_x_mm' => 350, 'build_y_mm' => 350, 'build_z_mm' => 350]);
        $this->printer(['name' => 'Apagada', 'status' => 'offline', 'build_x_mm' => 1000, 'build_y_mm' => 1000, 'build_z_mm' => 1000]);
        $this->printer(['name' => 'Resina', 'technology' => 'SLA', 'status' => 'offline', 'build_x_mm' => 300, 'build_y_mm' => 300, 'build_z_mm' => 300]);

        $res = $this->getJson('/api/quote/capacity')->assertOk();
        $this->assertSame(['x' => 350, 'y' => 350, 'z' => 350], $res->json('data.FDM'));
        // La única SLA con medidas está apagada: valores por defecto
        $this->assertSame(['x' => 218, 'y' => 123, 'z' => 250], $res->json('data.SLA'));
        $this->assertStringNotContainsString('Grande', $res->getContent());
    }

    public function test_lead_time_follows_the_queue_and_urgent_jumps_it(): void
    {
        $this->travelTo('2026-10-28T10:00:00Z');
        $ender = $this->printer(['name' => 'Ender', 'status' => 'printing']);
        $this->printer(['name' => 'Bambu']);
        $this->order(['status' => 'printing', 'printer_id' => $ender->id, 'estimated_duration_h' => 4, 'printing_started_at' => '2026-10-28 08:00:00']);
        $this->order(['estimated_duration_h' => 30, 'created_at' => '2026-10-27 08:00:00']);
        $this->order(['estimated_duration_h' => 30, 'created_at' => '2026-10-27 09:00:00']);

        // Bambu 10:00 + 30 h y Ender 12:00 + 30 h: el nuevo (10 h) espera hasta el 29 a las 16:00 UTC
        $normal = $this->getJson('/api/quote/lead-time?technology=FDM&hours=10')->assertOk();
        $this->assertSame(['date' => '2026-10-30', 'label' => 'viernes 30 de octubre'], $normal->json('data'));

        // Urgente: justo después de lo que se imprime (Ender libre a las 12:00)
        $urgent = $this->getJson('/api/quote/lead-time?technology=FDM&hours=10&urgent=1')->assertOk();
        $this->assertSame(['date' => '2026-10-29', 'label' => 'jueves 29 de octubre'], $urgent->json('data'));

        // Sin datos de pedidos, clientes ni máquinas
        foreach ([$normal, $urgent] as $res) {
            $body = $res->getContent();
            foreach (['Laura', 'laura@example.com', 'Ender', 'Bambu', 'printer', 'order'] as $secret) {
                $this->assertStringNotContainsString($secret, $body);
            }
        }
    }

    public function test_urgent_lead_time_waits_for_other_urgent_orders(): void
    {
        $this->travelTo('2026-10-28T10:00:00Z');
        $this->printer(['name' => 'Bambu']);
        $this->order(['estimated_duration_h' => 40, 'created_at' => '2026-10-27 08:00:00']);
        $this->order(['estimated_duration_h' => 20, 'created_at' => '2026-10-27 09:00:00', 'is_urgent' => true]);

        // Urgente: después del otro urgente, no del normal (10:00 + 20 h + 10 h = 29 16:00 UTC)
        $this->getJson('/api/quote/lead-time?technology=FDM&hours=10&urgent=1')->assertOk()
            ->assertJsonPath('data.date', '2026-10-30');
        // Normal: después de toda la cola (urgente incluido): 10:00 + 70 h = 31 08:00 UTC
        $this->getJson('/api/quote/lead-time?technology=FDM&hours=10')->assertOk()
            ->assertJsonPath('data.date', '2026-11-02');
    }

    public function test_lead_time_uses_post_hours_setting_and_skips_sunday(): void
    {
        $this->travelTo('2026-10-30T15:00:00Z'); // viernes
        $this->printer(['name' => 'Bambu']);
        Setting::create(['key' => 'lead_time', 'value' => ['post_hours' => 48]]);

        // Viernes + 2 días → sábado, domingo no cuenta → lunes 2
        $this->getJson('/api/quote/lead-time?technology=FDM&hours=1')
            ->assertOk()->assertJsonPath('data.date', '2026-11-02')->assertJsonPath('data.label', 'lunes 2 de noviembre');
    }

    public function test_lead_time_without_available_printer_or_bad_input(): void
    {
        $this->printer(['technology' => 'SLA', 'status' => 'offline']);
        $this->getJson('/api/quote/lead-time?technology=SLA&hours=3')->assertOk()->assertJsonPath('data', ['date' => null, 'label' => null]);
        $this->getJson('/api/quote/lead-time?technology=CATALOGO&hours=3')->assertStatus(422);
        $this->getJson('/api/quote/lead-time?technology=FDM&hours=-1')->assertStatus(422);
    }
}

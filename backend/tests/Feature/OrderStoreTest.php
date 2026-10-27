<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Material;
use App\Models\Order;
use App\Models\RecurrentCustomer;
use App\Mail\CustomerMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MeshVolumeService;
use App\Services\QuotePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Setting::updateOrCreate(['key' => 'infra'], ['value' => ['luz_hr' => 926, 'depr_hr' => 400, 'mant_hr' => 700]]);
        Setting::updateOrCreate(['key' => 'prep'], ['value' => ['mano_obra_hr' => 1000]]);
        Setting::updateOrCreate(['key' => 'oper'], ['value' => ['transporte' => 50, 'ganancia' => 50, 'marketing' => 50, 'fallos' => 30]]);
        Setting::updateOrCreate(['key' => 'margin'], ['value' => ['iva' => 19]]);
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [['code' => 'N3XT10', 'discount_pct' => 10, 'label' => 'Bienvenida', 'active' => true]]]);

        Material::create(['id' => 'PLA_BLACK', 'name' => 'PLA Negro', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000, 'density' => 1.24]);
        Material::create(['id' => 'RES_GREY', 'name' => 'Resina Gris', 'category' => 'SLA', 'type' => 'material', 'unit' => 'ml', 'cost_per_kg' => 120000, 'density' => 1.1]);
        Material::create(['id' => 'Caja_Piez_02', 'name' => 'Caja', 'category' => 'FDM', 'type' => 'utility', 'unit' => 'pieza', 'cost_per_kg' => 900]);
        Inventory::create(['material_id' => 'Caja_Piez_02', 'stock_available' => 10]);
    }

    /** Cubo de lado $l mm en STL ASCII (12 triángulos, normales hacia fuera). */
    private function cubeStl(float $l = 20): string
    {
        $v = [[0, 0, 0], [$l, 0, 0], [$l, $l, 0], [0, $l, 0], [0, 0, $l], [$l, 0, $l], [$l, $l, $l], [0, $l, $l]];
        $faces = [[0, 3, 2], [0, 2, 1], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [1, 2, 6], [1, 6, 5], [0, 4, 7], [0, 7, 3]];
        $out = "solid cube\n";
        foreach ($faces as $f) {
            $out .= "facet normal 0 0 0\nouter loop\n";
            foreach ($f as $i) {
                $out .= 'vertex ' . implode(' ', $v[$i]) . "\n";
            }
            $out .= "endloop\nendfacet\n";
        }
        return $out . "endsolid cube\n";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'technology' => 'FDM',
            'material_id' => 'PLA_BLACK',
            'infill' => 20,
            'layer_height' => 0.2,
            'models' => [
                ['file' => UploadedFile::fake()->createWithContent('cubo.stl', $this->cubeStl()), 'qty' => 2, 'scale_factor' => 1],
            ],
        ], $overrides);
    }

    public function test_public_order_is_priced_by_server_and_ignores_client_price(): void
    {
        $res = $this->post('/api/orders', $this->payload(['total_price' => 1, 'volume_mm3' => 1]), ['Accept' => 'application/json']);

        $res->assertCreated();
        $order = Order::findOrFail($res->json('data.order_id'));

        $this->assertEqualsWithDelta(8000 * 2, $order->volume_mm3, 0.01);
        $this->assertGreaterThan(1000, $order->total_price);
        $this->assertSame((float)$res->json('data.total_price'), (float)$order->total_price);
        $this->assertSame(2, $order->qty);
        $this->assertNotNull($order->file_path);
        Storage::disk('local')->assertExists($order->file_path);
        $this->assertCount(1, $order->models);
        $this->assertSame('cubo.stl', $order->models[0]['original_filename']);
        $this->assertArrayHasKey('path', $order->models[0]);
    }

    public function test_server_price_matches_pricing_service(): void
    {
        $res = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->assertCreated();

        $mesh = (new MeshVolumeService())->analyze(Storage::disk('local')->path(Order::first()->file_path), 'stl');
        $svc = app(QuotePricingService::class);
        $settings = $svc->loadSettings();
        // Mismos datos que el controlador: áreas por eje, bbox (las 2 piezas comparten cama) y cama por defecto
        $quote = $svc->quote(
            ['technology' => 'FDM', 'infill' => 20, 'layer_height' => 0.2, 'bed' => $svc->loadBeds($settings)['FDM'], 'models' => [
                QuotePricingService::pricingModelFromMesh($mesh, ['qty' => 2, 'scale_factor' => 1]),
            ]],
            $settings,
            ['id' => 'PLA_BLACK', 'cost_per_kg' => 85000, 'density' => 1.24],
            $svc->loadUtilities()
        );

        $this->assertSame($quote['total'], $res->json('data.total_price'));
        $this->assertSame(1, $res->json('data.breakdown.beds'));
        $this->assertSame('y', $res->json('data.breakdown.models.0.orientation'));
        $order = Order::first();
        $this->assertSame('y', $order->models[0]['orientation']);
        $this->assertSame(1.0, (float) $order->cost_snapshot['calibration_factor']);
    }

    public function test_urgent_flag_applies_rush_surcharge_and_calibration_is_recorded(): void
    {
        Setting::updateOrCreate(['key' => 'rules'], ['value' => ['rush_pct' => 20]]);
        Setting::updateOrCreate(['key' => 'calibration'], ['value' => ['time_factor' => ['FDM' => 1.25]]]);
        $plain = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $urgent = $this->post('/api/orders', $this->payload(['urgent' => '1']), ['Accept' => 'application/json'])->assertCreated();

        $this->assertGreaterThan($plain->json('data.total_price'), $urgent->json('data.total_price'));
        $this->assertSame('rush', $urgent->json('data.breakdown.adjustments.0.type'));
        $this->assertTrue($urgent->json('data.breakdown.urgent'));
        $this->assertSame(1.25, (float) Order::latest('id')->first()->cost_snapshot['calibration_factor']);

        $this->post('/api/orders', $this->payload(['urgent' => 'quizás']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('urgent');
    }

    public function test_printer_bed_size_limits_pieces_per_bed(): void
    {
        // Cama de 30×30: cabe un solo cubo de 20 mm (con su separación) por cama
        \App\Models\Printer::create(['name' => 'Mini', 'model' => 'A1 mini', 'watts' => 150, 'technology' => 'FDM', 'status' => 'idle', 'build_x_mm' => 30, 'build_y_mm' => 30, 'build_z_mm' => 30]);
        $res = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(2, $res->json('data.breakdown.beds'));
        $this->assertSame(['x' => 30, 'y' => 30, 'z' => 30], array_map('intval', $res->json('data.breakdown.bed')));
    }

    public function test_coupon_is_applied_server_side(): void
    {
        $plain = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->json('data.breakdown');
        $withCoupon = $this->post('/api/orders', $this->payload(['coupon_code' => 'n3xt10']), ['Accept' => 'application/json'])->json('data.breakdown');

        $this->assertNull($plain['coupon']);
        $this->assertSame('N3XT10', $withCoupon['coupon']['code']);
        $this->assertLessThan($plain['total'], $withCoupon['total']);
    }

    public function test_extras_do_not_touch_stock_until_paid(): void
    {
        $res = $this->post('/api/orders', $this->payload([
            'extra_items' => [['material_id' => 'Caja_Piez_02', 'qty' => 3]],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(10.0, Inventory::where('material_id', 'Caja_Piez_02')->first()->stock_available);

        $order = Order::findOrFail($res->json('data.order_id'));
        $this->assertSame(2700.0, (float)$order->extras_cost);

        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson("/api/admin/orders/{$order->id}/toggle-paid")->assertOk();
        $this->assertSame(7.0, Inventory::where('material_id', 'Caja_Piez_02')->first()->stock_available);
    }

    public function test_invalid_mesh_is_rejected_with_422(): void
    {
        $this->post('/api/orders', $this->payload([
            'models' => [['file' => UploadedFile::fake()->createWithContent('roto.stl', "solid x\nendsolid x\n")]],
        ]), ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertEmpty(Storage::disk('local')->allFiles('orders_models'));
    }

    public function test_material_must_exist_and_match_technology(): void
    {
        $this->post('/api/orders', $this->payload(['material_id' => 'NOPE']), ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/orders', $this->payload(['material_id' => 'RES_GREY']), ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/orders', $this->payload(['material_id' => 'Caja_Piez_02']), ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_public_order_requires_a_model(): void
    {
        $this->post('/api/orders', $this->payload(['models' => null]), ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_admin_manual_order_keeps_its_own_price(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $res = $this->postJson('/api/orders', [
            'customer_name' => 'Taller',
            'technology' => 'FDM',
            'material_id' => 'PLA_BLACK',
            'volume_mm3' => 1000,
            'estimated_weight_g' => 15,
            'total_price' => 42000,
        ])->assertCreated();

        $this->assertSame(42000.0, (float)Order::findOrFail($res->json('data.order_id'))->total_price);
    }

    public function test_admin_manual_order_with_3d_file(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $res = $this->post('/api/orders', [
            'customer_name' => 'Taller',
            'technology' => 'FDM',
            'material_id' => 'PLA_BLACK',
            'volume_mm3' => 1000,
            'estimated_weight_g' => 15,
            'total_price' => 42000,
            'file' => UploadedFile::fake()->createWithContent('soporte.3mf', 'PK-fake-3mf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $order = Order::findOrFail($res->json('data.order_id'));
        $this->assertSame(42000.0, (float) $order->total_price);
        $this->assertSame('soporte.3mf', $order->original_filename);
        Storage::disk('local')->assertExists($order->file_path);
        $this->get("/api/admin/orders/{$order->id}/download")->assertOk();
    }

    public function test_moving_to_printing_records_start_time(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $printer = \App\Models\Printer::create(['name' => 'Ender', 'model' => 'E3', 'technology' => 'FDM', 'status' => 'idle']);
        $id = $this->postJson('/api/orders', [
            'customer_name' => 'Taller', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK',
            'volume_mm3' => 1000, 'estimated_weight_g' => 15, 'total_price' => 42000, 'estimated_duration_h' => 3,
        ])->json('data.order_id');

        $this->travelTo(now()->setTime(9, 30));
        $this->patchJson("/api/admin/orders/{$id}/status", ['status' => 'printing', 'printer_id' => $printer->id])->assertOk();
        $started = Order::findOrFail($id)->printing_started_at;
        $this->assertSame('09:30', $started->format('H:i'));

        // Seguir en impresión no reinicia la hora
        $this->travel(2)->hours();
        $this->patchJson("/api/admin/orders/{$id}/status", ['status' => 'printing', 'printer_id' => $printer->id])->assertOk();
        $this->assertTrue($started->equalTo(Order::findOrFail($id)->printing_started_at));
    }

    public function test_coupon_endpoint(): void
    {
        $this->postJson('/api/quote/coupon', ['code' => 'n3xt10'])->assertOk()
            ->assertJsonPath('data.code', 'N3XT10')
            ->assertJsonPath('data.discount_pct', 10);
        $this->postJson('/api/quote/coupon', ['code' => 'FAKE'])->assertNotFound();
    }

    public function test_customer_sees_only_own_orders(): void
    {
        $this->post('/api/orders', $this->payload(['customer_email' => 'Ana@Example.com']), ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/orders', $this->payload(['customer_email' => 'otro@example.com']), ['Accept' => 'application/json'])->assertCreated();

        $this->getJson('/api/customer/orders')->assertUnauthorized();

        $customer = \App\Models\RecurrentCustomer::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('secret123')]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($customer, 'customer');

        $res = $this->getJson('/api/customer/orders')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertArrayNotHasKey('customer_phone', $res->json('data.0'));
        $this->assertArrayNotHasKey('cost_snapshot', $res->json('data.0'));
    }

    public function test_admin_can_show_single_order(): void
    {
        $id = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->json('data.order_id');

        $this->getJson("/api/admin/orders/{$id}")->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson("/api/admin/orders/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    }

    private function welcomeOwner(): RecurrentCustomer
    {
        $c = RecurrentCustomer::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('secret123')]);
        $c->forceFill(['email_verified_at' => now(), 'welcome_coupon' => 'BIENVENIDATEST01'])->save();
        return $c;
    }

    public function test_welcome_coupon_applies_once_and_only_with_owner_email(): void
    {
        $owner = $this->welcomeOwner();
        $full = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->json('data.total_price');

        // Otro correo: rechazado
        $this->post('/api/orders', $this->payload(['customer_email' => 'otro@example.com', 'coupon_code' => 'BIENVENIDATEST01']), ['Accept' => 'application/json'])
            ->assertStatus(422);

        // Dueño: aplica el 10 %
        $res = $this->post('/api/orders', $this->payload(['coupon_code' => 'bienvenidatest01']), ['Accept' => 'application/json'])->assertCreated();
        $this->assertLessThan($full, $res->json('data.total_price'));
        $this->assertNotNull($owner->fresh()->welcome_coupon_used_at);

        // Segundo uso: ya no existe para el cotizador
        $this->post('/api/orders', $this->payload(['coupon_code' => 'BIENVENIDATEST01']), ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.total_price', $full);
        $this->postJson('/api/quote/coupon', ['code' => 'BIENVENIDATEST01'])->assertNotFound();
    }

    public function test_customer_gets_mail_when_order_is_received_and_status_changes(): void
    {
        Mail::fake();
        $id = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->assertCreated()->json('data.order_id');
        Mail::assertSent(CustomerMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->mailView === 'mail.order-update' && str_contains($m->mailSubject, 'Recibimos'));

        $this->actingAs(User::factory()->create(), 'web');
        $this->patchJson("/api/admin/orders/{$id}/status", ['status' => 'printing'])->assertOk();
        Mail::assertSent(CustomerMail::class, fn ($m) => str_contains($m->mailSubject, 'en impresión'));

        // Mismo estado otra vez: sin correo nuevo
        $this->patchJson("/api/admin/orders/{$id}/status", ['status' => 'printing'])->assertOk();
        Mail::assertSent(CustomerMail::class, 2);
    }

    public function test_coupon_max_uses_and_hyphen_codes(): void
    {
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [
            ['code' => 'N3XT-AB12', 'discount_pct' => 15, 'label' => 'Feria', 'active' => true, 'max_uses' => 1],
        ]]);

        $this->postJson('/api/quote/coupon', ['code' => 'n3xt-ab12'])->assertOk()->assertJsonPath('data.discount_pct', 15);
        $this->post('/api/orders', $this->payload(['coupon_code' => 'N3XT-AB12']), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.breakdown.coupon.code', 'N3XT-AB12');

        // Agotado tras el primer uso
        $this->assertSame(['N3XT-AB12' => 1], Setting::where('key', 'coupon_uses')->first()->value);
        $this->postJson('/api/quote/coupon', ['code' => 'N3XT-AB12'])->assertNotFound();
    }

    public function test_admin_cannot_overwrite_coupon_uses_and_coupons_stay_private(): void
    {
        Setting::updateOrCreate(['key' => 'coupon_uses'], ['value' => ['N3XT10' => 7]]);
        $this->getJson('/api/settings')->assertJsonMissingPath('coupons')->assertJsonMissingPath('coupon_uses');

        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/admin/settings', ['settings' => ['coupon_uses' => ['N3XT10' => 0]]])->assertOk();
        $this->assertSame(['N3XT10' => 7], Setting::where('key', 'coupon_uses')->first()->value);
    }

    public function test_legacy_admin_discounts_are_merged_into_coupons(): void
    {
        Setting::updateOrCreate(['key' => 'discounts'], ['value' => [
            ['code' => 'N3XT-OLD1', 'type' => 'percentage', 'value' => 20, 'status' => 'active', 'max_uses' => 5],
            ['code' => 'FIJO', 'type' => 'fixed', 'value' => 5000, 'status' => 'active'],
            ['code' => 'n3xt10', 'type' => 'percentage', 'value' => 50, 'status' => 'active'],
        ]]);

        (require database_path('migrations/2026_10_26_000002_merge_admin_discounts_into_coupons.php'))->up();

        $codes = collect(Setting::where('key', 'coupons')->first()->value)->pluck('discount_pct', 'code');
        $this->assertSame(20.0, (float) $codes['N3XT-OLD1']);
        $this->assertArrayNotHasKey('FIJO', $codes->all());
        $this->assertSame(10.0, (float) $codes['N3XT10']); // el existente no se pisa
    }

    public function test_coupons_are_capped_at_fifty_percent(): void
    {
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [['code' => 'TODOGRATIS', 'discount_pct' => 100, 'active' => true]]]);
        $this->postJson('/api/quote/coupon', ['code' => 'TODOGRATIS'])->assertOk()->assertJsonPath('data.discount_pct', 50);
        $total = $this->post('/api/orders', $this->payload(['coupon_code' => 'TODOGRATIS']), ['Accept' => 'application/json'])->json('data.total_price');
        $this->assertGreaterThan(0, $total);
    }

    public function test_customer_can_repeat_own_3d_order_with_copied_files_and_no_coupon(): void
    {
        $plain = $this->post('/api/orders', $this->payload(), ['Accept' => 'application/json'])->json('data.total_price');
        $id = $this->post('/api/orders', $this->payload(['coupon_code' => 'N3XT10']), ['Accept' => 'application/json'])->assertCreated()->json('data.order_id');

        $ana = RecurrentCustomer::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('secret123')]);
        $ana->forceFill(['email_verified_at' => now()])->save();
        $otro = RecurrentCustomer::create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => bcrypt('secret123')]);
        $otro->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($otro, 'customer')->postJson("/api/customer/orders/{$id}/repeat")->assertNotFound();

        $res = $this->actingAs($ana, 'customer')->postJson("/api/customer/orders/{$id}/repeat")->assertCreated();
        $this->assertEquals($plain, $res->json('data.total_price'));

        $original = Order::find($id);
        $copy = Order::find($res->json('data.order_id'));
        $this->assertNotSame($original->models[0]['path'], $copy->models[0]['path']);
        Storage::disk('local')->assertExists($copy->models[0]['path']);
        $this->assertSame('pending', $copy->status);
        $this->assertStringContainsString("#{$id}", $copy->comments);
    }
}

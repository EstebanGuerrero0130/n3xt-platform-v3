<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerAuthController;
use App\Models\Material;
use App\Models\Order;
use App\Models\RecurrentCustomer;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Defectos encontrados en la auditoría final (octubre 2026). */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_prices_in_colombian_format_are_thousands(): void
    {
        $this->assertSame(185000.0, CustomerAuthController::price('185.000'));
        $this->assertSame(1250000.0, CustomerAuthController::price('$1.250.000'));
        $this->assertSame(45000.0, CustomerAuthController::price('45,000'));
        $this->assertSame(45000.5, CustomerAuthController::price('45000,5'));
        $this->assertSame(12.5, CustomerAuthController::price(12.5));

        Setting::updateOrCreate(['key' => 'web'], ['value' => ['catalog' => [
            ['id' => 1, 'name' => 'Figura', 'price' => '185.000', 'cost' => '60.000', 'status' => 'active'],
        ]]]);
        $customer = RecurrentCustomer::create(['name' => 'C', 'email' => 'c@example.com', 'password' => bcrypt('secret123')]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        Mail::fake();
        $this->actingAs($customer, 'customer')
            ->postJson('/api/customer/catalog-orders', ['items' => [['product' => '1', 'qty' => 2]]])
            ->assertCreated()->assertJsonPath('data.total_price', 370000);
        $this->assertSame(60000.0, (float) Order::first()->models[0]['unit_cost']);
    }

    public function test_order_cost_snapshot_never_holds_coupons(): void
    {
        Setting::updateOrCreate(['key' => 'coupons'], ['value' => [['code' => 'SECRETO', 'discount_pct' => 10, 'active' => true]]]);
        Setting::updateOrCreate(['key' => 'infra'], ['value' => ['luz_hr' => 900]]);
        Material::create(['id' => 'PLA', 'name' => 'PLA', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000]);
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/orders', ['customer_name' => 'T', 'technology' => 'FDM', 'material_id' => 'PLA', 'volume_mm3' => 1, 'estimated_weight_g' => 1, 'total_price' => 1])->assertCreated();

        // Pedido viejo que sí guardó los cupones: tampoco se devuelven
        Order::query()->update(['cost_snapshot' => json_encode(['settings' => ['coupons' => ['x'], 'infra' => ['luz_hr' => 1]]])]);

        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['history']]), 'web');
        $json = $this->getJson('/api/admin/orders')->assertOk()->getContent();
        $this->assertStringNotContainsString('coupons', $json);
        $this->assertStringContainsString('luz_hr', $json);
    }

    public function test_register_does_not_overwrite_a_pending_signup(): void
    {
        $pending = RecurrentCustomer::create(['name' => 'Laura Real', 'email' => 'laura@example.com', 'password' => Hash::make('original1')]);
        Mail::fake();
        $this->postJson('/api/auth/unified-register', [
            'name' => 'Intruso', 'email' => 'LAURA@example.com', 'password' => 'otraclave1', 'password_confirmation' => 'otraclave1',
        ])->assertStatus(422);
        $this->assertTrue(Hash::check('original1', $pending->fresh()->password));

        // Contacto del taller sin contraseña: se puede reclamar, pero no se revela su nombre
        RecurrentCustomer::create(['name' => 'Nombre Privado', 'email' => 'contacto@example.com']);
        $this->postJson('/api/auth/unified-register', [
            'name' => 'Quien Registra', 'email' => 'contacto@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonPath('data.user.name', 'Quien Registra');
    }

    public function test_code_requests_per_email_are_capped_and_reset_messages_are_generic(): void
    {
        Mail::fake();
        $customer = RecurrentCustomer::create(['name' => 'C', 'email' => 'c@example.com', 'password' => bcrypt('secret123')]);
        $accounts = app(CustomerAccountService::class);
        for ($i = 0; $i < CustomerAccountService::MAX_CODES_PER_HOUR; $i++) {
            $this->assertTrue($accounts->sendPasswordResetCode($customer));
        }
        $this->assertFalse($accounts->sendPasswordResetCode($customer));

        $body = ['code' => '000000', 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'];
        $known = $this->postJson('/api/auth/password/reset', $body + ['email' => 'c@example.com'])->assertStatus(422)->json('message');
        $unknown = $this->postJson('/api/auth/password/reset', $body + ['email' => 'nadie@example.com'])->assertStatus(422)->json('message');
        $this->assertSame($known, $unknown);
    }

    public function test_wrong_codes_stop_at_the_attempt_limit(): void
    {
        Mail::fake();
        $customer = RecurrentCustomer::create(['name' => 'C', 'email' => 'c@example.com', 'password' => bcrypt('secret123')]);
        $accounts = app(CustomerAccountService::class);
        $accounts->sendVerificationCode($customer);
        for ($i = 0; $i < CustomerAccountService::MAX_ATTEMPTS; $i++) {
            $this->assertSame('El código no es correcto.', $accounts->verify($customer->fresh(), '999999x'));
        }
        $this->assertSame('Demasiados intentos. Pide un código nuevo.', $accounts->verify($customer->fresh(), '999999x'));
        $this->assertSame(CustomerAccountService::MAX_ATTEMPTS, (int) $customer->fresh()->verification_attempts);
    }

    public function test_changing_the_password_ends_other_sessions(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'permissions' => ['kanban']]);
        $middleware = new \App\Http\Middleware\EndSessionsOnPasswordChange();
        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\ArraySessionHandler(10));
        $request = \Illuminate\Http\Request::create('/api/admin/orders');
        $request->setLaravelSession($session);
        $ok = fn () => response('ok');

        // Primera petición: la sesión guarda la huella de la contraseña actual
        Auth::guard('web')->setUser($staff);
        $middleware->handle($request, $ok);
        $this->assertTrue(Auth::guard('web')->check());

        // Misma sesión, pero la contraseña cambió en otra parte (Equipo / recuperación)
        $staff->forceFill(['password' => Hash::make('cambiada-123')])->save();
        Auth::guard('web')->setUser($staff->fresh());
        $middleware->handle($request, $ok);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_tracking_by_guide_only_shows_a_short_name(): void
    {
        Material::create(['id' => 'PLA', 'name' => 'PLA', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 1]);
        $order = Order::create(['customer_name' => 'Laura Gómez Ruiz', 'customer_email' => 'Laura@Example.com', 'technology' => 'FDM', 'material_id' => 'PLA',
            'volume_mm3' => 1, 'qty' => 1, 'total_price' => 1, 'status' => 'shipped', 'tracking_guide' => 'G123']);
        $this->getJson('/api/orders/track?tracking_guide=G123')->assertOk()->assertJsonPath('data.customer_name', 'Laura G.');
        $this->getJson("/api/orders/track?order_id={$order->id}&email=laura@example.com")->assertOk()->assertJsonPath('data.customer_name', 'Laura Gómez Ruiz');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RecurrentCustomer;
use App\Mail\CustomerMail;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/unified-register', [
            'name' => 'Cliente',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
    }

    public function test_register_stores_email_lowercase_and_hashes_password(): void
    {
        $this->register('Cliente@Example.com')->assertCreated();

        $customer = RecurrentCustomer::first();
        $this->assertSame('cliente@example.com', $customer->email);
        $this->assertNotSame('secret123', $customer->password);
    }

    public function test_register_rejects_same_email_with_other_case(): void
    {
        $c = RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('secret123')]);
        $c->forceFill(['email_verified_at' => now()])->save();

        $this->register('CLIENTE@example.com')->assertStatus(422);
        $this->assertSame(1, RecurrentCustomer::count());
    }

    public function test_register_requires_eight_char_password(): void
    {
        $this->postJson('/api/auth/unified-register', [
            'name' => 'Cliente', 'email' => 'c@example.com', 'password' => 'abc123', 'password_confirmation' => 'abc123',
        ])->assertStatus(422);
    }

    public function test_customer_orders_show_pieces_without_storage_paths(): void
    {
        $customer = RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('secret123')]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        Order::forceCreate([
            'customer_name' => 'Cliente', 'customer_email' => 'Cliente@example.com',
            'volume_mm3' => 1000, 'qty' => 2, 'technology' => 'FDM', 'material_id' => 'PLA', 'material_name' => 'PLA',
            'total_price' => 50000, 'status' => 'pending', 'scale_factor' => 1, 'estimated_duration_h' => 1,
            'extras_cost' => 0, 'stock_deducted' => false, 'is_paid' => false, 'hours_added_to_printer' => false,
            'models' => [['path' => 'orders_models/secret.stl', 'original_filename' => 'pieza.stl', 'qty' => 2, 'dimensions_mm' => '10 x 10 x 10', 'weight_g' => 12]],
        ]);
        // Pedido de otra persona: no debe aparecer
        Order::forceCreate([
            'customer_name' => 'Otro', 'customer_email' => 'otro@example.com',
            'volume_mm3' => 1000, 'qty' => 1, 'technology' => 'FDM', 'material_id' => 'PLA',
            'total_price' => 1, 'status' => 'pending', 'scale_factor' => 1, 'estimated_duration_h' => 1,
            'extras_cost' => 0, 'stock_deducted' => false, 'is_paid' => false, 'hours_added_to_printer' => false,
        ]);

        $res = $this->actingAs($customer, 'customer')->getJson('/api/customer/orders')->assertOk();

        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.models.0.name', 'pieza.stl');
        $res->assertJsonPath('data.0.models.0.qty', 2);
        $this->assertStringNotContainsString('orders_models', $res->getContent());
    }

    /** Registra y devuelve el código que llegó por correo. */
    private function registerAndGetCode(string $email = 'cliente@example.com'): string
    {
        Mail::fake();
        $this->register($email)->assertCreated()->assertJsonPath('data.user.email_verified', false);
        $code = null;
        Mail::assertSent(CustomerMail::class, function ($m) use (&$code) {
            if ($m->mailView !== 'mail.verification-code') return false;
            $code = $m->data['code'];
            return true;
        });
        return $code;
    }

    public function test_orders_stay_hidden_until_email_is_verified(): void
    {
        $code = $this->registerAndGetCode();

        $this->getJson('/api/customer/orders')->assertForbidden();
        $this->getJson('/api/customer/profile')->assertOk()->assertJsonPath('email_verified', false)->assertJsonMissingPath('phone');

        $this->postJson('/api/customer/verify', ['code' => $code === '000000' ? '111111' : '000000'])->assertStatus(422);
        $this->postJson('/api/customer/verify', ['code' => $code])->assertOk();

        $this->getJson('/api/customer/orders')->assertOk();
        $profile = $this->getJson('/api/customer/profile')->assertJsonPath('email_verified', true);
        $this->assertStringStartsWith('BIENVENIDA', $profile->json('welcome_coupon'));
        Mail::assertSent(CustomerMail::class, fn ($m) => $m->mailView === 'mail.welcome-coupon');
    }

    public function test_verification_locks_after_too_many_attempts(): void
    {
        $code = $this->registerAndGetCode();
        $wrong = $code === '123456' ? '654321' : '123456';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/customer/verify', ['code' => $wrong])->assertStatus(422);
        }
        // Ni el código correcto sirve hasta pedir uno nuevo
        $this->postJson('/api/customer/verify', ['code' => $code])->assertStatus(422);
    }

    public function test_unverified_or_workshop_contact_email_can_be_claimed_by_verifying(): void
    {
        // Contacto que cargó el taller, sin contraseña
        RecurrentCustomer::create(['name' => 'Cliente Taller', 'email' => 'cliente@example.com', 'phone' => '300']);

        $code = $this->registerAndGetCode();
        $this->assertSame(1, RecurrentCustomer::count());
        $this->postJson('/api/customer/verify', ['code' => $code])->assertOk();
        $this->assertSame('Cliente Taller', RecurrentCustomer::first()->name);
    }

    public function test_catalog_order_uses_server_price(): void
    {
        Setting::updateOrCreate(['key' => 'web'], ['value' => ['catalog' => [
            ['id' => 7, 'name' => 'Figura', 'price' => 50000, 'status' => 'active', 'category' => 'Coleccionables'],
            ['id' => 8, 'name' => 'Oculta', 'price' => 10, 'status' => 'draft'],
        ]]]);
        $customer = RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('secret123')]);
        $this->actingAs($customer, 'customer');

        // Sin verificar: no
        $this->postJson('/api/customer/catalog-orders', ['product' => '7', 'qty' => 2])->assertForbidden();

        $customer->forceFill(['email_verified_at' => now()])->save();
        Mail::fake();
        $this->postJson('/api/customer/catalog-orders', ['product' => '7', 'qty' => 2, 'total_price' => 1])
            ->assertCreated()->assertJsonPath('data.total_price', 100000);
        $this->postJson('/api/customer/catalog-orders', ['product' => '8', 'qty' => 1])->assertStatus(422);

        $this->getJson('/api/customer/orders')->assertJsonCount(1, 'data')->assertJsonPath('data.0.project_name', 'Catálogo: Figura');
        Mail::assertSent(CustomerMail::class, 1);
    }

    public function test_password_reset_with_emailed_code(): void
    {
        RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('vieja_clave1')]);
        Mail::fake();

        // Mismo mensaje exista o no la cuenta
        $this->postJson('/api/auth/password/forgot', ['email' => 'nadie@example.com'])->assertOk();
        Mail::assertNothingSent();
        $this->postJson('/api/auth/password/forgot', ['email' => 'Cliente@example.com'])->assertOk();

        $code = null;
        Mail::assertSent(CustomerMail::class, function ($m) use (&$code) {
            if ($m->mailView !== 'mail.password-reset-code') return false;
            $code = $m->data['code'];
            return true;
        });

        $this->postJson('/api/auth/password/reset', ['email' => 'cliente@example.com', 'code' => $code === '000000' ? '111111' : '000000', 'password' => 'nueva_clave1', 'password_confirmation' => 'nueva_clave1'])->assertStatus(422);
        $this->postJson('/api/auth/password/reset', ['email' => 'cliente@example.com', 'code' => $code, 'password' => 'nueva_clave1', 'password_confirmation' => 'nueva_clave1'])->assertOk();

        // Quedó con sesión, verificado y la clave nueva sirve
        $this->getJson('/api/customer/profile')->assertOk()->assertJsonPath('email_verified', true);
        $this->postJson('/api/auth-master-industrial-access', ['email' => 'cliente@example.com', 'password' => 'nueva_clave1'])->assertOk();
        // El código no se puede reusar
        $this->postJson('/api/auth/password/reset', ['email' => 'cliente@example.com', 'code' => $code, 'password' => 'otra_clave12', 'password_confirmation' => 'otra_clave12'])->assertStatus(422);
    }

    public function test_cart_order_with_several_products_priced_by_server(): void
    {
        Setting::updateOrCreate(['key' => 'web'], ['value' => ['catalog' => [
            ['id' => 1, 'name' => 'Figura', 'price' => '$185.000', 'status' => 'active', 'category' => 'Coleccionables'],
            ['id' => 2, 'name' => 'Llavero', 'price' => 12000, 'status' => 'active'],
        ]]]);
        $customer = RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('secret123')]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($customer, 'customer');
        Mail::fake();

        $this->postJson('/api/customer/catalog-orders', ['items' => [
            ['product' => '1', 'qty' => 1], ['product' => '2', 'qty' => 3], ['product' => '2', 'qty' => 1],
        ]])->assertCreated()->assertJsonPath('data.total_price', 185000 + 4 * 12000);

        $order = Order::first();
        $this->assertSame('Catálogo: 2 productos', $order->job_name);
        $this->assertSame(5, $order->qty);
        $this->assertSame([1, 4], array_column($order->models, 'qty'));

        $this->postJson('/api/customer/catalog-orders', ['items' => [['product' => '99', 'qty' => 1]]])->assertStatus(422);
    }
}

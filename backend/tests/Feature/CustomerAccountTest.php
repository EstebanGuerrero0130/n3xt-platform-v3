<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RecurrentCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        RecurrentCustomer::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => bcrypt('secret123')]);

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
}

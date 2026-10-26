<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RecurrentCustomer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'laura@example.com'): RecurrentCustomer
    {
        $c = RecurrentCustomer::create(['name' => 'Laura Gómez', 'email' => $email, 'password' => bcrypt('secret123')]);
        $c->forceFill(['email_verified_at' => now()])->save();
        return $c;
    }

    private function order(string $email, string $status, array $models = []): Order
    {
        return Order::forceCreate([
            'customer_name' => 'Laura', 'customer_email' => $email, 'volume_mm3' => 0, 'qty' => 1,
            'technology' => 'CATALOGO', 'material_id' => 'CATALOGO', 'total_price' => 50000, 'status' => $status,
            'scale_factor' => 1, 'estimated_duration_h' => 0, 'extras_cost' => 0, 'stock_deducted' => false,
            'is_paid' => true, 'hours_added_to_printer' => false, 'models' => $models,
        ]);
    }

    public function test_review_flow_from_customer_to_published(): void
    {
        $laura = $this->customer();
        $done = $this->order('laura@example.com', 'shipped', [['product_id' => 7, 'original_filename' => 'Figura', 'qty' => 1, 'path' => null]]);
        $pending = $this->order('laura@example.com', 'printing');
        $this->actingAs($laura, 'customer');

        $this->postJson("/api/customer/orders/{$pending->id}/review", ['rating' => 5, 'comment' => 'Excelente trabajo'])->assertStatus(422);
        $this->postJson("/api/customer/orders/{$done->id}/review", ['rating' => 5, 'comment' => 'corto'])->assertStatus(422);
        $this->postJson("/api/customer/orders/{$done->id}/review", ['rating' => 5, 'comment' => 'Quedó perfecta, muy buen acabado'])->assertCreated();
        $this->postJson("/api/customer/orders/{$done->id}/review", ['rating' => 4, 'comment' => 'Otra reseña igual'])->assertStatus(422);

        // Aún sin aprobar: no se publica, pero el cliente la ve en su pedido
        $this->getJson('/api/reviews')->assertJsonPath('data.count', 0);
        $mine = collect($this->getJson('/api/customer/orders')->json('data'))->firstWhere('id', $done->id);
        $this->assertSame('pending', $mine['review']['status']);

        $this->actingAs(User::factory()->create(), 'web');
        $id = Review::first()->id;
        $this->patchJson("/api/admin/reviews/{$id}", ['status' => 'approved'])->assertOk();

        $pub = $this->getJson('/api/reviews')->assertJsonPath('data.count', 1)->assertJsonPath('data.reviews.0.name', 'Laura G.');
        $this->assertStringNotContainsString('laura@example.com', $pub->getContent());
        $this->getJson('/api/reviews?product=7')->assertJsonPath('data.count', 1)->assertJsonPath('data.average', 5);
        $this->getJson('/api/reviews?product=8')->assertJsonPath('data.count', 0);
    }

    public function test_cannot_review_someone_elses_order(): void
    {
        $otro = $this->order('otro@example.com', 'completed');
        $this->actingAs($this->customer(), 'customer')
            ->postJson("/api/customer/orders/{$otro->id}/review", ['rating' => 1, 'comment' => 'No es mi pedido'])
            ->assertNotFound();
    }
}

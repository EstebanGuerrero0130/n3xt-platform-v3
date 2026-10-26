<?php

namespace Tests\Feature;

use App\Models\RecurrentCustomer;
use App\Models\SavedQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavedQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'ana@example.com', bool $verified = true): RecurrentCustomer
    {
        $c = RecurrentCustomer::create(['name' => 'Ana', 'email' => $email, 'password' => bcrypt('secret123')]);
        if ($verified) $c->forceFill(['email_verified_at' => now()])->save();
        return $c;
    }

    private function payload(): array
    {
        return [
            'name' => 'Engranajes', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK', 'infill' => 30, 'layer_height' => 0.2,
            'estimated_total' => 45000,
            'models' => [['file' => UploadedFile::fake()->createWithContent('gear.stl', "solid g\nendsolid g\n"), 'qty' => 3, 'scale_factor' => 1.5]],
            'extra_items' => [['material_id' => 'IMAN', 'qty' => 4]],
        ];
    }

    public function test_save_list_download_and_delete(): void
    {
        Storage::fake('local');
        $ana = $this->customer();
        $this->actingAs($ana, 'customer');

        $id = $this->post('/api/customer/quotes', $this->payload(), ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $list = $this->getJson('/api/customer/quotes')->assertOk()->assertJsonCount(1, 'data');
        $list->assertJsonPath('data.0.models.0.name', 'gear.stl')->assertJsonPath('data.0.models.0.scale_factor', 1.5)
            ->assertJsonPath('data.0.extras.0.id', 'IMAN');
        $this->assertStringNotContainsString('orders_models', $list->getContent());

        $this->get("/api/customer/quotes/{$id}/files/0")->assertOk()->assertDownload('gear.stl');

        $path = SavedQuote::find($id)->models[0]['path'];
        $this->deleteJson("/api/customer/quotes/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_others_cannot_see_or_delete_my_quotes_and_unverified_cannot_save(): void
    {
        Storage::fake('local');
        $this->actingAs($this->customer(), 'customer');
        $id = $this->post('/api/customer/quotes', $this->payload(), ['Accept' => 'application/json'])->json('data.id');

        $this->actingAs($this->customer('otro@example.com'), 'customer');
        $this->get("/api/customer/quotes/{$id}/files/0")->assertNotFound();
        $this->deleteJson("/api/customer/quotes/{$id}")->assertNotFound();
        $this->getJson('/api/customer/quotes')->assertJsonCount(0, 'data');

        $this->actingAs($this->customer('nuevo@example.com', false), 'customer');
        $this->post('/api/customer/quotes', $this->payload(), ['Accept' => 'application/json'])->assertForbidden();
    }
}

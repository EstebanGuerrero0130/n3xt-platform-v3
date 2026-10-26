<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageTest extends TestCase
{
    use RefreshDatabase;

    private function cube(): string
    {
        $v = [[0, 0, 0], [20, 0, 0], [20, 20, 0], [0, 20, 0], [0, 0, 20], [20, 0, 20], [20, 20, 20], [0, 20, 20]];
        $faces = [[0, 3, 2], [0, 2, 1], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [1, 2, 6], [1, 6, 5], [0, 4, 7], [0, 7, 3]];
        $out = "solid c\n";
        foreach ($faces as $f) {
            $out .= "facet normal 0 0 0\nouter loop\n";
            foreach ($f as $i) $out .= 'vertex ' . implode(' ', $v[$i]) . "\n";
            $out .= "endloop\nendfacet\n";
        }
        return $out . "endsolid c\n";
    }

    private function order(): array
    {
        Material::create(['id' => 'PLA_BLACK', 'name' => 'PLA', 'category' => 'FDM', 'type' => 'material', 'unit' => 'g', 'cost_per_kg' => 85000, 'density' => 1.24]);
        return [
            'customer_name' => 'Ana', 'customer_email' => 'ana@example.com', 'technology' => 'FDM', 'material_id' => 'PLA_BLACK',
            'models' => [
                ['file' => UploadedFile::fake()->createWithContent('a.stl', $this->cube()), 'qty' => 1],
                ['file' => UploadedFile::fake()->createWithContent('b.stl', $this->cube()), 'qty' => 1],
            ],
        ];
    }

    public function test_models_go_to_supabase_when_configured_and_download_by_piece(): void
    {
        config(['services.supabase_storage' => ['driver' => 'supabase', 'url' => 'https://proj.supabase.co', 'key' => 'service-key', 'bucket' => 'order-models']]);
        Http::fake([
            'proj.supabase.co/storage/v1/object/order-models/*' => Http::response('STL-BYTES', 200),
            'proj.supabase.co/storage/v1/object/order-models' => Http::response([], 200),
        ]);

        $id = $this->post('/api/orders', $this->order(), ['Accept' => 'application/json'])->assertCreated()->json('data.order_id');
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/object/order-models/orders_models/') && $r->header('Authorization') === ['Bearer service-key']);

        $this->actingAs(User::factory()->create(), 'web');
        $res = $this->get("/api/admin/orders/{$id}/download?model=1")->assertOk();
        $this->assertStringContainsString('b.stl', $res->headers->get('Content-Disposition'));

        // Borrar el pedido borra las dos piezas
        $this->deleteJson("/api/admin/orders/{$id}")->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && count($r['prefixes'] ?? []) === 2);
    }

    public function test_local_download_by_piece_and_missing_file_is_404(): void
    {
        Storage::fake('local');
        $id = $this->post('/api/orders', $this->order(), ['Accept' => 'application/json'])->assertCreated()->json('data.order_id');
        $this->actingAs(User::factory()->create(), 'web');

        $this->get("/api/admin/orders/{$id}/download?model=1")->assertOk()->assertDownload('b.stl');
        $this->get("/api/admin/orders/{$id}/download?model=5")->assertNotFound();
    }

    public function test_logo_goes_to_cloudinary(): void
    {
        config(['services.cloudinary.cloud_name' => 'demo']);
        Http::fake(['api.cloudinary.com/*' => Http::response(['secure_url' => 'https://res.cloudinary.com/demo/logo.png'], 200)]);
        $this->actingAs(User::factory()->create(), 'web');

        $this->post('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('logo_url', 'https://res.cloudinary.com/demo/logo.png');
        $this->assertSame('https://res.cloudinary.com/demo/logo.png', Setting::where('key', 'company_logo')->value('value'));
    }
}

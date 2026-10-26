<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_what_is_published(): void
    {
        config(['app.frontend_url' => 'https://n3xt3d.com']);
        Setting::updateOrCreate(['key' => 'web'], ['value' => [
            'catalog' => [
                ['id' => 7, 'name' => 'Figura', 'status' => 'active'],
                ['id' => 8, 'name' => 'Oculto', 'status' => 'inactive'],
            ],
            'gallery' => [['title' => 'Casco Mandalorian', 'date' => '2026-09-01']],
            'news' => [
                ['t' => '¡Feria 3D en Bogotá!', 'status' => 'published', 'date' => '2026-10-20'],
                ['t' => 'Borrador', 'status' => 'draft'],
                ['t' => '¡Feria 3D en Bogotá!', 'status' => 'published'],
            ],
        ]]);

        $xml = $this->get('/api/sitemap')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();

        $this->assertStringContainsString('<loc>https://n3xt3d.com/catalog/7</loc>', $xml);
        $this->assertStringNotContainsString('/catalog/8', $xml);
        $this->assertStringContainsString('<loc>https://n3xt3d.com/galeria/Casco%20Mandalorian</loc>', $xml);
        $this->assertStringContainsString("<loc>https://n3xt3d.com/noticias/feria-3d-en-bogota</loc>\n    <lastmod>2026-10-20</lastmod>", $xml);
        $this->assertStringContainsString('/noticias/feria-3d-en-bogota-2', $xml);
        $this->assertStringNotContainsString('borrador', $xml);
    }
}

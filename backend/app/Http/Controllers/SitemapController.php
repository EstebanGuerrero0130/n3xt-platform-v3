<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/**
 * /sitemap.xml generado en cada visita desde lo publicado en el panel
 * (catálogo, galería y noticias): al publicar algo ya aparece aquí.
 */
class SitemapController extends Controller
{
    public function index()
    {
        $baseUrl = rtrim(config('app.frontend_url') ?: config('app.url'), '/');
        // En producción, URL canónica aunque APP_URL no esté bien configurada
        if (app()->environment('production') && !str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://n3xt3d.com';
        }

        $web = Setting::where('key', 'web')->first();
        $webData = is_array($web?->value) ? $web->value : [];
        // Última vez que se publicó el sitio: fecha de lo que no trae la suya
        $published = $web?->updated_at?->toDateString() ?? now()->toDateString();

        $urls = [
            ['/', '1.0', 'weekly', $published],
            ['/catalog', '0.9', 'weekly', $published],
            ['/galeria', '0.9', 'weekly', $published],
            ['/noticias', '0.8', 'weekly', $published],
            ['/quote', '0.8', 'monthly', $published],
            ['/project/init', '0.7', 'monthly', $published],
            ['/track', '0.5', 'monthly', $published],
            ['/privacy', '0.3', 'yearly', $published],
            ['/terms', '0.3', 'yearly', $published],
        ];

        // Productos activos → /catalog/:id (o el nombre si no tiene id, como en el sitio)
        foreach ($this->list($webData, 'catalog') as $item) {
            if (empty($item['name']) || ($item['status'] ?? 'active') !== 'active') continue;
            $urls[] = ['/catalog/' . rawurlencode((string) ($item['id'] ?? $item['name'])), '0.7', 'monthly', $this->date($item['updated_at'] ?? null, $published)];
        }

        // Proyectos de la galería → /galeria/:title
        foreach ($this->list($webData, 'gallery') as $item) {
            if (empty($item['title'])) continue;
            $urls[] = ['/galeria/' . rawurlencode($item['title']), '0.6', 'monthly', $this->date($item['date'] ?? null, $published)];
        }

        // Noticias publicadas → /noticias/:slug (mismo slug que utils/news.ts)
        $seen = [];
        foreach ($this->list($webData, 'news') as $item) {
            if (empty($item['t']) || (!empty($item['status']) && $item['status'] !== 'published')) continue;
            $base = $this->slugify((string) ($item['slug'] ?? '') ?: $item['t']) ?: 'noticia';
            $slug = $base;
            for ($i = 2; isset($seen[$slug]); $i++) $slug = "{$base}-{$i}";
            $seen[$slug] = true;
            $urls[] = ["/noticias/{$slug}", '0.6', 'yearly', $this->date($item['date'] ?? null, $published)];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $priority, $freq, $lastmod]) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($baseUrl . $loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$freq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            // Corto: lo recién publicado aparece pronto
            'Cache-Control' => 'public, max-age=600, s-maxage=600',
        ]);
    }

    private function list(array $web, string $key): array
    {
        return array_filter(is_array($web[$key] ?? null) ? $web[$key] : [], 'is_array');
    }

    /** Fecha YYYY-MM-DD válida o la de publicación del sitio. */
    private function date(?string $value, string $fallback): string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : $fallback;
    }

    private function slugify(string $text): string
    {
        $ascii = \Illuminate\Support\Str::ascii(mb_strtolower($text));
        return trim(preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }
}

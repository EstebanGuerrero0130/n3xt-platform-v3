<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class SitemapController extends Controller
{
    public function index()
    {
        $baseUrl = config('app.url');
// En producción, asegurar URL canónica incluso si APP_URL no está configurada
if (app()->environment('production') && (!str_starts_with($baseUrl, 'https://') || $baseUrl === 'http://localhost')) {
    $baseUrl = 'https://n3xt3d.com';
}

        // Static pages with their metadata
        $staticPages = [
            ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => '2026-06-09'],
            ['loc' => '/catalog', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => '2026-06-09'],
            ['loc' => '/galeria', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => '2026-06-09'],
            ['loc' => '/quote', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => '2026-06-09'],
            ['loc' => '/project/init', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => '2026-06-09'],
            ['loc' => '/track', 'priority' => '0.7', 'changefreq' => 'daily', 'lastmod' => '2026-06-09'],
            ['loc' => '/privacy', 'priority' => '0.4', 'changefreq' => 'yearly', 'lastmod' => '2026-06-09'],
            ['loc' => '/terms', 'priority' => '0.4', 'changefreq' => 'yearly', 'lastmod' => '2026-06-09'],
        ];

        $urls = $staticPages;

        // Fetch dynamic content from settings
        $webSetting = Setting::where('key', 'web')->first();
        $webData = $webSetting ? $webSetting->value : [];

        // Catalog items → /catalog/:name
        $catalogItems = isset($webData['catalog']) && is_array($webData['catalog'])
            ? $webData['catalog']
            : [];

        foreach ($catalogItems as $item) {
            if (isset($item['name']) && !empty($item['name'])) {
                $slug = rawurlencode($item['name']);
                $urls[] = [
                    'loc' => "/catalog/{$slug}",
                    'priority' => '0.6',
                    'changefreq' => 'monthly',
                    'lastmod' => isset($item['updated_at']) ? substr($item['updated_at'], 0, 10) : '2026-06-09',
                ];
            }
        }

        // Gallery items → /galeria/:title
        $galleryItems = isset($webData['gallery']) && is_array($webData['gallery'])
            ? $webData['gallery']
            : [];

        foreach ($galleryItems as $item) {
            if (isset($item['title']) && !empty($item['title'])) {
                $slug = rawurlencode($item['title']);
                $urls[] = [
                    'loc' => "/galeria/{$slug}",
                    'priority' => '0.5',
                    'changefreq' => 'monthly',
                    'lastmod' => isset($item['updated_at']) ? substr($item['updated_at'], 0, 10) : '2026-06-09',
                ];
            }
        }

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$baseUrl}{$url['loc']}</loc>\n";
            $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }
}

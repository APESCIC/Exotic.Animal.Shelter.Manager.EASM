<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => 'EASM',
            'description' => 'Care tasks for exotic animal shelter staff and volunteers.',
            'id' => route('mobile.index'),
            'start_url' => route('mobile.index'),
            'scope' => url('/').'/',
            'display' => 'standalone',
            'background_color' => '#f4f6f3',
            'theme_color' => '#1b4332',
            'icons' => [
                ['src' => asset('images/pwa/easm-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/pwa/easm-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('images/pwa/easm.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}

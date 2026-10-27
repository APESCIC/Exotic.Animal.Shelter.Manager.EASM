<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdoptableFeed;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerFeedController extends Controller
{
    public const FORMATS = ['csv', 'json', 'rss'];

    public function show(Request $request, string $format, AdoptableFeed $feed): Response
    {
        abort_unless(Setting::current()?->partnerFeedAccepts($request->query('token')), 404);

        $headers = ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'];

        return match ($format) {
            'csv' => response($feed->csv(), 200, $headers + [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="adoptable-animals.csv"',
            ]),
            'json' => response()->json($feed->json(), 200, $headers),
            'rss' => response($feed->rss(), 200, $headers + ['Content-Type' => 'application/rss+xml; charset=UTF-8']),
            default => abort(404),
        };
    }
}

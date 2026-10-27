<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\PartnerFeedController;
use App\Models\Setting;
use App\Services\AdoptableFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublishingController extends Controller
{
    public function edit(AdoptableFeed $feed): View
    {
        $settings = Setting::current();
        $token = $settings?->partner_feed_token;

        return view('admin.publishing', [
            'enabled' => (bool) $settings?->partner_feed_enabled,
            'feedUrls' => $token === null ? [] : array_combine(
                PartnerFeedController::FORMATS,
                array_map(
                    fn (string $format): string => route('feeds.adoptable', ['format' => $format, 'token' => $token]),
                    PartnerFeedController::FORMATS,
                ),
            ),
            'adoptableCount' => $feed->animals()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $this->settings();
        $enabled = $request->boolean('partner_feed_enabled');

        $settings->update([
            'partner_feed_enabled' => $enabled,
            'partner_feed_token' => $settings->partner_feed_token ?? ($enabled ? $this->newToken() : null),
        ]);

        return redirect()
            ->route('admin.publishing.edit')
            ->with('status', $enabled ? 'Partner feed switched on.' : 'Partner feed switched off.');
    }

    public function regenerate(): RedirectResponse
    {
        $this->settings()->update(['partner_feed_token' => $this->newToken()]);

        return redirect()
            ->route('admin.publishing.edit')
            ->with('status', 'New feed address created. Old feed addresses no longer work; send partners the new one.');
    }

    public function download(AdoptableFeed $feed): Response
    {
        return response($feed->csv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="adoptable-animals-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function settings(): Setting
    {
        return Setting::current() ?? Setting::query()->create([
            'organisation_name' => config('app.name'),
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
        ]);
    }

    private function newToken(): string
    {
        return Str::random(48);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = Setting::current() ?? new Setting([
            'organisation_name' => config('app.name'),
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
            'dwa_reminder_days' => Setting::DEFAULT_DWA_REMINDER_DAYS,
        ]);

        return view('admin.settings', [
            'settings' => $settings,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->safe()->only([
            'organisation_name',
            'locale',
            'timezone',
            'dwa_reminder_days',
        ]);

        $settings = Setting::current();

        if ($settings === null) {
            $settings = Setting::query()->create($data);
        } else {
            $settings->update($data);
        }

        $settings->refresh()->applyToConfig();

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Settings saved.');
    }
}

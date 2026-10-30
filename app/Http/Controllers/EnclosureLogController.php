<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnclosureLogRequest;
use App\Models\Enclosure;
use Illuminate\Http\RedirectResponse;

class EnclosureLogController extends Controller
{
    public function store(StoreEnclosureLogRequest $request, Enclosure $enclosure): RedirectResponse
    {
        $enclosure->logs()->create([
            ...$request->safe()->only(['recorded_at', 'temperature_hot_c', 'temperature_cool_c', 'humidity_percent', 'notes']),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->to(route('enclosures.show', $enclosure).'#environment')
            ->with('status', 'Reading recorded.');
    }
}

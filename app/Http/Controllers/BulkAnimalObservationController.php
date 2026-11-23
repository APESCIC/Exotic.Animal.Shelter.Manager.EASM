<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkAnimalObservationRequest;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\ObservationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkAnimalObservationController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorizeRecord();

        $enclosureId = $request->filled('enclosure_id') ? (int) $request->input('enclosure_id') : null;
        $location = trim((string) $request->input('location', ''));

        $animals = $this->animalsFor($enclosureId, $location);
        $columnTypes = ObservationType::query()->active()->ordered()->get();

        return view('observations.bulk', [
            'enclosures' => Enclosure::query()->where('active', true)->ordered()->get(),
            'enclosureId' => $enclosureId,
            'location' => $location,
            'animals' => $animals,
            'columnTypes' => $columnTypes,
            'observedOn' => old('observed_on', now()->toDateString()),
        ]);
    }

    public function store(StoreBulkAnimalObservationRequest $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        $observedOn = $request->validated('observed_on');
        $created = 0;

        foreach ($request->resolvedEntries() as $entry) {
            $entry['animal']->observations()->create([
                'observation_type_id' => $entry['type']->id,
                'lookup_value_id' => $entry['lookup_value_id'],
                'value_text' => $entry['value_text'],
                'observed_on' => $observedOn,
                'user_id' => $userId,
            ]);
            $created++;
        }

        return redirect()
            ->route('observations.bulk', $request->only(['enclosure_id', 'location']))
            ->with('status', $created === 0
                ? 'No observations were saved — tick animals and enter at least one value.'
                : ($created === 1 ? 'One observation recorded.' : $created.' observations recorded.'));
    }

    /**
     * @return Collection<int, Animal>
     */
    private function animalsFor(?int $enclosureId, string $location)
    {
        $query = Animal::query()->inCare()->orderBy('name');

        if ($enclosureId !== null) {
            $query->where('enclosure_id', $enclosureId);
        } elseif ($location !== '') {
            $query->atLocation($location);
        } else {
            return Animal::query()->whereRaw('1 = 0')->get();
        }

        $animals = $query->get();
        $animals->load('enclosure');

        return $animals;
    }

    private function authorizeRecord(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canRecordObservations(), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnclosureRequest;
use App\Models\Enclosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnclosureController extends Controller
{
    public function index(Request $request): View
    {
        $zone = $request->string('zone')->toString();
        $q = trim($request->string('q')->toString());

        $enclosures = Enclosure::query()
            ->withCount('occupants')
            ->when($zone !== '', fn ($query) => $query->where('zone', $zone))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderByDesc('active')
            ->ordered()
            ->paginate(30)
            ->withQueryString();

        return view('enclosures.index', [
            'enclosures' => $enclosures,
            'zones' => Enclosure::query()->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone'),
            'zone' => $zone,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('enclosures.create', [
            'enclosure' => new Enclosure(['active' => true]),
        ]);
    }

    public function store(StoreEnclosureRequest $request): RedirectResponse
    {
        $enclosure = Enclosure::query()->create($request->enclosureData());

        return redirect()
            ->route('enclosures.show', $enclosure)
            ->with('status', 'Enclosure added.');
    }

    public function show(Enclosure $enclosure): View
    {
        $enclosure->load(['occupants.latestMovement'])->loadCount('occupants');

        return view('enclosures.show', [
            'enclosure' => $enclosure,
        ]);
    }

    public function edit(Enclosure $enclosure): View
    {
        $this->authorizeManage();

        return view('enclosures.edit', [
            'enclosure' => $enclosure,
        ]);
    }

    public function update(StoreEnclosureRequest $request, Enclosure $enclosure): RedirectResponse
    {
        $enclosure->update($request->enclosureData());

        $response = redirect()
            ->route('enclosures.show', $enclosure)
            ->with('status', 'Enclosure updated.');

        return $enclosure->isOverCapacity()
            ? $response->with('warning', "{$enclosure->name} is over capacity: {$enclosure->occupancyLabel()} animals.")
            : $response;
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageAnimals(), 403);
    }
}

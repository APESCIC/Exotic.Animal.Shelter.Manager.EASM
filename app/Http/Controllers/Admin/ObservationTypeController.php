<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ObservationTypeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreObservationTypeRequest;
use App\Http\Requests\UpdateObservationTypeRequest;
use App\Models\LookupList;
use App\Models\ObservationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ObservationTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.observation-types.index', [
            'types' => ObservationType::query()->with('lookupList')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.observation-types.create', [
            'type' => new ObservationType([
                'kind' => ObservationTypeKind::ValueList,
                'sort_order' => 0,
                'active' => true,
            ]),
            'kinds' => ObservationTypeKind::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreObservationTypeRequest $request): RedirectResponse
    {
        ObservationType::query()->create($request->observationTypeData());

        return redirect()
            ->route('admin.observation-types.index')
            ->with('status', 'Observation type created.');
    }

    public function edit(ObservationType $observationType): View
    {
        return view('admin.observation-types.edit', [
            'type' => $observationType,
            'kinds' => ObservationTypeKind::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateObservationTypeRequest $request, ObservationType $observationType): RedirectResponse
    {
        $observationType->update($request->observationTypeData());

        return redirect()
            ->route('admin.observation-types.index')
            ->with('status', 'Observation type updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDwaInspectionRequest;
use App\Models\DwaLicence;
use Illuminate\Http\RedirectResponse;

class DwaInspectionController extends Controller
{
    public function store(StoreDwaInspectionRequest $request, DwaLicence $dwaLicence): RedirectResponse
    {
        $dwaLicence->inspections()->create([
            ...$request->safe()->only(['inspected_on', 'inspector', 'outcome', 'notes']),
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()
            ->to(route('compliance.dwa.show', $dwaLicence).'#inspections')
            ->with('status', 'Inspection recorded.');
    }
}

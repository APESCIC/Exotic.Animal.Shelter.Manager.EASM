<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentType;
use App\Http\Requests\StoreEnclosureEquipmentRequest;
use App\Models\DiaryTask;
use App\Models\Enclosure;
use App\Models\EnclosureEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EnclosureEquipmentController extends Controller
{
    public function store(StoreEnclosureEquipmentRequest $request, Enclosure $enclosure): RedirectResponse
    {
        $enclosure->equipment()->create($request->equipmentData());

        return redirect()
            ->to(route('enclosures.show', $enclosure).'#equipment')
            ->with('status', 'Equipment added.');
    }

    public function edit(EnclosureEquipment $equipment): View
    {
        $role = request()->user()?->role;
        abort_unless($role !== null && $role->canManageAnimals(), 403);

        return view('enclosures.equipment.edit', [
            'equipment' => $equipment->load('enclosure'),
            'types' => EquipmentType::cases(),
        ]);
    }

    public function update(StoreEnclosureEquipmentRequest $request, EnclosureEquipment $equipment): RedirectResponse
    {
        $equipment->update($request->equipmentData());

        return redirect()
            ->to(route('enclosures.show', $equipment->enclosure_id).'#equipment')
            ->with('status', 'Equipment updated.');
    }

    public function replaced(EnclosureEquipment $equipment): RedirectResponse
    {
        $role = request()->user()?->role;
        abort_unless($role !== null && $role->canRecordHusbandry(), 403);

        DB::transaction(function () use ($equipment): void {
            $equipment->update(['installed_on' => today()]);

            DiaryTask::query()
                ->open()
                ->reminderKeyStartsWith(EnclosureEquipment::reminderKeyPrefix($equipment->id))
                ->update(['completed_at' => now()]);
        });

        return redirect()
            ->to(route('enclosures.show', $equipment->enclosure_id).'#equipment')
            ->with('status', "{$equipment->name} marked replaced today.");
    }
}

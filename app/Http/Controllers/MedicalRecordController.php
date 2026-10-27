<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Enums\MedicalRecordType;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Models\Animal;
use App\Models\MedicalRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MedicalRecordController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('medical.create', [
            'animal' => $animal,
            'record' => new MedicalRecord([
                'type' => MedicalRecordType::Treatment,
                'due_on' => now()->toDateString(),
            ]),
            'types' => MedicalRecordType::cases(),
        ]);
    }

    public function store(StoreMedicalRecordRequest $request, Animal $animal): RedirectResponse
    {
        $animal->medicalRecords()->create($this->validatedData($request));

        return redirect()
            ->to(AnimalTab::Health->url($animal))
            ->with('status', 'Medical record added.');
    }

    public function edit(MedicalRecord $medicalRecord): View
    {
        $this->authorizeManage();
        $medicalRecord->load('animal');

        return view('medical.edit', [
            'animal' => $medicalRecord->animal,
            'record' => $medicalRecord,
            'types' => MedicalRecordType::cases(),
        ]);
    }

    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medicalRecord): RedirectResponse
    {
        $medicalRecord->update($this->validatedData($request));

        return redirect()
            ->to(AnimalTab::Health->url($medicalRecord->animal))
            ->with('status', 'Medical record updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreMedicalRecordRequest $request): array
    {
        $data = $request->safe()->all();

        foreach (['due_on', 'given_on', 'expires_on', 'notes'] as $nullable) {
            if (($data[$nullable] ?? null) === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageMedical(), 403);
    }
}

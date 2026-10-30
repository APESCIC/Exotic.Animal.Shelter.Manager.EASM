<?php

namespace App\Http\Controllers;

use App\Enums\DwaInspectionOutcome;
use App\Http\Requests\StoreDwaLicenceRequest;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\DwaLicence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DwaLicenceController extends Controller
{
    public function index(Request $request): View
    {
        $renewalOnly = $request->string('filter')->toString() === 'renewal';

        return view('compliance.dwa.index', [
            'licences' => DwaLicence::query()
                ->withCount('animals')
                ->with('inspections')
                ->when($renewalOnly, fn ($query) => $query->needsRenewal())
                ->orderBy('expires_on')
                ->get(),
            'renewalOnly' => $renewalOnly,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('compliance.dwa.create', [
            'licence' => new DwaLicence,
            'animals' => $this->animalOptions(),
            'linkedIds' => [],
        ]);
    }

    public function store(StoreDwaLicenceRequest $request): RedirectResponse
    {
        $licence = DB::transaction(function () use ($request): DwaLicence {
            $licence = DwaLicence::query()->create($request->licenceData());
            $licence->animals()->sync($request->animalIds());

            return $licence;
        });

        return redirect()
            ->route('compliance.dwa.show', $licence)
            ->with('status', 'DWA licence added.');
    }

    public function show(DwaLicence $dwaLicence): View
    {
        $dwaLicence->load(['animals.latestMovement', 'inspections.recorder']);

        return view('compliance.dwa.show', [
            'licence' => $dwaLicence,
            'outcomes' => DwaInspectionOutcome::cases(),
        ]);
    }

    public function edit(DwaLicence $dwaLicence): View
    {
        $this->authorizeManage();

        return view('compliance.dwa.edit', [
            'licence' => $dwaLicence,
            'animals' => $this->animalOptions($dwaLicence),
            'linkedIds' => $dwaLicence->animals()->pluck('animals.id')->all(),
        ]);
    }

    public function update(StoreDwaLicenceRequest $request, DwaLicence $dwaLicence): RedirectResponse
    {
        DB::transaction(function () use ($request, $dwaLicence): void {
            $previousKey = $dwaLicence->reminderKey();
            $dwaLicence->update($request->licenceData());
            $dwaLicence->animals()->sync($request->animalIds());

            if ($dwaLicence->reminderKey() !== $previousKey) {
                DiaryTask::query()
                    ->open()
                    ->reminderKeyStartsWith(DwaLicence::reminderKeyPrefix($dwaLicence->id))
                    ->update(['completed_at' => now()]);
            }
        });

        return redirect()
            ->route('compliance.dwa.show', $dwaLicence)
            ->with('status', 'DWA licence updated.');
    }

    /**
     * Animals in care, plus any already on the licence so editing never drops them silently.
     *
     * @return Collection<int, Animal>
     */
    private function animalOptions(?DwaLicence $licence = null): Collection
    {
        return Animal::query()
            ->where(function ($query) use ($licence): void {
                $query->inCare();

                if ($licence !== null) {
                    $query->orWhereHas('dwaLicences', fn ($licences) => $licences->whereKey($licence->id));
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'species', 'dwa']);
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageAnimals(), 403);
    }
}

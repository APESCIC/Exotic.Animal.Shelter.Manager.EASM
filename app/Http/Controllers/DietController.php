<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Http\Requests\StoreDietRequest;
use App\Http\Requests\UpdateDietRequest;
use App\Models\Animal;
use App\Models\Diet;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DietController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('diets.create', [
            'animal' => $animal,
            'diet' => new Diet([
                'started_on' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(StoreDietRequest $request, Animal $animal): RedirectResponse
    {
        $animal->diets()->create($this->validatedData($request));

        return redirect()
            ->to(AnimalTab::Husbandry->url($animal))
            ->with('status', 'Diet recorded.');
    }

    public function edit(Diet $diet): View
    {
        $this->authorizeManage();
        $diet->load('animal');

        return view('diets.edit', [
            'animal' => $diet->animal,
            'diet' => $diet,
        ]);
    }

    public function update(UpdateDietRequest $request, Diet $diet): RedirectResponse
    {
        $diet->update($this->validatedData($request));

        return redirect()
            ->to(AnimalTab::Husbandry->url($diet->animal))
            ->with('status', 'Diet updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreDietRequest $request): array
    {
        $data = $request->safe()->all();

        foreach (['details', 'started_on', 'ended_on', 'notes'] as $nullable) {
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

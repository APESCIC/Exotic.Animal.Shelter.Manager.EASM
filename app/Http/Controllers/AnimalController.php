<?php

namespace App\Http\Controllers;

use App\Enums\AnimalSex;
use App\Enums\CustomFieldType;
use App\Http\Requests\StoreAnimalRequest;
use App\Http\Requests\UpdateAnimalRequest;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnimalController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $location = $request->string('location')->toString();

        $animals = Animal::query()
            ->search($q !== '' ? $q : null)
            ->atLocation($location !== '' ? $location : null)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $locations = Animal::query()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        return view('animals.index', [
            'animals' => $animals,
            'locations' => $locations,
            'q' => $q,
            'location' => $location,
        ]);
    }

    public function shelter(): View
    {
        $groups = Animal::query()
            ->orderBy('location')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Animal $animal) => $animal->location ?: 'Unassigned');

        return view('animals.shelter', [
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('animals.create', [
            'animal' => new Animal(['sex' => AnimalSex::Unknown]),
            'customFieldDefinitions' => CustomFieldDefinition::query()
                ->active()
                ->forAnimal()
                ->ordered()
                ->get(),
        ]);
    }

    public function store(StoreAnimalRequest $request): RedirectResponse
    {
        $data = $this->validatedAnimalData($request);

        if ($request->hasFile('primary_photo')) {
            $data['primary_photo_path'] = $request->file('primary_photo')->store('animals', 'public');
        }

        $animal = Animal::query()->create($data);
        $this->syncCustomFieldValues($request, $animal);

        return redirect()
            ->route('animals.show', $animal)
            ->with('status', 'Animal record created.');
    }

    public function show(Animal $animal): View
    {
        $animal->load([
            'movements.person',
            'medicalRecords',
            'diets',
            'observations.user',
            'diaryTasks.assignee',
            'media.uploader',
            'customFieldValues.definition',
        ]);

        $customFieldDefinitions = CustomFieldDefinition::query()
            ->active()
            ->forAnimal()
            ->ordered()
            ->get();

        return view('animals.show', [
            'animal' => $animal,
            'customFieldDefinitions' => $customFieldDefinitions,
        ]);
    }

    public function edit(Animal $animal): View
    {
        $this->authorizeManage();

        $animal->load('customFieldValues');

        return view('animals.edit', [
            'animal' => $animal,
            'customFieldDefinitions' => CustomFieldDefinition::query()
                ->active()
                ->forAnimal()
                ->ordered()
                ->get(),
        ]);
    }

    public function update(UpdateAnimalRequest $request, Animal $animal): RedirectResponse
    {
        $data = $this->validatedAnimalData($request);

        if ($request->hasFile('primary_photo')) {
            if ($animal->primary_photo_path) {
                Storage::disk('public')->delete($animal->primary_photo_path);
            }
            $data['primary_photo_path'] = $request->file('primary_photo')->store('animals', 'public');
        }

        $animal->update($data);
        $this->syncCustomFieldValues($request, $animal);

        return redirect()
            ->route('animals.show', $animal)
            ->with('status', 'Animal record updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAnimalData(StoreAnimalRequest $request): array
    {
        $data = $request->safe()->except(['primary_photo', 'custom_fields']);
        $data['non_shelter'] = $request->boolean('non_shelter');

        return $data;
    }

    private function syncCustomFieldValues(StoreAnimalRequest $request, Animal $animal): void
    {
        $submitted = $request->input('custom_fields', []);

        if (! is_array($submitted)) {
            $submitted = [];
        }

        $definitions = CustomFieldDefinition::query()
            ->active()
            ->forAnimal()
            ->get();

        foreach ($definitions as $definition) {
            $rawValue = $submitted[$definition->id] ?? ($definition->type === CustomFieldType::Boolean ? '0' : null);
            $value = $definition->normalizeStoredValue($rawValue);

            $existing = $animal->customFieldValues()
                ->where('definition_id', $definition->id)
                ->first();

            if ($value === null || $value === '') {
                $existing?->delete();

                continue;
            }

            if ($existing !== null) {
                $existing->update(['value' => $value]);
            } else {
                $animal->customFieldValues()->create([
                    'definition_id' => $definition->id,
                    'value' => $value,
                ]);
            }
        }
    }

    private function authorizeManage(): void
    {
        abort_unless($this->requestUserCanManage(), 403);
    }

    private function requestUserCanManage(): bool
    {
        $role = request()->user()?->role;

        return $role !== null && $role->canManageAnimals();
    }
}

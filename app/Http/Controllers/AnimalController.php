<?php

namespace App\Http\Controllers;

use App\Enums\AnimalListFilter;
use App\Enums\AnimalSex;
use App\Enums\AnimalTab;
use App\Enums\CustomFieldEntity;
use App\Http\Requests\StoreAnimalRequest;
use App\Http\Requests\UpdateAnimalRequest;
use App\Models\Animal;
use App\Models\AuditLog;
use App\Models\CustomFieldDefinition;
use App\Models\Enclosure;
use App\Services\CustomFieldSync;
use App\Support\LookupField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnimalController extends Controller
{
    public function __construct(private CustomFieldSync $customFields) {}

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $location = $request->string('location')->toString();
        $filter = AnimalListFilter::tryFrom($request->string('filter')->toString());

        $query = Animal::query()
            ->with(['latestMovement', 'enclosure'])
            ->search($q !== '' ? $q : null)
            ->atLocation($location !== '' ? $location : null);

        if ($filter !== null) {
            $filter->apply($query);
        }

        $animals = $query
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
            'filter' => $filter,
        ]);
    }

    public function shelter(Request $request): View
    {
        $byEnclosure = $request->string('group')->toString() === 'enclosure';

        if ($byEnclosure) {
            return view('animals.shelter', [
                'byEnclosure' => true,
                'enclosures' => Enclosure::query()
                    ->with(['occupants.latestMovement'])
                    ->withCount('occupants')
                    ->where(fn ($query) => $query->active()->orHas('occupants'))
                    ->ordered()
                    ->get(),
                'unassigned' => Animal::query()
                    ->inCare()
                    ->whereNull('enclosure_id')
                    ->with('latestMovement')
                    ->orderBy('name')
                    ->get(),
            ]);
        }

        $groups = Animal::query()
            ->with(['latestMovement', 'enclosure'])
            ->orderBy('location')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Animal $animal) => $animal->location ?: 'Unassigned');

        return view('animals.shelter', [
            'byEnclosure' => false,
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        $animal = new Animal(['sex' => AnimalSex::Unknown]);

        return view('animals.create', [
            'animal' => $animal,
            'enclosures' => $this->enclosureOptions(),
            'lookupOptions' => $this->lookupOptionsFor($animal),
            'customFieldDefinitions' => $this->customFields->formDefinitions(CustomFieldEntity::Animal, newRecord: true),
        ]);
    }

    public function store(StoreAnimalRequest $request): RedirectResponse
    {
        $data = $this->validatedAnimalData($request);

        if ($request->hasFile('primary_photo')) {
            $data['primary_photo_path'] = $request->file('primary_photo')->store('animals', 'public');
        }

        $animal = Animal::query()->create($data);
        $this->customFields->syncForAnimal($request, $animal, $data['species'] ?? null);

        return $this->withCapacityWarning(
            redirect()->route('animals.show', $animal)->with('status', 'Animal record created.'),
            $animal,
        );
    }

    public function show(Animal $animal, ?string $tab = null): View
    {
        $activeTab = AnimalTab::from($tab ?? AnimalTab::Overview->value);

        $animal->load(['latestMovement', ...$activeTab->relations()]);
        $animal->loadCount(array_values(array_filter(
            array_map(fn (AnimalTab $t): ?string => $t->countedRelation(), AnimalTab::cases()),
        )));

        return view('animals.show', [
            'animal' => $animal,
            'activeTab' => $activeTab,
            'tabs' => AnimalTab::cases(),
            'customFieldDefinitions' => $activeTab === AnimalTab::Overview
                ? $this->customFields->overviewDefinitions(
                    CustomFieldEntity::Animal,
                    $animal->customFieldValues->pluck('definition_id'),
                    $animal->species,
                )
                : collect(),
            'auditEntries' => $activeTab === AnimalTab::Audit
                ? AuditLog::forAnimalRecord($animal)->with('user')->limit(200)->get()
                : collect(),
        ]);
    }

    public function edit(Animal $animal): View
    {
        $this->authorizeManage();

        $animal->load('customFieldValues');

        return view('animals.edit', [
            'animal' => $animal,
            'enclosures' => $this->enclosureOptions($animal->enclosure_id),
            'lookupOptions' => $this->lookupOptionsFor($animal),
            'customFieldDefinitions' => $this->customFields->formDefinitions(CustomFieldEntity::Animal, newRecord: false)
                ->filter(fn (CustomFieldDefinition $definition): bool => $definition->appliesToSpecies($animal->species))
                ->values(),
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
        $this->customFields->syncForAnimal($request, $animal, $data['species'] ?? $animal->species);

        return $this->withCapacityWarning(
            redirect()->route('animals.show', $animal)->with('status', 'Animal record updated.'),
            $animal,
        );
    }

    /**
     * Capacity is advisory: staff may need to over-fill an enclosure in an emergency intake.
     */
    private function withCapacityWarning(RedirectResponse $response, Animal $animal): RedirectResponse
    {
        $enclosure = $animal->enclosure()->first();

        if ($enclosure === null || ! $enclosure->isOverCapacity()) {
            return $response;
        }

        return $response->with(
            'warning',
            "{$enclosure->name} is over capacity: {$enclosure->occupancyLabel()} animals.",
        );
    }

    /**
     * @return Collection<int, Enclosure>
     */
    private function enclosureOptions(?int $currentId = null): Collection
    {
        return Enclosure::query()
            ->withCount('occupants')
            ->where(fn ($query) => $query->active()->when($currentId !== null, fn ($inner) => $inner->orWhere('id', $currentId)))
            ->ordered()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAnimalData(StoreAnimalRequest $request): array
    {
        $data = $request->safe()->except(['primary_photo', 'custom_fields']);
        $data['non_shelter'] = $request->boolean('non_shelter');
        $data['is_adoptable'] = $request->boolean('is_adoptable');
        $data['cites_exempt_annex_x'] = $request->boolean('cites_exempt_annex_x');
        $data['flags'] = array_values($request->input('flags', []) ?: []);

        return $data;
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, LookupValue>>
     */
    private function lookupOptionsFor(Animal $animal): array
    {
        return [
            'colour' => LookupField::options('colour', $animal->colour),
            'breed_type' => LookupField::options('breed_type', $animal->breed_type),
            'entry_reason' => LookupField::options('entry_reason', $animal->entry_reason),
            'death_reason' => LookupField::options('death_reason', $animal->death_reason),
            'flags' => LookupField::options('flags', $animal->flags),
        ];
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

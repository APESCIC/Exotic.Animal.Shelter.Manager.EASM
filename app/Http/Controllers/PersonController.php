<?php

namespace App\Http\Controllers;

use App\Enums\CustomFieldEntity;
use App\Enums\PersonCategory;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Person;
use App\Services\CustomFieldSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function __construct(private CustomFieldSync $customFields) {}

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $category = $request->string('category')->toString();
        $bannedOnly = $request->boolean('banned');

        $people = Person::query()
            ->search($q !== '' ? $q : null)
            ->ofCategory($category !== '' ? $category : null)
            ->bannedOnly($bannedOnly)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('people.index', [
            'people' => $people,
            'categories' => PersonCategory::cases(),
            'q' => $q,
            'category' => $category,
            'bannedOnly' => $bannedOnly,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('people.create', [
            'person' => new Person(['category' => PersonCategory::Adopter]),
            'customFieldDefinitions' => $this->customFields->formDefinitions(CustomFieldEntity::Person, newRecord: true),
        ]);
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        $person = Person::query()->create($this->validatedPersonData($request));
        $this->customFields->syncForPerson($request, $person);

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Contact created.');
    }

    public function show(Person $person): View
    {
        $person->load('customFieldValues');

        return view('people.show', [
            'person' => $person,
            'customFieldDefinitions' => $this->customFields->overviewDefinitions(
                CustomFieldEntity::Person,
                $person->customFieldValues->pluck('definition_id'),
            ),
        ]);
    }

    public function edit(Person $person): View
    {
        $this->authorizeManage();

        $person->load('customFieldValues');

        return view('people.edit', [
            'person' => $person,
            'customFieldDefinitions' => $this->customFields->formDefinitions(CustomFieldEntity::Person, newRecord: false),
        ]);
    }

    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($this->validatedPersonData($request));
        $this->customFields->syncForPerson($request, $person);

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Contact updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPersonData(StorePersonRequest $request): array
    {
        $data = $request->safe()->except(['custom_fields']);
        $data['banned'] = $request->boolean('banned');
        $data['homechecked'] = $request->boolean('homechecked');

        $category = $data['category'] ?? null;
        $isCustom = $category === PersonCategory::Custom
            || $category === PersonCategory::Custom->value;

        if (! $isCustom) {
            $data['category_custom'] = null;
        }

        return $data;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->requestUserCanManage(), 403);
    }

    private function requestUserCanManage(): bool
    {
        $role = request()->user()?->role;

        return $role !== null && $role->canManagePeople();
    }
}

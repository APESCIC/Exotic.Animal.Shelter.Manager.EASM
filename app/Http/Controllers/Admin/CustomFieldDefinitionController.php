<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomFieldDefinitionRequest;
use App\Http\Requests\UpdateCustomFieldDefinitionRequest;
use App\Models\CustomFieldDefinition;
use App\Models\LookupList;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomFieldDefinitionController extends Controller
{
    public function index(): View
    {
        $definitions = CustomFieldDefinition::query()
            ->forAnimal()
            ->with('lookupList')
            ->ordered()
            ->get();

        return view('admin.custom-fields.index', [
            'definitions' => $definitions,
        ]);
    }

    public function create(): View
    {
        return view('admin.custom-fields.create', [
            'definition' => new CustomFieldDefinition([
                'entity' => CustomFieldEntity::Animal,
                'type' => CustomFieldType::Text,
                'sort_order' => 0,
                'active' => true,
                'is_mandatory' => false,
                'show_on_new_record' => true,
            ]),
            'types' => CustomFieldType::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCustomFieldDefinitionRequest $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['entity'] = CustomFieldEntity::Animal;
        $data['slug'] = $this->uniqueSlug($data['label']);

        CustomFieldDefinition::query()->create($data);

        return redirect()
            ->route('admin.custom-fields.index')
            ->with('status', 'Custom field created.');
    }

    public function edit(CustomFieldDefinition $customField): View
    {
        return view('admin.custom-fields.edit', [
            'definition' => $customField,
            'types' => CustomFieldType::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCustomFieldDefinitionRequest $request, CustomFieldDefinition $customField): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($customField->label !== $data['label']) {
            $data['slug'] = $this->uniqueSlug($data['label'], $customField->id);
        }

        $customField->update($data);

        return redirect()
            ->route('admin.custom-fields.index')
            ->with('status', 'Custom field updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreCustomFieldDefinitionRequest|UpdateCustomFieldDefinitionRequest $request): array
    {
        $data = $request->safe()->only([
            'label',
            'type',
            'sort_order',
            'active',
            'is_mandatory',
            'tooltip',
            'show_on_new_record',
            'species_filter',
            'lookup_list_id',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['active'] = $request->boolean('active');
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['show_on_new_record'] = $request->boolean('show_on_new_record', true);

        $type = CustomFieldType::tryFrom((string) ($data['type'] ?? ''));
        if (! $type?->usesLookupList()) {
            $data['lookup_list_id'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $label, ?int $ignoreId = null): string
    {
        $base = CustomFieldDefinition::makeSlug($label);
        $slug = $base;
        $suffix = 2;

        while (
            CustomFieldDefinition::query()
                ->where('entity', CustomFieldEntity::Animal->value)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

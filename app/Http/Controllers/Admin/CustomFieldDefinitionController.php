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
            'entities' => CustomFieldEntity::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCustomFieldDefinitionRequest $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['label'], $data['entity']);

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
            'entities' => CustomFieldEntity::cases(),
            'lookupLists' => LookupList::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCustomFieldDefinitionRequest $request, CustomFieldDefinition $customField): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($customField->entity !== $data['entity'] && $customField->values()->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['entity' => 'Cannot change the entity while this field has saved values.']);
        }

        if ($customField->label !== $data['label'] || $customField->entity !== $data['entity']) {
            $data['slug'] = $this->uniqueSlug($data['label'], $data['entity'], $customField->id);
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
            'entity',
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
        $data['entity'] = CustomFieldEntity::from((string) $data['entity']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['active'] = $request->boolean('active');
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['show_on_new_record'] = $request->boolean('show_on_new_record');

        if ($data['entity'] === CustomFieldEntity::Person) {
            $data['species_filter'] = null;
        }

        $type = CustomFieldType::tryFrom((string) ($data['type'] ?? ''));
        if (! $type?->usesLookupList()) {
            $data['lookup_list_id'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $label, CustomFieldEntity $entity, ?int $ignoreId = null): string
    {
        $base = CustomFieldDefinition::makeSlug($label);
        $slug = $base;
        $suffix = 2;

        while (
            CustomFieldDefinition::query()
                ->where('entity', $entity->value)
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

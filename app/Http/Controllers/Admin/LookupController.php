<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLookupValueRequest;
use App\Http\Requests\UpdateLookupValueRequest;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\LookupList;
use App\Models\LookupValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LookupController extends Controller
{
    public function index(): View
    {
        $lists = LookupList::query()
            ->withCount('values')
            ->orderBy('name')
            ->get();

        return view('admin.lookups.index', [
            'lists' => $lists,
        ]);
    }

    public function show(LookupList $lookupList): View
    {
        $values = $lookupList->values()->ordered()->get();

        return view('admin.lookups.show', [
            'list' => $lookupList,
            'values' => $values,
        ]);
    }

    public function create(LookupList $lookupList): View
    {
        return view('admin.lookups.create', [
            'list' => $lookupList,
            'value' => new LookupValue([
                'sort_order' => 0,
                'active' => true,
            ]),
        ]);
    }

    public function store(StoreLookupValueRequest $request, LookupList $lookupList): RedirectResponse
    {
        $lookupList->values()->create($this->validatedData($request));

        return redirect()
            ->route('admin.lookups.show', $lookupList)
            ->with('status', 'Lookup value added.');
    }

    public function edit(LookupList $lookupList, LookupValue $lookupValue): View
    {
        $this->ensureValueBelongsToList($lookupList, $lookupValue);

        return view('admin.lookups.edit', [
            'list' => $lookupList,
            'value' => $lookupValue,
        ]);
    }

    public function update(
        UpdateLookupValueRequest $request,
        LookupList $lookupList,
        LookupValue $lookupValue,
    ): RedirectResponse {
        $this->ensureValueBelongsToList($lookupList, $lookupValue);

        $data = $this->validatedData($request);
        $oldName = $lookupValue->name;
        $lookupValue->update($data);

        if ($oldName !== $lookupValue->name) {
            $this->propagateRename($lookupList, $oldName, $lookupValue->name);
        }

        return redirect()
            ->route('admin.lookups.show', $lookupList)
            ->with('status', 'Lookup value updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreLookupValueRequest|UpdateLookupValueRequest $request): array
    {
        $data = $request->safe()->only(['name', 'description', 'sort_order', 'active']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['active'] = $request->boolean('active');

        return $data;
    }

    private function ensureValueBelongsToList(LookupList $list, LookupValue $value): void
    {
        abort_unless($value->lookup_list_id === $list->id, 404);
    }

    /**
     * Animal form fields and custom-field values store lookup names as text.
     * Keep them in sync when an admin renames a value.
     */
    private function propagateRename(LookupList $list, string $oldName, string $newName): void
    {
        $animalColumns = ['colour', 'breed_type', 'entry_reason', 'death_reason'];
        if (in_array($list->key, $animalColumns, true)) {
            Animal::query()
                ->where($list->key, $oldName)
                ->update([$list->key => $newName]);
        }

        if ($list->key === 'flags') {
            Animal::query()
                ->whereNotNull('flags')
                ->orderBy('id')
                ->each(function (Animal $animal) use ($oldName, $newName): void {
                    $flags = $animal->flags;
                    if (! is_array($flags) || ! in_array($oldName, $flags, true)) {
                        return;
                    }
                    $animal->flags = array_values(array_map(
                        static fn (string $flag): string => $flag === $oldName ? $newName : $flag,
                        $flags,
                    ));
                    $animal->save();
                });
        }

        CustomFieldDefinition::query()
            ->where('lookup_list_id', $list->id)
            ->each(function (CustomFieldDefinition $definition) use ($oldName, $newName): void {
                $definition->values()
                    ->where('value', $oldName)
                    ->update(['value' => $newName]);

                if ($definition->type === CustomFieldType::MultiLookup) {
                    $definition->values()
                        ->where('value', 'like', '%'.$oldName.'%')
                        ->orderBy('id')
                        ->each(function (CustomFieldValue $row) use ($oldName, $newName): void {
                            $names = $row->definition->decodedMultiLookup($row->value);
                            if (! in_array($oldName, $names, true)) {
                                return;
                            }
                            $names = array_map(
                                static fn (string $name): string => $name === $oldName ? $newName : $name,
                                $names,
                            );
                            $row->update(['value' => json_encode(array_values($names), JSON_UNESCAPED_UNICODE)]);
                        });
                }
            });
    }
}

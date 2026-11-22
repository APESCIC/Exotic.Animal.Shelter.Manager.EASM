<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLookupValueRequest;
use App\Http\Requests\UpdateLookupValueRequest;
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

        $lookupValue->update($this->validatedData($request));

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
}

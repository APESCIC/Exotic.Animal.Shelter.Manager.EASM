<?php

namespace App\Http\Controllers;

use App\Enums\WaitingListStatus;
use App\Enums\WaitingListType;
use App\Enums\WaitingListUrgency;
use App\Http\Requests\StoreWaitingListEntryRequest;
use App\Models\Person;
use App\Models\WaitingListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WaitingListEntryController extends Controller
{
    public function index(Request $request): View
    {
        $status = WaitingListStatus::tryFrom($request->string('status')->toString()) ?? WaitingListStatus::Open;
        $type = WaitingListType::tryFrom($request->string('type')->toString());
        $q = trim($request->string('q')->toString());

        $entries = WaitingListEntry::query()
            ->with('person')
            ->where('status', $status->value)
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                $like = '%'.$q.'%';
                $inner->where('species', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('details', 'like', $like)
                    ->orWhereHas('person', fn ($person) => $person->where('name', 'like', $like));
            }))
            ->byUrgency()
            ->paginate(30)
            ->withQueryString();

        return view('waiting-list.index', [
            'entries' => $entries,
            'statuses' => WaitingListStatus::cases(),
            'types' => WaitingListType::cases(),
            'status' => $status,
            'type' => $type,
            'q' => $q,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();

        return view('waiting-list.create', $this->formData(new WaitingListEntry([
            'type' => WaitingListType::Surrender,
            'urgency' => WaitingListUrgency::Normal,
            'added_on' => today(),
            'person_id' => $request->integer('person') ?: null,
        ])));
    }

    public function store(StoreWaitingListEntryRequest $request): RedirectResponse
    {
        WaitingListEntry::query()->create([
            ...$request->validated(),
            'status' => WaitingListStatus::Open,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('waiting-list.index')
            ->with('status', 'Added to the waiting list.');
    }

    public function edit(WaitingListEntry $waitingListEntry): View
    {
        $this->authorizeManage();

        return view('waiting-list.edit', $this->formData($waitingListEntry));
    }

    public function update(StoreWaitingListEntryRequest $request, WaitingListEntry $waitingListEntry): RedirectResponse
    {
        $waitingListEntry->update($request->validated());

        return redirect()
            ->route('waiting-list.index', ['status' => $waitingListEntry->status->value])
            ->with('status', 'Waiting list entry updated.');
    }

    public function close(Request $request, WaitingListEntry $waitingListEntry): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'status' => ['required', Rule::in([WaitingListStatus::Placed->value, WaitingListStatus::Removed->value])],
            'closed_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $waitingListEntry->update([
            'status' => $data['status'],
            'closed_reason' => $data['closed_reason'] ?? null,
            'closed_on' => today(),
        ]);

        return redirect()
            ->route('waiting-list.index')
            ->with('status', 'Waiting list entry closed.');
    }

    public function reopen(WaitingListEntry $waitingListEntry): RedirectResponse
    {
        $this->authorizeManage();

        $waitingListEntry->update([
            'status' => WaitingListStatus::Open,
            'closed_on' => null,
            'closed_reason' => null,
        ]);

        return redirect()
            ->route('waiting-list.index')
            ->with('status', 'Waiting list entry reopened.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(WaitingListEntry $entry): array
    {
        return [
            'entry' => $entry,
            'types' => WaitingListType::cases(),
            'urgencies' => WaitingListUrgency::cases(),
            'people' => Person::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManagePeople(), 403);
    }
}

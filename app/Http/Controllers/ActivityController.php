<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeView();

        $q = $request->string('q')->toString();
        $type = $request->string('type')->toString();
        $userId = $request->integer('user_id') ?: null;

        $typeOptions = AuditLog::subjectTypeOptions();
        if ($type !== '' && ! array_key_exists($type, $typeOptions)) {
            $type = '';
        }

        $entries = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($type !== '', fn ($query) => $query->where('auditable_type', $type))
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($q !== '', function ($query) use ($q, $typeOptions): void {
                $query->where(function ($inner) use ($q, $typeOptions): void {
                    $inner->where('action', 'like', '%'.$q.'%')
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$q.'%'));

                    foreach ($typeOptions as $morph => $label) {
                        if (stripos($label, $q) !== false) {
                            $inner->orWhere('auditable_type', $morph);
                        }
                    }
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        /** @var Collection<string, Collection<int, AuditLog>> $grouped */
        $grouped = $entries->getCollection()->groupBy(
            fn (AuditLog $entry): string => $entry->created_at
                ?->timezone(config('app.timezone'))
                ->toDateString() ?? 'unknown',
        );

        return view('activity.index', [
            'entries' => $entries,
            'grouped' => $grouped,
            'q' => $q,
            'type' => $type,
            'userId' => $userId,
            'typeOptions' => $typeOptions,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'today' => now()->timezone(config('app.timezone'))->toDateString(),
            'yesterday' => now()->timezone(config('app.timezone'))->subDay()->toDateString(),
        ]);
    }

    private function authorizeView(): void
    {
        $role = auth()->user()?->role;
        abort_unless($role !== null && $role->canViewActivity(), 403);
    }
}

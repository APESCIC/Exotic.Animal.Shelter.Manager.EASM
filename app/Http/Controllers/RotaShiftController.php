<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreRotaShiftRequest;
use App\Models\RotaShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RotaShiftController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['week' => ['nullable', 'date']]);

        $weekStart = ($request->filled('week')
            ? CarbonImmutable::parse($request->string('week')->toString())
            : CarbonImmutable::today())->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->addWeek();

        $shifts = RotaShift::query()
            ->with('user')
            ->startingBetween($weekStart, $weekEnd)
            ->orderBy('starts_at')
            ->get();

        $days = [];
        for ($day = $weekStart; $day->lt($weekEnd); $day = $day->addDay()) {
            $days[$day->toDateString()] = [
                'date' => $day,
                'shifts' => $shifts->filter(fn (RotaShift $shift): bool => $shift->starts_at->isSameDay($day))->values(),
            ];
        }

        return view('rota.index', [
            'weekStart' => $weekStart,
            'days' => $days,
            'canManage' => $request->user()->role->canManageRota(),
        ]);
    }

    public function mine(Request $request): View
    {
        return view('rota.mine', [
            'shifts' => RotaShift::query()
                ->upcomingFor($request->user())
                ->orderBy('starts_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')->toString()) : CarbonImmutable::today();

        return view('rota.create', [
            'shift' => new RotaShift([
                'starts_at' => $date->setTime(9, 0),
                'ends_at' => $date->setTime(17, 0),
            ]),
            'people' => $this->people(),
        ]);
    }

    public function store(StoreRotaShiftRequest $request): RedirectResponse
    {
        $shift = RotaShift::query()->create([
            ...$request->shiftData(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('rota.index', ['week' => $shift->starts_at->toDateString()])
            ->with('status', 'Shift added.');
    }

    public function edit(RotaShift $rotaShift): View
    {
        $this->authorizeManage();

        return view('rota.edit', [
            'shift' => $rotaShift,
            'people' => $this->people(),
        ]);
    }

    public function update(StoreRotaShiftRequest $request, RotaShift $rotaShift): RedirectResponse
    {
        $rotaShift->update($request->shiftData());

        return redirect()
            ->route('rota.index', ['week' => $rotaShift->starts_at->toDateString()])
            ->with('status', 'Shift updated.');
    }

    public function destroy(RotaShift $rotaShift): RedirectResponse
    {
        $this->authorizeManage();
        $week = $rotaShift->starts_at->toDateString();
        $rotaShift->delete();

        return redirect()
            ->route('rota.index', ['week' => $week])
            ->with('status', 'Shift removed.');
    }

    /**
     * @return Collection<int, User>
     */
    private function people(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value, UserRole::Volunteer->value])
            ->orderBy('name')
            ->get();
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageRota(), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Enums\MovementType;
use App\Http\Requests\StoreMovementRequest;
use App\Models\Animal;
use App\Models\Movement;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('movements.create', [
            'animal' => $animal,
            'movement' => new Movement([
                'type' => MovementType::Intake,
                'moved_at' => now()->toDateString(),
            ]),
            'types' => MovementType::cases(),
            'people' => Person::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMovementRequest $request, Animal $animal): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($animal, $data): void {
            $movement = $animal->movements()->create($data);

            if ($movement->type === MovementType::Deceased) {
                $animal->update([
                    'deceased_at' => $movement->moved_at,
                    'death_reason' => $movement->reason,
                ]);
            }
        });

        return redirect()
            ->to(AnimalTab::Movements->url($animal))
            ->with('status', 'Movement recorded.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageMovements(), 403);
    }
}

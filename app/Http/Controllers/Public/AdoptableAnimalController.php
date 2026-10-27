<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnimalMediaKind;
use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Setting;
use App\Services\AdoptableFeed;
use App\Support\UkDate;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdoptableAnimalController extends Controller
{
    public function index(): View
    {
        $animals = Animal::query()
            ->adoptable()
            ->orderBy('name')
            ->paginate(24);

        return view('public.adopt.index', [
            'animals' => $animals,
            'organisationName' => $this->organisationName(),
        ]);
    }

    public function show(Animal $animal): View
    {
        abort_unless($animal->isPubliclyAdoptable(), 404);

        $animal->load(['media' => function ($query): void {
            $query->where('kind', AnimalMediaKind::Photo->value)->orderByDesc('id');
        }]);

        return view('public.adopt.show', [
            'animal' => $animal,
            'organisationName' => $this->organisationName(),
            'dateOfBirth' => $animal->date_of_birth ? UkDate::format($animal->date_of_birth) : null,
        ]);
    }

    public function embed(): View
    {
        $animals = Animal::query()
            ->adoptable()
            ->orderBy('name')
            ->paginate(24);

        return view('public.adopt.embed', [
            'animals' => $animals,
            'organisationName' => $this->organisationName(),
            'embed' => true,
        ]);
    }

    public function apiIndex(AdoptableFeed $feed): JsonResponse
    {
        $animals = Animal::query()
            ->adoptable()
            ->orderBy('name')
            ->paginate(24);

        return response()->json([
            'data' => $animals->getCollection()->map(fn (Animal $animal) => $feed->payload($animal))->values(),
            'meta' => [
                'current_page' => $animals->currentPage(),
                'last_page' => $animals->lastPage(),
                'per_page' => $animals->perPage(),
                'total' => $animals->total(),
            ],
        ]);
    }

    public function apiShow(Animal $animal, AdoptableFeed $feed): JsonResponse
    {
        abort_unless($animal->isPubliclyAdoptable(), 404);

        return response()->json([
            'data' => $feed->payload($animal),
        ]);
    }

    private function organisationName(): string
    {
        return Setting::current()?->organisation_name ?? config('app.name');
    }
}

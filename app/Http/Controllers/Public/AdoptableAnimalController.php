<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnimalMediaKind;
use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Setting;
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

    public function apiIndex(): JsonResponse
    {
        $animals = Animal::query()
            ->adoptable()
            ->orderBy('name')
            ->paginate(24);

        return response()->json([
            'data' => $animals->getCollection()->map(fn (Animal $animal) => $this->apiPayload($animal))->values(),
            'meta' => [
                'current_page' => $animals->currentPage(),
                'last_page' => $animals->lastPage(),
                'per_page' => $animals->perPage(),
                'total' => $animals->total(),
            ],
        ]);
    }

    public function apiShow(Animal $animal): JsonResponse
    {
        abort_unless($animal->isPubliclyAdoptable(), 404);

        return response()->json([
            'data' => $this->apiPayload($animal),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function apiPayload(Animal $animal): array
    {
        $photoUrl = $animal->primaryPhotoUrl();

        return [
            'id' => $animal->id,
            'name' => $animal->name,
            'species' => $animal->species,
            'sex' => $animal->sex->value,
            'age_years' => $animal->age_years,
            'date_of_birth' => $animal->date_of_birth?->toDateString(),
            'colour' => $animal->colour,
            'bonded_animals' => $animal->bonded_animals,
            'photo_url' => $photoUrl !== null ? url($photoUrl) : null,
            'url' => route('public.adopt.show', $animal),
        ];
    }

    private function organisationName(): string
    {
        return Setting::current()?->organisation_name ?? config('app.name');
    }
}

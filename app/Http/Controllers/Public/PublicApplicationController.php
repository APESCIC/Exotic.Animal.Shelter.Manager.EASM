<?php

namespace App\Http\Controllers\Public;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicApplicationRequest;
use App\Models\Animal;
use App\Models\Application;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicApplicationController extends Controller
{
    public function index(): View
    {
        return view('public.apply.index', [
            'types' => ApplicationType::cases(),
            'organisationName' => $this->organisationName(),
        ]);
    }

    public function create(string $type): View
    {
        $applicationType = $this->resolveType($type);

        return view('public.apply.create', [
            'type' => $applicationType,
            'animal' => null,
            'organisationName' => $this->organisationName(),
        ]);
    }

    public function createForAnimal(Animal $animal): View
    {
        abort_unless($animal->isPubliclyAdoptable(), 404);

        return view('public.apply.create', [
            'type' => ApplicationType::Adopter,
            'animal' => $animal,
            'organisationName' => $this->organisationName(),
        ]);
    }

    public function store(StorePublicApplicationRequest $request, string $type): RedirectResponse
    {
        $applicationType = $this->resolveType($type);

        $this->persist($request, $applicationType, null);

        return redirect()
            ->route('public.apply.thanks')
            ->with('status', 'Thank you. Your application has been submitted.');
    }

    public function storeForAnimal(StorePublicApplicationRequest $request, Animal $animal): RedirectResponse
    {
        abort_unless($animal->isPubliclyAdoptable(), 404);

        $this->persist($request, ApplicationType::Adopter, $animal);

        return redirect()
            ->route('public.apply.thanks')
            ->with('status', 'Thank you. Your application has been submitted.');
    }

    public function thanks(): View
    {
        return view('public.apply.thanks', [
            'organisationName' => $this->organisationName(),
        ]);
    }

    private function persist(
        StorePublicApplicationRequest $request,
        ApplicationType $applicationType,
        ?Animal $preferredAnimal,
    ): void {
        $data = $request->safe()->only([
            'name',
            'email',
            'phone',
            'address_line1',
            'address_line2',
            'town_city',
            'county',
            'postcode',
            'message',
            'animal_id',
        ]);

        if ($preferredAnimal !== null) {
            $data['animal_id'] = $preferredAnimal->id;
        } elseif ($applicationType !== ApplicationType::Adopter) {
            $data['animal_id'] = null;
        } elseif (! empty($data['animal_id'])) {
            $animal = Animal::query()->find($data['animal_id']);
            abort_unless($animal !== null && $animal->isPubliclyAdoptable(), 404);
        }

        Application::query()->create([
            ...$data,
            'type' => $applicationType,
            'status' => ApplicationStatus::Submitted,
        ]);
    }

    private function resolveType(string $type): ApplicationType
    {
        $applicationType = ApplicationType::tryFrom($type);
        abort_unless($applicationType instanceof ApplicationType, 404);

        return $applicationType;
    }

    private function organisationName(): string
    {
        return Setting::current()?->organisation_name ?? config('app.name');
    }
}

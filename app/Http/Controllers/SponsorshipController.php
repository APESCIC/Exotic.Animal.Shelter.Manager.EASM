<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Enums\SponsorshipFrequency;
use App\Http\Requests\StoreSponsorshipRequest;
use App\Models\Animal;
use App\Models\Person;
use App\Models\Sponsorship;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SponsorshipController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorizeManage();

        return view('finance.sponsorships.create', [
            'sponsorship' => new Sponsorship([
                'person_id' => $request->integer('person') ?: null,
                'animal_id' => $request->integer('animal') ?: null,
                'starts_on' => today(),
                'frequency' => SponsorshipFrequency::Monthly,
            ]),
            'frequencies' => SponsorshipFrequency::cases(),
            'people' => Person::query()->orderBy('name')->get(['id', 'name']),
            'animals' => Animal::query()->orderBy('name')->get(['id', 'name', 'species']),
        ]);
    }

    public function store(StoreSponsorshipRequest $request): RedirectResponse
    {
        $sponsorship = Sponsorship::query()->create([
            ...$request->safe()->only(['person_id', 'animal_id', 'starts_on', 'ends_on', 'frequency', 'notes']),
            'amount_pence' => Money::toPence($request->string('amount')->toString()),
        ]);

        return redirect()
            ->to(AnimalTab::Overview->url($sponsorship->animal_id))
            ->with('status', 'Sponsorship added.');
    }

    public function end(Sponsorship $sponsorship): RedirectResponse
    {
        $this->authorizeManage();

        if ($sponsorship->ends_on === null || $sponsorship->ends_on->isFuture()) {
            $sponsorship->update(['ends_on' => today()]);
        }

        return redirect()
            ->back(fallback: AnimalTab::Overview->url($sponsorship->animal_id))
            ->with('status', 'Sponsorship ended.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageFinance(), 403);
    }
}

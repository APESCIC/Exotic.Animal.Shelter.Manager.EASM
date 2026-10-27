<?php

namespace App\Http\Controllers;

use App\Enums\GiftAidDeclarationMethod;
use App\Http\Requests\StoreGiftAidDeclarationRequest;
use App\Models\GiftAidDeclaration;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GiftAidDeclarationController extends Controller
{
    public function create(Person $person): View
    {
        $this->authorizeManage();

        return view('finance.gift-aid-declarations.create', [
            'person' => $person,
            'declaration' => new GiftAidDeclaration([
                'declared_on' => today(),
                'effective_from' => today(),
                'method' => GiftAidDeclarationMethod::Written,
            ]),
            'methods' => GiftAidDeclarationMethod::cases(),
        ]);
    }

    public function store(StoreGiftAidDeclarationRequest $request, Person $person): RedirectResponse
    {
        $person->giftAidDeclarations()->create($request->safe()->only([
            'declared_on',
            'effective_from',
            'method',
            'notes',
        ]));

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Gift Aid declaration recorded.');
    }

    public function cancel(GiftAidDeclaration $giftAidDeclaration): RedirectResponse
    {
        $this->authorizeManage();

        if ($giftAidDeclaration->cancelled_on === null) {
            $giftAidDeclaration->update(['cancelled_on' => today()]);
        }

        return redirect()
            ->route('people.show', $giftAidDeclaration->person_id)
            ->with('status', 'Gift Aid declaration cancelled from today.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageFinance(), 403);
    }
}

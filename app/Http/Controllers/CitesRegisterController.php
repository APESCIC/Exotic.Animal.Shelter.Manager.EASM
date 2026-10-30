<?php

namespace App\Http\Controllers;

use App\Services\CitesRegister;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CitesRegisterController extends Controller
{
    public function index(Request $request, CitesRegister $register): View
    {
        $this->authorizeView();
        $includeDeparted = $request->boolean('all');

        return view('compliance.cites', [
            'animals' => $register->animals($includeDeparted),
            'register' => $register,
            'includeDeparted' => $includeDeparted,
        ]);
    }

    public function csv(Request $request, CitesRegister $register): Response
    {
        $this->authorizeView();

        return response($register->csv($register->animals($request->boolean('all'))), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cites-register-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function authorizeView(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canViewCompliance(), 403);
    }
}

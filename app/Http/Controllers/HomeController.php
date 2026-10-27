<?php

namespace App\Http\Controllers;

use App\Services\DashboardAlerts;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(DashboardAlerts $alerts): View
    {
        return view('home', [
            'alerts' => $alerts->all(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DashboardAlerts;
use App\Services\ReminderSync;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(DashboardAlerts $alerts, ReminderSync $reminders): View
    {
        $reminders->sync();

        return view('home', [
            'alerts' => $alerts->all(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DashboardAlerts;
use App\Services\DashboardShelter;
use App\Services\ReminderSync;
use App\Services\ShelterView;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(
        DashboardAlerts $alerts,
        DashboardShelter $shelter,
        ShelterView $shelterView,
        ReminderSync $reminders,
    ): View {
        $reminders->sync();

        return view('home', [
            'alerts' => $alerts->all(),
            'shelter' => $shelter->panel($shelterView),
        ]);
    }
}

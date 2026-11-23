<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\DashboardAlerts;
use App\Services\DashboardShelter;
use App\Services\ReminderSync;
use App\Services\ShelterView;
use Illuminate\Support\Collection;
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

        $canViewActivity = auth()->user()?->role?->canViewActivity() === true;

        /** @var Collection<int, AuditLog>|null $recentActivity */
        $recentActivity = $canViewActivity
            ? AuditLog::query()
                ->with(['user', 'auditable'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : null;

        return view('home', [
            'alerts' => $alerts->all(),
            'shelter' => $shelter->panel($shelterView),
            'canViewActivity' => $canViewActivity,
            'recentActivity' => $recentActivity,
        ]);
    }
}

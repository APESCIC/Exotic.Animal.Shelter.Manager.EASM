<?php

namespace App\Http\Controllers;

use App\Services\GiftAidRow;
use App\Services\GiftAidSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GiftAidExportController extends Controller
{
    public function index(Request $request, GiftAidSchedule $schedule): View
    {
        $this->authorizeManage();
        [$from, $to] = $this->range($request);
        $rows = $schedule->rows($from, $to);

        return view('finance.gift-aid', [
            'from' => $from,
            'to' => $to,
            'claimable' => array_values(array_filter($rows, fn (GiftAidRow $row): bool => $row->isClaimable())),
            'needsAttention' => array_values(array_filter($rows, fn (GiftAidRow $row): bool => ! $row->isClaimable())),
        ]);
    }

    public function csv(Request $request, GiftAidSchedule $schedule): Response
    {
        $this->authorizeManage();
        [$from, $to] = $this->range($request);

        return response($schedule->csv($schedule->rows($from, $to)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="DRAFT-gift-aid-schedule-'.$from.'-to-'.$to.'.csv"',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $today = CarbonImmutable::today();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())
            : GiftAidSchedule::taxYearStart($today);
        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString()) : $today;

        return [$from->toDateString(), $to->toDateString()];
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageFinance(), 403);
    }
}

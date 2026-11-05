<?php

namespace App\Http\Controllers;

use App\Services\Reports\AnimalTimeline;
use App\Services\Reports\IntakeOutcomeReport;
use App\Services\Reports\OccupancyReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $this->authorizeView();

        return view('reports.index');
    }

    public function occupancy(Request $request, OccupancyReport $report): View
    {
        $this->authorizeView();
        $on = $this->day($request);
        $stays = $report->stays($on);

        return view('reports.occupancy', [
            'on' => $on,
            'stays' => $stays,
            'bySpecies' => $report->bySpecies($stays),
            'byEnclosure' => $report->byEnclosure($stays),
        ]);
    }

    public function occupancyCsv(Request $request, OccupancyReport $report): Response
    {
        $this->authorizeView();
        $on = $this->day($request);

        return $this->csvResponse($report->csv($report->stays($on), $on), 'occupancy-'.$on->toDateString());
    }

    public function intakeOutcome(Request $request, IntakeOutcomeReport $report): View
    {
        $this->authorizeView();
        [$from, $to] = $this->range($request, 12);

        return view('reports.intake-outcome', [
            'from' => $from,
            'to' => $to,
            'summary' => $report->summary($from, $to),
            'outcomeTypes' => IntakeOutcomeReport::outcomeTypes(),
        ]);
    }

    public function intakeOutcomeCsv(Request $request, IntakeOutcomeReport $report): Response
    {
        $this->authorizeView();
        [$from, $to] = $this->range($request, 12);

        return $this->csvResponse($report->csv($report->summary($from, $to)), 'intake-outcome-'.$from->toDateString().'-to-'.$to->toDateString());
    }

    public function timeline(Request $request, AnimalTimeline $timeline): View
    {
        $this->authorizeView();
        [$from, $to] = $this->range($request, 3);

        return view('reports.timeline', [
            'from' => $from,
            'to' => $to,
            'rows' => $timeline->rows($from, $to),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function timelineCsv(Request $request, AnimalTimeline $timeline): Response
    {
        $this->authorizeView();
        [$from, $to] = $this->range($request, 3);

        return $this->csvResponse($timeline->csv($timeline->rows($from, $to), CarbonImmutable::today()), 'timeline-'.$from->toDateString().'-to-'.$to->toDateString());
    }

    private function day(Request $request): CarbonImmutable
    {
        $request->validate(['on' => ['nullable', 'date']]);

        return $request->filled('on')
            ? CarbonImmutable::parse($request->string('on')->toString())->startOfDay()
            : CarbonImmutable::today();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request, int $defaultMonths): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString())->startOfDay() : CarbonImmutable::today();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : $to->subMonthsNoOverflow($defaultMonths)->addDay();

        return [$from, $to];
    }

    private function csvResponse(string $csv, string $name): Response
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
        ]);
    }

    private function authorizeView(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canViewReports(), 403);
    }
}

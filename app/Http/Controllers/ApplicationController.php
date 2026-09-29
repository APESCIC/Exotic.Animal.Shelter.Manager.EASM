<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Models\Application;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $openApplications = Application::query()
            ->with(['animal', 'person', 'reviewer'])
            ->open()
            ->ofType($type !== '' ? $type : null)
            ->ofStatus($status !== '' ? $status : null)
            ->orderByDesc('id')
            ->get();

        $closedApplications = Application::query()
            ->with(['animal', 'person', 'reviewer'])
            ->whereIn('status', [
                ApplicationStatus::Accepted->value,
                ApplicationStatus::Rejected->value,
            ])
            ->ofType($type !== '' ? $type : null)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('applications.index', [
            'openApplications' => $openApplications,
            'closedApplications' => $closedApplications,
            'type' => $type,
            'status' => $status,
        ]);
    }

    public function show(Application $application): View
    {
        $application->load(['animal', 'person', 'reviewer']);

        return view('applications.show', [
            'application' => $application,
        ]);
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): RedirectResponse
    {
        $status = ApplicationStatus::from($request->validated('status'));
        $note = $request->validated('status_note');

        if ($status === ApplicationStatus::Accepted) {
            return $this->accept($request, $application);
        }

        $application->update([
            'status' => $status,
            'status_note' => $note !== null && $note !== '' ? $note : $application->status_note,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('applications.show', $application)
            ->with('status', 'Application status updated.');
    }

    public function accept(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeManage();

        if ($application->status === ApplicationStatus::Accepted && $application->person_id !== null) {
            return redirect()
                ->route('applications.show', $application)
                ->with('status', 'Application was already accepted.');
        }

        $person = Person::query()->create([
            'name' => $application->name,
            'category' => $application->type->personCategory(),
            'email' => $application->email,
            'phone' => $application->phone,
            'address_line1' => $application->address_line1,
            'address_line2' => $application->address_line2,
            'town_city' => $application->town_city,
            'county' => $application->county,
            'postcode' => $application->postcode,
            'banned' => false,
            'homechecked' => false,
            'notes' => $application->message,
        ]);

        $application->update([
            'status' => ApplicationStatus::Accepted,
            'person_id' => $person->id,
            'status_note' => $request->input('status_note') ?: $application->status_note,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('applications.show', $application)
            ->with('status', 'Application accepted. Contact record created.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role instanceof UserRole && $role->canManageApplications(), 403);
    }
}

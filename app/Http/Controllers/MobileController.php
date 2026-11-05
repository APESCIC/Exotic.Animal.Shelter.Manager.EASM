<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\Enclosure;
use App\Models\MedicalRecord;
use App\Models\RotaShift;
use App\Services\ReminderSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Touch-first care pages for phones. Writes post to the normal controllers; the
 * offline queue in public/js/offline-queue.js replays them when the phone is back online.
 */
class MobileController extends Controller
{
    public function index(Request $request, ReminderSync $reminders): View
    {
        $reminders->sync();
        $user = $request->user();

        return view('mobile.index', [
            'shifts' => RotaShift::query()
                ->where('user_id', $user->id)
                ->where('ends_at', '>', now())
                ->where('starts_at', '<', today()->addDay())
                ->orderBy('starts_at')
                ->get(),
            'tasks' => DiaryTask::query()
                ->with(['animal', 'enclosure'])
                ->open()
                ->whereNotNull('due_on')
                ->whereDate('due_on', '<=', today())
                ->orderBy('due_on')
                ->limit(30)
                ->get(),
            'medical' => $this->dueMedical()->with('animal')->limit(30)->get(),
        ]);
    }

    public function animals(Request $request): View
    {
        $q = trim($request->string('q')->toString());

        return view('mobile.animals', [
            'q' => $q,
            'animals' => Animal::query()
                ->inCare()
                ->with('enclosure')
                ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('species', 'like', '%'.$q.'%')
                        ->orWhere('identifying_code', 'like', '%'.$q.'%');
                }))
                ->orderBy('name')
                ->limit(100)
                ->get(),
        ]);
    }

    public function animal(Animal $animal): View
    {
        $animal->load(['enclosure', 'latestMovement']);

        return view('mobile.animal', [
            'animal' => $animal,
            'tasks' => $animal->diaryTasks()->open()->orderByRaw('due_on is null')->orderBy('due_on')->get(),
            'medical' => $this->dueMedical()->where('animal_id', $animal->id)->get(),
            'unsigned' => $animal->documents()->whereNull('signed_at')->latest()->get(),
        ]);
    }

    public function enclosure(Enclosure $enclosure): View
    {
        $enclosure->load(['occupants', 'dueEquipment']);

        return view('mobile.enclosure', [
            'enclosure' => $enclosure,
            'lastLog' => $enclosure->logs()->latest('recorded_at')->first(),
        ]);
    }

    public function scan(): View
    {
        return view('mobile.scan', [
            'enclosures' => Enclosure::query()->active()->ordered()->get(),
        ]);
    }

    /**
     * A fresh CSRF token for replaying queued writes from a page cached offline.
     */
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()]);
    }

    /**
     * @return Builder<MedicalRecord>
     */
    private function dueMedical(): Builder
    {
        return MedicalRecord::query()
            ->whereNull('given_on')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', today())
            ->whereHas('animal', fn (Builder $animal) => $animal->whereNull('deceased_at'))
            ->orderBy('due_on');
    }
}

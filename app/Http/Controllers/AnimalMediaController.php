<?php

namespace App\Http\Controllers;

use App\Enums\AnimalMediaKind;
use App\Http\Requests\StoreAnimalMediaRequest;
use App\Models\Animal;
use App\Models\AnimalMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnimalMediaController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('media.create', [
            'animal' => $animal,
        ]);
    }

    public function store(StoreAnimalMediaRequest $request, Animal $animal): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('media_file');
        $mime = (string) $file->getMimeType();
        $isPdf = $mime === 'application/pdf' || strtolower((string) $file->getClientOriginalExtension()) === 'pdf';
        $kind = $isPdf ? AnimalMediaKind::Document : AnimalMediaKind::Photo;

        $path = $file->store('media/animals/'.$animal->id, 'public');

        $animal->media()->create([
            'disk_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'kind' => $kind,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('animals.show', $animal)
            ->with('status', 'Media uploaded.');
    }

    public function destroy(AnimalMedia $medium): RedirectResponse
    {
        $this->authorizeManage();

        $animalId = $medium->animal_id;

        if ($medium->disk_path) {
            Storage::disk('public')->delete($medium->disk_path);
        }

        $medium->delete();

        return redirect()
            ->route('animals.show', $animalId)
            ->with('status', 'Media removed.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageMedia(), 403);
    }
}

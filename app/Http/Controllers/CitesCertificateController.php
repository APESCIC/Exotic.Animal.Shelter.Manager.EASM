<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Http\Requests\StoreCitesCertificateRequest;
use App\Models\Animal;
use App\Models\CitesCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CitesCertificateController extends Controller
{
    public function store(StoreCitesCertificateRequest $request, Animal $animal): RedirectResponse
    {
        $file = $request->file('certificate_file');

        $animal->citesCertificates()->create([
            ...$request->safe()->only(['type', 'certificate_number', 'issued_on', 'notes']),
            'file_path' => $file?->store('cites/'.$animal->id, CitesCertificate::DISK),
            'original_name' => $file?->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->to(AnimalTab::Legal->url($animal))
            ->with('status', 'Certificate added.');
    }

    public function download(CitesCertificate $citesCertificate): StreamedResponse
    {
        $role = request()->user()?->role;
        abort_unless($role !== null && $role->canViewCompliance(), 403);
        abort_unless($citesCertificate->hasFile() && Storage::disk(CitesCertificate::DISK)->exists($citesCertificate->file_path), 404);

        return Storage::disk(CitesCertificate::DISK)->download(
            $citesCertificate->file_path,
            $citesCertificate->original_name ?? basename($citesCertificate->file_path),
        );
    }

    public function destroy(CitesCertificate $citesCertificate): RedirectResponse
    {
        $role = request()->user()?->role;
        abort_unless($role !== null && $role->canManageAnimals(), 403);

        if ($citesCertificate->hasFile()) {
            Storage::disk(CitesCertificate::DISK)->delete($citesCertificate->file_path);
        }

        $animalId = $citesCertificate->animal_id;
        $citesCertificate->delete();

        return redirect()
            ->to(AnimalTab::Legal->url($animalId))
            ->with('status', 'Certificate removed.');
    }
}

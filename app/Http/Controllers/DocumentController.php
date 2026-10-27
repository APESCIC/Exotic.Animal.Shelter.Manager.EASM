<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Animal;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Person;
use App\Rules\SignatureImage;
use App\Services\DocumentPdf;
use App\Services\DocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentPdf $pdf,
    ) {}

    public function create(Request $request): View
    {
        $this->authorizeManage();

        return view('documents.create', [
            'templates' => DocumentTemplate::query()->active()->orderBy('name')->get(),
            'animals' => Animal::query()->orderBy('name')->get(['id', 'name', 'species']),
            'people' => Person::query()->orderBy('name')->get(['id', 'name']),
            'selectedAnimalId' => $request->integer('animal') ?: null,
            'selectedPersonId' => $request->integer('person') ?: null,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $template = DocumentTemplate::query()->findOrFail($request->integer('document_template_id'));
        $animal = $request->filled('animal_id') ? Animal::query()->findOrFail($request->integer('animal_id')) : null;
        $person = $request->filled('person_id') ? Person::query()->findOrFail($request->integer('person_id')) : null;

        $document = Document::query()->create([
            'document_template_id' => $template->id,
            'animal_id' => $animal?->id,
            'person_id' => $person?->id,
            'type' => $template->type,
            'title' => $template->name.' · '.implode(' · ', array_filter([$animal?->name, $person?->name])),
            'body_html' => $this->renderer->render($template, $animal, $person, $request->input('notes')),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Document generated.');
    }

    public function show(Document $document): View
    {
        $document->load(['animal', 'person', 'creator']);

        return view('documents.show', [
            'document' => $document,
        ]);
    }

    public function sign(SignDocumentRequest $request, Document $document): RedirectResponse
    {
        if ($document->isSigned()) {
            return redirect()
                ->route('documents.show', $document)
                ->withErrors(['signature' => 'This document is already signed and cannot be changed.']);
        }

        $directory = 'documents/'.$document->id;
        $signaturePath = $directory.'/signature.png';
        Storage::disk('local')->put($signaturePath, (string) SignatureImage::decode($request->string('signature')->toString()));

        DB::transaction(function () use ($document, $request, $signaturePath, $directory): void {
            $document->update([
                'signature_path' => $signaturePath,
                'signed_by_name' => $request->string('signed_by_name')->toString(),
                'signed_at' => now(),
            ]);

            $pdfPath = $directory.'/'.$this->pdf->filename($document);
            Storage::disk('local')->put($pdfPath, $this->pdf->output($document));
            $document->update(['pdf_path' => $pdfPath]);
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Document signed and archived.');
    }

    public function pdf(Document $document): Response
    {
        $filename = $this->pdf->filename($document);

        if ($document->pdf_path !== null && Storage::disk('local')->exists($document->pdf_path)) {
            return Storage::disk('local')->download($document->pdf_path, $filename);
        }

        return response($this->pdf->output($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageDocuments(), 403);
    }
}

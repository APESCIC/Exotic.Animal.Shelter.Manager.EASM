<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentTemplateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use App\Services\DocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.document-templates.index', [
            'templates' => DocumentTemplate::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.document-templates.create', [
            'template' => new DocumentTemplate(['type' => DocumentTemplateType::AdoptionContract, 'active' => true]),
            'types' => DocumentTemplateType::cases(),
            'tokens' => DocumentRenderer::TOKENS,
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request): RedirectResponse
    {
        DocumentTemplate::query()->create($this->validatedData($request));

        return redirect()
            ->route('admin.document-templates.index')
            ->with('status', 'Document template created.');
    }

    public function edit(DocumentTemplate $documentTemplate): View
    {
        return view('admin.document-templates.edit', [
            'template' => $documentTemplate,
            'types' => DocumentTemplateType::cases(),
            'tokens' => DocumentRenderer::TOKENS,
        ]);
    }

    public function update(StoreDocumentTemplateRequest $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->update($this->validatedData($request));

        return redirect()
            ->route('admin.document-templates.index')
            ->with('status', 'Document template updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreDocumentTemplateRequest $request): array
    {
        $data = $request->safe()->only(['name', 'type', 'body']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}

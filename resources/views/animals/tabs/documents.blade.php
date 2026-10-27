<p class="hint">Contracts, invoices and enclosure cards generated from templates. Signed documents are archived as PDF and cannot be changed.</p>
@include('documents._list', ['documents' => $animal->documents])

@if (auth()->user()?->role?->canManageDocuments())
    <p><a class="button" href="{{ route('documents.create', ['animal' => $animal->id]) }}">Generate document</a></p>
@endif

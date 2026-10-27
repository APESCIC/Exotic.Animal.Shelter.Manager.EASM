@if ($documents->isEmpty())
    <p class="hint">No documents yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Created</th>
                <th>Status</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documents as $document)
                <tr>
                    <td><a href="{{ route('documents.show', $document) }}">{{ $document->title }}</a></td>
                    <td>{{ $document->type->label() }}</td>
                    <td>{{ \App\Support\UkDate::format($document->created_at) }}</td>
                    <td>
                        @if ($document->isSigned())
                            <x-badge tone="adoptable">Signed {{ \App\Support\UkDate::format($document->signed_at) }}</x-badge>
                        @elseif ($document->awaitingSignature())
                            <x-badge tone="quarantine">Awaiting signature</x-badge>
                        @else
                            <x-badge>Draft</x-badge>
                        @endif
                    </td>
                    <td><a href="{{ route('documents.pdf', $document) }}">PDF</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

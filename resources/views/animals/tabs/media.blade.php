<p class="hint">Extra photos and PDFs. The primary photo is set on the animal record.</p>
@if ($animal->media->isEmpty())
    <p class="hint">No extra media yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Name</th>
                <th>Kind</th>
                <th>Uploaded by</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->media as $item)
                <tr>
                    <td>
                        @if ($item->isPhoto())
                            <a href="{{ $item->url() }}" target="_blank" rel="noopener">
                                <img class="thumb" src="{{ $item->url() }}" alt="{{ $item->original_name }}">
                            </a>
                        @else
                            <a href="{{ $item->url() }}" target="_blank" rel="noopener">Download PDF</a>
                        @endif
                    </td>
                    <td>{{ $item->original_name }}</td>
                    <td>{{ $item->kind->label() }}</td>
                    <td>{{ $item->uploader?->name ?: '—' }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManageMedia())
                            <form class="inline-form" method="post" action="{{ route('media.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button-danger">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageMedia())
    <p><a class="button" href="{{ route('animals.media.create', $animal) }}">Upload media</a></p>
@endif

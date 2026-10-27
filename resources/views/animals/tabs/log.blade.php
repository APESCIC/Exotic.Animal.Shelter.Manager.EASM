<p class="hint">Daily observations by staff and volunteers.</p>
@if ($animal->observations->isEmpty())
    <p class="hint">No daily observations yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>By</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->observations as $observation)
                <tr>
                    <td>{{ \App\Support\UkDate::format($observation->observed_on) }}</td>
                    <td>{{ $observation->user?->name ?: '—' }}</td>
                    <td>{{ $observation->body }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageMedical())
    <p><a class="button" href="{{ route('animals.observations.create', $animal) }}">Add observation</a></p>
@endif

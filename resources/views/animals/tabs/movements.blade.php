@if ($animal->movements->isEmpty())
    <p class="hint">No movements recorded yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Reason</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->movements as $movement)
                <tr>
                    <td>{{ \App\Support\UkDate::format($movement->moved_at) }}</td>
                    <td>{{ $movement->type->label() }}</td>
                    <td>
                        @if ($movement->person)
                            <a href="{{ route('people.show', $movement->person) }}">{{ $movement->person->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $movement->reason ?: '—' }}</td>
                    <td>{{ $movement->notes ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageMovements())
    <p><a class="button" href="{{ route('animals.movements.create', $animal) }}">Record movement</a></p>
@endif

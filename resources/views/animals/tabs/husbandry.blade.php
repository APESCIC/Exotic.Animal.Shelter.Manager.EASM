@php
    use App\Support\UkDate;
@endphp

<table>
    <tr><th>Location</th><td>{{ $animal->location ?: '—' }}</td></tr>
    <tr>
        <th>Enclosure</th>
        <td>
            @if ($animal->enclosure)
                <a href="{{ route('enclosures.show', $animal->enclosure) }}">{{ $animal->enclosure->label() }}</a>
            @else
                —
            @endif
        </td>
    </tr>
    <tr><th>Bonded animals</th><td>{{ $animal->bonded_animals ?: '—' }}</td></tr>
</table>

<h3>Diets</h3>
@if ($animal->diets->isEmpty())
    <p class="hint">No diets recorded yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Started</th>
                <th>Ended</th>
                <th>Details</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->diets as $diet)
                <tr>
                    <td>{{ $diet->name }}</td>
                    <td>{{ $diet->started_on ? UkDate::format($diet->started_on) : '—' }}</td>
                    <td>{{ $diet->ended_on ? UkDate::format($diet->ended_on) : '—' }}</td>
                    <td>{{ $diet->details ?: '—' }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManageMedical())
                            <a href="{{ route('diets.edit', $diet) }}">Edit</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageMedical())
    <p><a class="button" href="{{ route('animals.diets.create', $animal) }}">Add diet</a></p>
@endif

@php
    use App\Support\UkDate;
@endphp

<h3>Medical</h3>
<p class="hint">Vaccinations, tests, and treatments with due / given / expiry dates. Free-text names for exotic schedules.</p>
@if ($animal->medicalRecords->isEmpty())
    <p class="hint">No medical records yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Name</th>
                <th>Due</th>
                <th>Given</th>
                <th>Expires</th>
                <th>Notes</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->medicalRecords as $record)
                <tr>
                    <td>{{ $record->type->label() }}</td>
                    <td>{{ $record->name }}</td>
                    <td>{{ $record->due_on ? UkDate::format($record->due_on) : '—' }}</td>
                    <td>{{ $record->given_on ? UkDate::format($record->given_on) : '—' }}</td>
                    <td>{{ $record->expires_on ? UkDate::format($record->expires_on) : '—' }}</td>
                    <td>{{ $record->notes ?: '—' }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManageMedical())
                            <a href="{{ route('medical.edit', $record) }}">Edit</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageMedical())
    <p><a class="button" href="{{ route('animals.medical.create', $animal) }}">Add medical record</a></p>
@endif

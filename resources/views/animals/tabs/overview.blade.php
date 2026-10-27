@php
    use App\Support\UkDate;
@endphp

<div class="overview-grid">
    @if ($animal->primaryPhotoUrl())
        <img class="photo" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}">
    @endif

    <table>
        <tr><th>Sex</th><td>{{ $animal->sex->label() }}</td></tr>
        <tr><th>Date of birth</th><td>{{ $animal->date_of_birth ? UkDate::format($animal->date_of_birth) : '—' }}</td></tr>
        <tr><th>Age (years)</th><td>{{ $animal->age_years ?? '—' }}</td></tr>
        <tr><th>Colour</th><td>{{ $animal->colour ?: '—' }}</td></tr>
        <tr><th>Identifying code</th><td>{{ $animal->identifying_code ?: '—' }}</td></tr>
        <tr><th>Current status</th><td>{{ $animal->currentMovementType()?->label() ?? '—' }}</td></tr>
        <tr><th>Entry reason</th><td>{{ $animal->entry_reason ?: '—' }}</td></tr>
        <tr><th>Flags</th><td>{{ $animal->flags ?: '—' }}</td></tr>
        <tr><th>Non-shelter</th><td>{{ $animal->non_shelter ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Listed for public adoption</th><td>{{ $animal->is_adoptable ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Deceased</th><td>{{ $animal->deceased_at ? UkDate::format($animal->deceased_at) : '—' }}</td></tr>
        <tr><th>Death reason</th><td>{{ $animal->death_reason ?: '—' }}</td></tr>
    </table>
</div>

@php
    $customValues = $animal->customFieldValues->keyBy('definition_id');
@endphp
@if ($customFieldDefinitions->isNotEmpty())
    <h3>Custom fields</h3>
    <table>
        @foreach ($customFieldDefinitions as $definition)
            <tr>
                <th>{{ $definition->label }}</th>
                <td>{{ $definition->displayValue($customValues->get($definition->id)?->value) }}</td>
            </tr>
        @endforeach
    </table>
@endif

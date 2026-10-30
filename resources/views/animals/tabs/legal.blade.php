@php
    use App\Enums\CitesCertificateType;
    use App\Support\UkDate;
    $role = auth()->user()?->role;
@endphp

<p class="hint">CITES and Dangerous Wild Animals records for this animal.</p>

<table>
    <tr>
        <th>CITES Annex</th>
        <td>
            {{ $animal->cites_annex?->label() ?? 'Not recorded' }}
            @if ($animal->isMissingArticleTen())
                <x-badge tone="dwa" data-missing-article-10>Article 10 missing</x-badge>
            @endif
        </td>
    </tr>
    <tr>
        <th>CITES reference</th>
        <td>
            {{ $animal->cites ?: '—' }}
            @if ($animal->hasCitesListing())
                <x-badge tone="cites">Listed</x-badge>
            @endif
        </td>
    </tr>
    <tr>
        <th>DWA</th>
        <td>
            {{ $animal->dwa ?: '—' }}
            @if ($animal->hasDwaListing())
                <x-badge tone="dwa">Licensed</x-badge>
            @endif
        </td>
    </tr>
</table>

@if ($role?->canManageAnimals())
    <p><a class="button" href="{{ route('animals.edit', $animal) }}#cites_annex">Edit CITES / DWA</a></p>
@endif

<h3>DWA licences</h3>
@if ($animal->dwaLicences->isEmpty())
    <p class="hint">Not linked to a DWA licence. Link animals from the licence under <a href="{{ route('compliance.dwa.index') }}">DWA licences</a>.</p>
@else
    <table>
        <thead>
            <tr><th>Licence</th><th>Council</th><th>Expires</th></tr>
        </thead>
        <tbody>
            @foreach ($animal->dwaLicences as $licence)
                <tr data-dwa-licence="{{ $licence->id }}">
                    <td><a href="{{ route('compliance.dwa.show', $licence) }}">{{ $licence->licence_number }}</a></td>
                    <td>{{ $licence->issuing_authority }}</td>
                    <td>
                        {{ UkDate::format($licence->expires_on) }}
                        <x-badge :tone="$licence->statusTone()">{{ $licence->statusLabel() }}</x-badge>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h3>CITES certificates</h3>
@include('compliance._not-legal-advice')

@if ($animal->citesCertificates->isEmpty())
    <p class="hint">No certificates on file.</p>
@else
    <table>
        <thead>
            <tr><th>Certificate</th><th>Number</th><th>Issued</th><th>File</th><th>Notes</th><th><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
            @foreach ($animal->citesCertificates as $certificate)
                <tr data-certificate="{{ $certificate->id }}">
                    <td>{{ $certificate->type->label() }}</td>
                    <td>{{ $certificate->certificate_number }}</td>
                    <td>{{ $certificate->issued_on ? UkDate::format($certificate->issued_on) : '—' }}</td>
                    <td>
                        @if ($certificate->hasFile() && $role?->canViewCompliance())
                            <a href="{{ route('cites-certificates.download', $certificate) }}">{{ $certificate->original_name ?? 'Download' }}</a>
                        @elseif ($certificate->hasFile())
                            On file
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $certificate->notes ?: '—' }}</td>
                    <td>
                        @if ($role?->canManageAnimals())
                            <form class="inline-form" method="post" action="{{ route('cites-certificates.destroy', $certificate) }}" onsubmit="return window.confirm('Remove this certificate and its file?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="linkish">Remove</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($role?->canManageAnimals())
    <details class="card">
        <summary>Add certificate</summary>
        <form method="post" action="{{ route('animals.cites-certificates.store', $animal) }}" enctype="multipart/form-data">
            @csrf
            <label for="certificate_type">Type</label>
            <select id="certificate_type" name="type" required>
                @foreach (CitesCertificateType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type', CitesCertificateType::Article10->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>

            <label for="certificate_number">Certificate number</label>
            <input id="certificate_number" type="text" name="certificate_number" value="{{ old('certificate_number') }}" required maxlength="255">

            <label for="issued_on">Issued on</label>
            <input id="issued_on" type="date" name="issued_on" value="{{ old('issued_on') }}">

            <label for="certificate_file">Scan or PDF (optional)</label>
            <input id="certificate_file" type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png">
            <p class="hint">Stored privately. Only admin, staff and read-only users can download it.</p>

            <label for="certificate_notes">Notes</label>
            <input id="certificate_notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="2000">

            <p><button type="submit" class="button-primary">Add certificate</button></p>
        </form>
    </details>
@endif

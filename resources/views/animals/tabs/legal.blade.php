<p class="hint">CITES and Dangerous Wild Animals references for this animal. Registers and expiry tracking arrive with the compliance milestone.</p>

<table>
    <tr>
        <th>CITES</th>
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

@if (auth()->user()?->role?->canManageAnimals())
    <p><a class="button" href="{{ route('animals.edit', $animal) }}#cites">Edit CITES / DWA</a></p>
@endif

<span class="badges">
    @if ($animal->deceased_at)
        <x-badge>Deceased</x-badge>
    @elseif ($animal->isInQuarantine())
        <x-badge tone="quarantine">Quarantine</x-badge>
    @endif
    @if ($animal->cites)
        <x-badge tone="cites" title="CITES {{ $animal->cites }}">CITES</x-badge>
    @endif
    @if ($animal->dwa)
        <x-badge tone="dwa" title="DWA {{ $animal->dwa }}">DWA</x-badge>
    @endif
    @if ($animal->isPubliclyAdoptable())
        <x-badge tone="adoptable">Adoptable</x-badge>
    @endif
</span>

<span data-occupancy="{{ $enclosure->id }}">{{ $enclosure->occupancyLabel() }}</span>
@if ($enclosure->isOverCapacity())
    <x-badge tone="quarantine" data-over-capacity>Over capacity</x-badge>
@elseif ($enclosure->isFull())
    <x-badge>Full</x-badge>
@endif
@unless ($enclosure->active)
    <x-badge>Retired</x-badge>
@endunless

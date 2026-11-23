@php
    /** @var \App\Models\Animal $animal */
    $photoUrl = $animal->primaryPhotoUrl();
    $alt = $photoUrl
        ? 'Primary photo of '.$animal->name
        : 'No photo for '.$animal->name;
@endphp
<a class="shelter-tile" href="{{ route('animals.show', $animal) }}">
    @if ($photoUrl)
        <img class="shelter-tile-photo" src="{{ $photoUrl }}" alt="{{ $alt }}" width="160" height="160" loading="lazy">
    @else
        <span class="shelter-tile-photo shelter-tile-photo-empty" role="img" aria-label="{{ $alt }}">No photo</span>
    @endif
    <span class="shelter-tile-body">
        <span class="shelter-tile-name">{{ $animal->name }}</span>
        <span class="shelter-tile-meta">{{ $animal->sex->label() }} · {{ $animal->species }}</span>
        <span class="shelter-tile-badges">@include('animals._badges')</span>
    </span>
</a>

@php
    /** @var \App\Models\Animal $animal */
    $photoUrl = $animal->primaryPhotoUrl();
    $alt = $photoUrl
        ? 'Primary photo of '.$animal->name
        : 'No photo for '.$animal->name;
@endphp
<li class="shelter-list-item">
    @if ($photoUrl)
        <img class="thumb" src="{{ $photoUrl }}" alt="{{ $alt }}" width="56" height="56" loading="lazy">
    @else
        <span class="thumb thumb-empty" role="img" aria-label="{{ $alt }}">No photo</span>
    @endif
    <div class="shelter-list-body">
        <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
        — {{ $animal->sex->label() }} · {{ $animal->species }}
        @if ($animal->enclosure && ($group ?? null) !== \App\Enums\ShelterGroup::Enclosure)
            · enclosure <a href="{{ route('enclosures.show', $animal->enclosure) }}">{{ $animal->enclosure->name }}</a>
        @endif
        @include('animals._badges')
    </div>
</li>

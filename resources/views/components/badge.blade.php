@props(['tone' => 'neutral'])

<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>{{ $slot }}</span>

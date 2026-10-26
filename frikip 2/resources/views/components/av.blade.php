{{-- Avatar rond aux couleurs du sport, avec les initiales. <x-av :name="$a['name']" :sport="$a['sport']" /> --}}
@props(['name' => '?', 'sport' => '', 'lg' => false])
@php
    $initials = collect(preg_split('/\s+/u', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $color = \App\Terrain\SportFields::color((string) $sport)[0];
@endphp
<span {{ $attributes->merge(['class' => 'av'.($lg ? ' lg' : '')]) }} style="background:{{ $color }}" aria-hidden="true">{{ $initials }}</span>

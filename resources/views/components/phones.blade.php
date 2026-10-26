{{-- Numéros de contact de Frikip (config/terrain.php, « phones » ; TERRAIN_TELEPHONES dans .env). Lien d'appel et WhatsApp. --}}
@props(['wa' => false, 'sep' => ' · '])
@php
  $nums = (array) config('terrain.phones', []);
  $intl = fn ($n) => preg_replace('/^00/', '', preg_replace('/\D/', '', $n));
@endphp
@foreach ($nums as $i => $n)@if ($i){{ $sep }}@endif<a href="tel:+{{ $intl($n) }}">{{ $n }}</a>@if ($wa) (<a href="https://wa.me/{{ $intl($n) }}" target="_blank" rel="noopener">WhatsApp</a>)@endif @endforeach

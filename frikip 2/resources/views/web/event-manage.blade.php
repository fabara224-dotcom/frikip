@extends('layouts.web')

@section('title', __('Inscrits').' – '.$e->title)

@section('content')
<div class="page gut">
  <a href="{{ route('events.show', $e->id) }}">← {{ $e->title }}</a>
  <h1>{{ __('Inscrits') }} ({{ $rows->count() }} / {{ $e->capacity }})</h1>
  <p class="meta">{{ __('Le jour J : scanne le QR code du billet avec l’appareil photo du téléphone, ou cherche le code ci-dessous, puis touche « Présent ».') }}</p>
  <input type="search" id="q" placeholder="{{ __('Nom ou code du billet') }}" aria-label="{{ __('Rechercher') }}" style="margin:8px 0 12px">
  <div class="list" id="regs">
    @forelse ($rows as $r)
      <div class="li" data-s="{{ \Illuminate\Support\Str::lower($r->name.' '.$r->code) }}">
        <span class="grow"><span class="t">{{ $r->name }}</span><span class="s">{{ __('Billet') }} {{ $r->code }}@if ($r->athlete_id) · <a href="{{ route('athletes.show', $r->athlete_id) }}">{{ __('profil') }}</a>@endif</span></span>
        @if ($r->checked_in_at)<span class="pill ok">✓ {{ __('Présent') }} {{ \Illuminate\Support\Carbon::parse($r->checked_in_at)->format('H:i') }}</span>
        @else<form method="POST" action="{{ route('tickets.checkin', $r->code) }}">@csrf<button class="btn sm">{{ __('Présent') }}</button></form>@endif
      </div>
    @empty
      <p class="meta">{{ __('Pas encore d’inscrit.') }}</p>
    @endforelse
  </div>
  <x-fabara for="detection" />
</div>
@endsection

@push('scripts')
<script>
document.getElementById('q').addEventListener('input', function () {
  var v = this.value.trim().toLowerCase();
  document.querySelectorAll('#regs .li').forEach(function (li) { li.hidden = v && li.dataset.s.indexOf(v) === -1; });
});
</script>
@endpush

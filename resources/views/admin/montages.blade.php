@extends('layouts.admin')
@section('title', 'Montages – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Commandes de montage'])
  <nav class="filters" aria-label="Filtrer">
    @foreach (['a_faire' => 'À faire', 'attente_paiement' => 'Non payées', 'livree' => 'Livrées', 'annulee' => 'Annulées', 'toutes' => 'Toutes'] as $k => $l)
      <a href="{{ route('admin.montages', ['statut' => $k]) }}" aria-current="{{ $status === $k ? 'page' : 'false' }}">{{ $l }}</a>
    @endforeach
  </nav>
  @error('delivered_url')<div class="msg err">{{ $message }}</div>@enderror
  @forelse ($rows as $o)
    @php
      $files = json_decode((string) $o->files, true) ?: [];
    @endphp
    <div class="card" style="margin-top:14px">
      <div class="row" style="justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
        <div><b>{{ $o->package_label }}</b> · {{ number_format($o->amount, 0, ',', ' ') }} {{ $o->currency }} · réf. {{ $o->reference }}
          <div class="meta">{{ $names[$o->user_key] ?? $o->user_key }} · WhatsApp <a href="https://wa.me/{{ ltrim((string) $o->whatsapp, '+') }}" target="_blank" rel="noopener">{{ $o->whatsapp }}</a> · {{ \Illuminate\Support\Carbon::parse($o->created_at)->format('d/m/Y H:i') }}@if ($o->athlete_id) · <a href="{{ route('athletes.show', $o->athlete_id) }}" target="_blank">profil</a>@endif</div></div>
        <span class="pill">{{ $labels[$o->status] ?? $o->status }}</span>
      </div>
      @if ($o->notes)<p style="margin:0"><b>Demande :</b> {{ $o->notes }}</p>@endif
      <div class="row" style="gap:8px;flex-wrap:wrap">
        @foreach ($files as $n => $f)
          <a class="btn ghost sm" href="{{ route('admin.montages.file', [$o->id, $n]) }}">⬇ Vidéo {{ $n + 1 }} ({{ number_format($f['size'] / 1048576, 1, ',', ' ') }} Mo)</a>
        @endforeach
        @if ($o->rush_link)<a class="btn ghost sm" href="{{ $o->rush_link }}" target="_blank" rel="noopener">Lien des vidéos</a>@endif
      </div>
      <form method="POST" action="{{ route('admin.montages.update', $o->id) }}" class="grid-form" style="align-items:end">@csrf
        <label>Statut<select name="status">@foreach (['payee', 'en_cours', 'livree', 'annulee'] as $s)<option value="{{ $s }}" @selected($o->status === $s)>{{ $labels[$s] }}</option>@endforeach</select></label>
        <label>Lien du montage livré<input name="delivered_url" type="url" value="{{ $o->delivered_url }}" placeholder="https://"></label>
        <label>Note pour le sportif<input name="admin_note" value="{{ $o->admin_note }}" maxlength="500"></label>
        <button class="btn sm" type="submit">Enregistrer</button>
      </form>
    </div>
  @empty
    <p class="meta" style="margin-top:14px">Aucune commande.</p>
  @endforelse
</div></div>
@endsection

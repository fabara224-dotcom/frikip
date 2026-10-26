@extends('layouts.admin')
@section('title', 'Suivi '.$r->reference.' – Admin')
@section('content')
@include('admin._nav', ['title' => $name.' · '.$r->package_label, 'lead' => 'Réf. '.$r->reference.' · objectif : '.$r->goal.($r->ends_at ? ' · jusqu’au '.\Illuminate\Support\Carbon::parse($r->ends_at)->format('d/m/Y') : '')])

@php
  $kinds = ['message' => 'Message', 'programme' => 'Programme', 'bilan' => 'Bilan', 'demande' => 'Demande'];
@endphp

<p><a href="{{ route('admin.coaching') }}">← Coaching</a>@if ($member) · <a href="{{ route('admin.member', $member->user_key) }}">Fiche du membre</a>@endif
  @if ($r->whatsapp) · WhatsApp <a href="https://wa.me/{{ ltrim((string) $r->whatsapp, '+') }}" target="_blank" rel="noopener">{{ $r->whatsapp }}</a>@endif</p>

<div class="adm-grid">
  <section class="panel">
    <h2>Coach et état</h2>
    <form method="POST" action="{{ route('admin.coaching.update', $r->id) }}" class="actbox">@csrf
      <label>Coach<select name="coach_id"><option value="">Aucun (l’équipe répond)</option>@foreach ($coaches as $c)<option value="{{ $c->id }}" @selected((int) $r->coach_id === $c->id)>{{ $c->name }}{{ $c->specialty ? ' – '.$c->specialty : '' }}</option>@endforeach</select></label>
      <label>État<select name="status">@foreach ($labels as $k => $l)<option value="{{ $k }}" @selected($r->status === $k)>{{ $l }}</option>@endforeach</select></label>
      <div class="formbar"><button class="btn" type="submit">Enregistrer</button></div>
    </form>
  </section>
  <section class="panel">
    <h2>Programme gratuit du sportif</h2>
    @if ($plan)
      <p class="meta" style="margin-top:-6px">{{ $plan['sport'] }}{{ $plan['position'] ? ' · '.$plan['position'] : '' }}</p>
      @if ($stats)<p class="meta">@foreach ($stats as $k => $v)@continue(str_starts_with($k, '_')){{ $k }} : {{ $v }} · @endforeach</p>@endif
      <ul style="margin:0;padding-left:18px">@foreach ($plan['findings'] as $f)<li>{{ $f }}</li>@endforeach</ul>
      <p class="meta">Priorités : {{ implode(', ', array_map(fn ($p) => $p['title'], $plan['priorities'])) }}</p>
    @else
      <p class="meta">Le sportif n’a pas encore rempli son programme gratuit.</p>
    @endif
  </section>
</div>

<section class="panel">
  <h2>Échanges</h2>
  <div class="cthread admin">
    @forelse ($msgs as $m)
      <div class="cmsg {{ $m->author === 'coach' ? 'mine' : '' }}">
        <small>{{ $m->author === 'coach' ? 'Coach' : 'Sportif' }}@if ($m->author_name) · {{ $m->author_name }}@endif · <b>{{ $kinds[$m->kind] ?? $m->kind }}</b> · {{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d/m H:i') }}</small>
        <p>{!! nl2br(e($m->body)) !!}</p>
      </div>
    @empty
      <p class="meta">Pas encore de message.</p>
    @endforelse
  </div>
  <form method="POST" action="{{ route('admin.coaching.update', $r->id) }}" class="actbox" style="margin-top:12px">@csrf
    <label>Répondre au sportif<textarea name="body" rows="5" maxlength="4000" required placeholder="Programme de la semaine, conseils, bilan…"></textarea></label>
    <div class="formbar">
      <select name="kind" style="width:auto">@foreach (['message', 'programme', 'bilan'] as $k)<option value="{{ $k }}">{{ $kinds[$k] }}</option>@endforeach</select>
      <button class="btn" type="submit">Envoyer</button>
    </div>
  </form>
</section>
@endsection

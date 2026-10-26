@extends('layouts.admin')
@section('title', 'Coaching – Admin')
@section('content')
@include('admin._nav', ['title' => 'Coaching', 'lead' => 'Les suivis payés par les sportifs. Choisis un coach pour chaque demande, ou réponds au nom de l’équipe.'])

@php
  $tabs = ['a_traiter' => 'À traiter', 'attente_paiement' => 'Non payés', 'terminee' => 'Terminés', 'toutes' => 'Tous', 'coachs' => 'Coachs'];
  $coachNames = $coaches->pluck('name', 'id');
  $c = $edit;
@endphp

<div class="kpis">
  <div class="kpi"><span>Programmes gratuits</span><b>{{ number_format($stats['plans'], 0, ',', ' ') }}</b><small>calculés pour des membres</small></div>
  <div class="kpi"><span>Suivis payés</span><b>{{ $stats['paid'] }}</b><small>depuis le début</small></div>
  <div class="kpi"><span>Coachs actifs</span><b>{{ $coaches->where('active', true)->count() }}</b><small>{{ $coaches->whereNotNull('user_key')->count() }} avec un compte Frikip</small></div>
  <div class="kpi accent"><span>Encaissé</span><b>{{ number_format($revenue, 0, ',', ' ') }}</b><small>GNF de coaching</small></div>
</div>

<nav class="tabs" aria-label="Filtrer">
  @foreach ($tabs as $k => $l)<a href="{{ route('admin.coaching', ['statut' => $k]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach
</nav>

@if ($status === 'coachs')
  <div class="adm-grid">
    <section class="panel">
      <h2>{{ $c ? 'Modifier '.$c->name : 'Ajouter un coach' }}</h2>
      <form method="POST" action="{{ route('admin.coaching.coach') }}" class="adform">@csrf
        @if ($c)<input type="hidden" name="id" value="{{ $c->id }}">@endif
        <div class="formgrid">
          <label class="wide">Nom<input name="name" value="{{ old('name', $c->name ?? '') }}" required maxlength="120"></label>
          <label>Sport<select name="sport"><option value="">Tous</option>@foreach ($sports as $s)<option @selected(old('sport', $c->sport ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
          <label>Pays<input name="country" value="{{ old('country', $c->country ?? '') }}" maxlength="60" placeholder="Guinée"></label>
          <label class="wide">Spécialité<input name="specialty" value="{{ old('specialty', $c->specialty ?? '') }}" maxlength="160" placeholder="ex. Finition et attaquants, préparation physique"></label>
          <label class="wide">Présentation<textarea name="bio" rows="3" maxlength="1000">{{ old('bio', $c->bio ?? '') }}</textarea></label>
          <label class="wide">E-mail ou téléphone de son compte Frikip<input name="contact" value="{{ old('contact', $c->contact ?? '') }}" maxlength="120"><small>Avec un compte rattaché, le coach répond lui-même depuis « Espace coach ». Sinon, tu réponds pour lui ici.</small></label>
          <label class="check wide"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $c->active ?? true))> Coach actif</label>
        </div>
        <div class="formbar"><button class="btn" type="submit">{{ $c ? 'Enregistrer' : 'Ajouter le coach' }}</button>@if ($c)<a class="btn ghost" href="{{ route('admin.coaching', ['statut' => 'coachs']) }}">Annuler</a>@endif</div>
      </form>
    </section>
    <section class="panel">
      <h2>Coachs</h2>
      @forelse ($coaches as $co)
        <div class="line {{ $co->active ? '' : 'muted' }}"><span><b>{{ $co->name }}</b><small>{{ $co->specialty ?: 'Sans spécialité' }}{{ $co->sport ? ' · '.$co->sport : '' }} · {{ $co->user_key ? 'compte rattaché' : 'sans compte' }}</small></span>
          <a class="btn ghost sm" href="{{ route('admin.coaching', ['statut' => 'coachs', 'coach' => $co->id]) }}">Modifier</a></div>
      @empty
        <p class="meta">Aucun coach. Ajoute le premier : tu peux aussi répondre toi-même aux sportifs.</p>
      @endforelse
    </section>
  </div>
@else
  @if ($rows->isEmpty())
    <div class="empty"><b>Rien ici.</b><span>Les demandes de coaching payées apparaîtront ici.</span></div>
  @else
    <div class="admin-tbl"><table>
      <thead><tr><th>Sportif</th><th>Formule</th><th>Objectif</th><th>Coach</th><th>État</th><th></th></tr></thead>
      <tbody>
      @foreach ($rows as $r)
        <tr>
          <td><b class="mname">{{ $names[$r->user_key] ?? 'Membre' }}</b><span class="meta">réf. {{ $r->reference }} · {{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y') }}</span></td>
          <td>{{ $r->package_label }}<span class="meta">{{ number_format($r->amount, 0, ',', ' ') }} {{ $r->currency }}</span></td>
          <td>{{ $r->goal }}</td>
          <td>{{ $r->coach_id ? ($coachNames[$r->coach_id] ?? '–') : '–' }}</td>
          <td><span class="pill {{ $r->status === 'payee' ? 'warn' : '' }}">{{ $labels[$r->status] ?? $r->status }}</span>@if ($unread[$r->id] ?? 0)<span class="pill danger">{{ $unread[$r->id] }} nouveau(x)</span>@endif</td>
          <td><a class="btn sm {{ $r->status === 'payee' ? '' : 'ghost' }}" href="{{ route('admin.coaching.request', $r->id) }}">Ouvrir</a></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
@endif
@endsection

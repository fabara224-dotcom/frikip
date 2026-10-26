@extends('layouts.web')

@section('title', 'Offres – '.config('terrain.app_name'))

@php($isRecruiter = $me['role'] === 'recruiter')

@section('content')
<div class="page gut">
  @if ($isRecruiter)
    <h1>Mes offres</h1>
    <div class="cols">
      <div>
        @php($mine = array_filter($offers, fn ($o) => $o['mine']))
        @forelse ($mine as $o)
          <div class="card" style="margin-bottom:12px">
            <h3 style="margin:0;font-size:19px">{{ $o['title'] }}</h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap"><span class="pill">{{ $o['sport'] }}</span><span class="pill">{{ $o['place'] }}</span>@if ($o['deadline'])<span class="pill">Avant le {{ $o['deadline'] }}</span>@endif</div>
            @if (! empty($recommended[$o['id']]))
              <div class="section-label" style="margin:6px 0 0">✨ Joueurs recommandés pour cette offre</div>
              <div class="list">
                @foreach ($recommended[$o['id']] as $rec)
                  <div class="li">
                    <x-av :name="$rec['athlete']['name']" :sport="$rec['athlete']['sport']" />
                    <a class="grow" href="{{ route('athletes.show', $rec['athlete']['id']) }}" style="color:inherit;text-decoration:none"><span class="t">{{ $rec['athlete']['name'] }} @if (! empty($rec['athlete']['star']))<x-star />@endif</span>
                      <span class="s">{{ \Illuminate\Support\Arr::first($rec['athlete']['fields']) }} · {{ $rec['athlete']['age'] }} ans · {{ $rec['athlete']['city'] }}@if (count($rec['why'])) · {{ implode(', ', $rec['why']) }}@endif</span></a>
                    <a class="btn ghost sm" href="{{ route('messages.show', $rec['athlete']['contact_id']) }}">Contacter</a>
                  </div>
                @endforeach
              </div>
            @endif
            <div class="section-label" style="margin:6px 0 0">Candidats ({{ count($o['candidates']) }})</div>
            <div class="list">
              @forelse ($o['candidates'] as $id)
                @if ($c = $people[$id] ?? null)
                  <div class="li">
                    <x-av :name="$c['name']" :sport="$c['sport']" />
                    <a class="grow" href="{{ route('athletes.show', $c['id']) }}" style="color:inherit;text-decoration:none"><span class="t">{{ $c['name'] }} @if (! empty($c['star']))<x-star />@endif</span><span class="s">{{ \Illuminate\Support\Arr::first($c['fields']) }} · {{ $c['age'] }} ans</span></a>
                    <a class="btn ghost sm" href="{{ route('messages.show', $c['contact_id']) }}">Contacter</a>
                  </div>
                @endif
              @empty
                <p class="meta">Pas encore de candidat.</p>
              @endforelse
            </div>
          </div>
        @empty
          <p class="meta">Aucune offre publiée pour le moment.</p>
        @endforelse
      </div>
      <aside>
        <form method="POST" action="{{ route('offers.store') }}" class="card">@csrf
          <h3 style="margin:0">Publier une offre</h3>
          <label for="title">Titre<input id="title" name="title" value="{{ old('title') }}" placeholder="Ex. : Détection U17 – milieux" required></label>
          @error('title')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
          <label for="sport">Sport<select id="sport" name="sport">@foreach ($sports as $s)<option @selected(old('sport') === $s)>{{ $s }}</option>@endforeach</select></label>
          <label for="place">Lieu<input id="place" name="place" value="{{ old('place', 'Conakry') }}"></label>
          <label for="deadline">Date limite<input id="deadline" name="deadline" value="{{ old('deadline') }}" placeholder="Ex. : 15 déc. 2026"></label>
          <label for="criteria">Critères<input id="criteria" name="criteria" value="{{ old('criteria') }}" placeholder="Ex. : nés en 2009 ou 2010"></label>
          <button class="btn" type="submit">Publier l'offre</button>
          <p class="meta" style="margin:0">Il est interdit de demander de l'argent aux candidats.</p>
        </form>
      </aside>
    </div>
  @else
    <h1>Offres ouvertes</h1>
    @error('apply')<p class="field-error">{{ $message }}</p>@enderror
    <div class="mgrid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
      @foreach (array_filter($offers, fn ($o) => ! $o['mine']) as $o)
        <div class="card">
          <div style="display:flex;gap:10px;align-items:center">
            <x-av :name="$o['organization']" :sport="$o['sport']" />
            <div style="min-width:0"><b>{{ $o['organization'] }}</b> @if ($o['verified'])<x-ver />@endif<div class="meta">{{ $o['org_type'] }} · {{ $o['place'] }}</div></div>
          </div>
          <h3 style="margin:0;font-size:19px">{{ $o['title'] }}</h3>
          @if (! empty($o['match']) && $o['match']['score'] >= 70)<span class="pill ok" style="align-self:flex-start">✨ Recommandée pour toi{{ count($o['match']['why']) ? ' : '.implode(', ', $o['match']['why']) : '' }}</span>@endif
          <div style="display:flex;gap:6px;flex-wrap:wrap"><span class="pill">{{ $o['sport'] }}</span>@if ($o['deadline'])<span class="pill">Avant le {{ $o['deadline'] }}</span>@endif<span class="pill">{{ count($o['candidates']) }} candidat{{ count($o['candidates']) > 1 ? 's' : '' }}</span></div>
          <p class="meta" style="margin:0">Critères : {{ $o['criteria'] }}</p>
          @if ($o['applied'])
            <span class="pill ok" style="align-self:flex-start">Candidature envoyée · en cours d'examen</span>
          @elseif (! $myAthlete || ! count($myAthlete['videos']))
            <p class="meta" style="margin:0"><a href="{{ route('publish') }}">Publie d'abord une compilation</a> pour postuler.</p>
          @else
            <details>
              <summary class="btn" style="display:inline-flex">Postuler</summary>
              <form method="POST" action="{{ route('offers.apply', $o['id']) }}" style="display:flex;flex-direction:column;gap:10px;margin-top:10px">@csrf
                <label for="v-{{ $o['id'] }}">Ta meilleure vidéo<select id="v-{{ $o['id'] }}" name="video_id">
                  @foreach ($myAthlete['videos'] as $v)<option value="{{ $v['id'] }}">{{ $v['title'] }} ({{ $v['duration'] }})</option>@endforeach
                </select></label>
                <label for="m-{{ $o['id'] }}">Message au recruteur<textarea id="m-{{ $o['id'] }}" name="message" rows="3">Bonjour, je suis très motivé par cette détection. Ma compilation montre mes qualités.</textarea></label>
                <button class="btn" type="submit">Envoyer ma candidature</button>
              </form>
            </details>
          @endif
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection

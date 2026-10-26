@extends('layouts.web')

@section('title', $o->name.' – '.config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="{{ $o->name }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit((string) $o->description, 150) }}">
@endpush

@section('content')
<div class="page gut">
  <div class="orghead" style="--c:{{ $o->color }}">
    <span class="orglogo lg" style="background:{{ $o->color }}">{{ \App\Terrain\SportFields::initials($o->name) }}</span>
    <div>
      <h1 style="margin:0">{{ $o->name }} @if ($o->verified)<x-ver />@endif</h1>
      <div class="meta">{{ __($types[$o->type] ?? $o->type) }} · {{ $o->sport }} · {{ $o->city }}, {{ $o->country }}</div>
      @if ($pro)<span class="pill pro">{{ __('Structure Pro') }}</span>@endif
      @if ($o->verified)<span class="pill ok">{{ __('Structure vérifiée') }}</span>@else<span class="pill">{{ __('Vérification en attente') }}</span>@endif
      @if ($o->demo)<span class="pill">{{ __('Exemple de démonstration') }}</span>@endif
    </div>
    @if ($mine)<a class="btn ghost sm" href="{{ route('clubs.edit') }}">{{ __('Modifier') }}</a>@endif
  </div>

  @if ($mine)
    <div class="ownerbar">
      <span><b>{{ number_format($o->views ?? 0, 0, ',', ' ') }}</b> {{ __('visites de ta page') }}</span>
      @if ($pro)
        <span class="meta">{{ __('Structure Pro active : ta page est en tête de la liste des clubs.') }}</span>
      @else
        <a class="btn sm" href="{{ route('structure.offer') }}">{{ __('Passer à Structure Pro') }}</a>
      @endif
    </div>
  @endif
  <div class="cols">
    <div>
      @if ($o->description)<p>{{ $o->description }}</p>@endif
      @if ($o->palmares)<p>🏆 <b>{{ $o->palmares }}</b></p>@endif

      <div class="section-label">{{ __('Détections à venir') }}</div>
      <div class="list">
        @forelse ($events as $e)
          <a class="li" href="{{ route('events.show', $e->id) }}"><span class="datebox"><b>{{ \Illuminate\Support\Carbon::parse($e->date)->format('d') }}</b>{{ \Illuminate\Support\Carbon::parse($e->date)->translatedFormat('M') }}</span>
            <span class="grow"><span class="t">{{ $e->title }}</span><span class="s">{{ $e->city }} · {{ $e->time }}</span></span>›</a>
        @empty
          <p class="meta">{{ __('Aucune détection programmée.') }}</p>
        @endforelse
      </div>

      <div class="section-label">{{ __('Offres') }}</div>
      @forelse ($offers as $of)
        <div class="card" style="margin-bottom:8px"><b>{{ $of['title'] }}</b><span class="meta">{{ $of['sport'] }} · {{ $of['place'] }} · {{ __('avant le') }} {{ $of['deadline'] }}</span></div>
      @empty
        <p class="meta">{{ __('Aucune offre ouverte.') }}</p>
      @endforelse
    </div>
    <aside>
      <div class="section-label">{{ __('Joueurs liés au club') }}</div>
      <div class="list">
        @forelse (array_slice($players, 0, 12) as $a)
          <a class="li" href="{{ route('athletes.show', $a['id']) }}"><x-av :name="$a['name']" :sport="$a['sport']" />
            <span class="grow"><span class="t">{{ $a['name'] }}</span><span class="s">{{ \Illuminate\Support\Arr::first($a['fields']) }} · {{ $a['age'] }} {{ __('ans') }}</span></span></a>
        @empty
          <p class="meta">{{ __('Les joueurs qui indiquent ce club sur leur profil apparaissent ici.') }}</p>
        @endforelse
      </div>
    </aside>
  </div>
</div>
@endsection

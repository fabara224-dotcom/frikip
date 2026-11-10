@extends('layouts.web')

@section('title', __('Historique de mes contacts').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow">
    <h1>{{ __('Historique de mes contacts') }}</h1>
    <p class="pub-lead">{{ __('Les joueurs et les structures à qui tu as écrit, quand, et s’ils ont répondu.') }}</p>
    @forelse ($rows as $r)
      <a class="listrow" href="{{ route('messages.show', $r['id']) }}">
        <span><b>{{ $r['name'] }}</b><small>{{ __('Premier message le :d', ['d' => \Illuminate\Support\Carbon::parse($r['first'])->format('d/m/Y')]) }} · {{ trans_choice(':n message envoyé|:n messages envoyés', $r['n'], ['n' => $r['n']]) }}</small></span>
        @if ($r['reply'])<span class="pill ok">{{ __('A répondu') }}</span>@else<span class="pill">{{ __('Pas encore de réponse') }}</span>@endif
      </a>
    @empty
      <p class="meta">{{ __('Tu n’as encore écrit à personne.') }} <a href="{{ route('search') }}">{{ __('Chercher des joueurs') }}</a></p>
    @endforelse
    @if (count($events))
      <h2 style="margin-top:24px">{{ __('Mes détections et essais') }}</h2>
      @foreach ($events as $e)
        <a class="listrow" href="{{ route('events.manage', $e->id) }}"><span><b>{{ $e->title }}</b><small>{{ \Illuminate\Support\Carbon::parse($e->date)->format('d/m/Y') }} · {{ $e->city }} · {{ trans_choice(':n inscrit|:n inscrits', \Illuminate\Support\Facades\DB::table('event_registrations')->where('event_id', $e->id)->count(), ['n' => \Illuminate\Support\Facades\DB::table('event_registrations')->where('event_id', $e->id)->count()]) }}</small></span><span class="btn sm ghost">{{ __('Inscrits') }}</span></a>
      @endforeach
    @endif
  </div>
</div>
@endsection

@extends('layouts.web')

@section('title', __($c->title).' – '.__('Défis').' – '.config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="🏆 {{ __($c->title) }} – {{ config('terrain.app_name') }}">
<meta property="og:description" content="{{ $c->description ? __($c->description) : '' }} {{ __('Vote pour ton joueur préféré !') }}">
@endpush

@php
  $end = \Illuminate\Support\Carbon::parse($c->ends_on)->endOfDay();
  $total = max(1, array_sum(array_map(fn ($e) => $e['entry']->votes, $entries)));
  $open = $c->status === 'ouvert';
  $shareUrl = route('challenges.show', $c->id);
@endphp

@section('content')
<div class="page gut">
  <p class="meta" style="margin:0"><a href="{{ route('challenges') }}">← {{ __('Tous les défis') }}</a></p>

  <section class="defihero slim">
    <div class="dh-main">
      <span class="dh-kind">{{ ($c->kind ?? 'semaine') === 'special' ? __('Défi spécial') : __('Défi de la semaine') }}@if ($c->sport) · @td($c->sport)@endif @if ($c->country) · {{ \App\Terrain\DataLang::t($c->country) }}@endif</span>
      <h1>{{ __($c->title) }}</h1>
      @php
        $desc = $c->description ?: (\App\Terrain\Growth\Challenges::THEMES[$c->theme][2] ?? '');
      @endphp
      @if ($desc)<p>{{ __($desc) }}</p>@endif
      @if ($c->prize || $boost)
        <p class="dh-prize"><x-ico name="trophy" /> {{ __('À gagner') }} : <b>{{ $c->prize ?: '' }}{{ $c->prize && $boost ? ' + ' : '' }}{{ $boost ? __(':n jours de Boost', ['n' => $boost]) : '' }}</b>
          @if ($c->sponsor) · @if ($c->sponsor_url)<a href="{{ $c->sponsor_url }}" target="_blank" rel="noopener sponsored">{{ __('offert par :s', ['s' => $c->sponsor]) }}</a>@else{{ __('offert par :s', ['s' => $c->sponsor]) }}@endif @endif</p>
      @endif
      @if ($open)
        <div class="countdown" data-end="{{ $end->toIso8601String() }}"><span><b>–</b>{{ __('jours') }}</span><span><b>–</b>{{ __('heures') }}</span><span><b>–</b>{{ __('min') }}</span></div>
      @else
        <p class="dh-stats">{{ __('Défi terminé le :d', ['d' => $end->translatedFormat('j F Y')]) }}</p>
      @endif
    </div>
  </section>

  @if ($winner)
    <a class="winnerbar" href="{{ route('athletes.show', $winner['athlete']['id']) }}"><span class="hof-av"><x-av :name="$winner['athlete']['name']" :sport="$winner['athlete']['sport']" lg /><i>🏆</i></span>
      <span><small>{{ __('Gagnant') }}</small><b>{{ $winner['athlete']['name'] }}</b>{{ trans_choice(':n vote|:n votes', $winner['entry']->votes, ['n' => $winner['entry']->votes]) }}</span></a>
  @endif

  @if ($open)
    @if ($canEnter && count($athlete['videos']))
      <form method="POST" action="{{ route('challenges.enter') }}" class="card enterbox">@csrf
        <input type="hidden" name="challenge_id" value="{{ $c->id }}">
        <b>{{ $myEntry ? __('Ta participation') : __('Participer avec une de mes vidéos') }}</b>
        @if ($myEntry)<p class="meta" style="margin:0">{{ __('Tu es :r sur :t avec :v vote(s).', ['r' => '#'.(array_search($myEntry, $entries, true) + 1), 't' => count($entries), 'v' => $myEntry['entry']->votes]) }}</p>@endif
        <div class="row" style="gap:8px;flex-wrap:wrap">
          <select name="video_id" aria-label="{{ __('Ma vidéo') }}" style="flex:1;min-width:200px">@foreach ($athlete['videos'] as $v)<option value="{{ $v['id'] }}" @selected($myEntry && $myEntry['video']['id'] === $v['id'])>{{ $v['title'] }}</option>@endforeach</select>
          <button class="btn" type="submit">{{ $myEntry ? __('Changer de vidéo') : __('Participer') }}</button>
        </div>
        @if ($myEntry)<a class="btn ghost sm wa" href="https://wa.me/?text={{ rawurlencode(__('Vote pour moi au défi « :t » sur :app : :u', ['t' => __($c->title), 'app' => config('terrain.app_name'), 'u' => $shareUrl.'#e'.$myEntry['entry']->id])) }}" target="_blank" rel="noopener">{{ __('Demander des votes sur WhatsApp') }}</a>@endif
      </form>
    @elseif ($athlete && ! count($athlete['videos']))
      <p class="note"><a href="{{ route('publish') }}">{{ __('Publie une compilation') }}</a> {{ __('pour participer.') }}</p>
    @elseif ($athlete && ! $canEnter)
      <p class="note">{{ __('Ce défi n’est pas ouvert à ton sport ou à ton pays.') }} <a href="{{ route('challenges') }}">{{ __('Voir les autres défis') }}</a></p>
    @elseif (! $me)
      <p class="note"><a href="{{ route('phone.login') }}">{{ __('Crée ton compte') }}</a> {{ __('pour voter ou participer.') }}</p>
    @endif
  @endif

  <h2>{{ __('Classement') }} <span class="meta">· {{ trans_choice(':n participant|:n participants', count($entries), ['n' => count($entries)]) }}</span></h2>
  <ol class="leader">
    @forelse ($entries as $i => $en)
      @php
        $voted = $myVote === $en['entry']->id;
        $pct = round($en['entry']->votes / $total * 100);
        $mineE = $myEntry && $myEntry['entry']->id === $en['entry']->id;
      @endphp
      <li class="lrow {{ $i < 3 ? 'top' : '' }} {{ $mineE ? 'me' : '' }}" id="e{{ $en['entry']->id }}">
        <span class="lpos">{{ $i + 1 }}</span>
        @php
          $col = \App\Terrain\SportFields::color($en['athlete']['sport']);
        @endphp
        <a class="lthumb" href="{{ route('feed', ['v' => $en['video']['id']]) }}" aria-label="{{ __('Regarder la vidéo de :n', ['n' => $en['athlete']['name']]) }}"><span class="lposter" style="background:@if (! empty($en['video']['poster']))url('{{ $en['video']['poster'] }}') center/cover,@endif linear-gradient(160deg,{{ $col[0] }},{{ $col[1] }})"><i aria-hidden="true">▶</i></span></a>
        <span class="lbody">
          <a class="cname" href="{{ route('athletes.show', $en['athlete']['id']) }}">{{ $en['athlete']['name'] }} @if (! empty($en['athlete']['star']))<x-star />@endif</a>
          <small>{{ $en['video']['title'] }} · {{ $en['athlete']['city'] }}</small>
          <span class="lbar"><i style="width:{{ $pct }}%"></i></span>
          <small><b class="vcount" data-entry="{{ $en['entry']->id }}">{{ $en['entry']->votes }}</b> {{ __('votes') }} · {{ $pct }} %</small>
        </span>
        <span class="lact">
          @if ($open && $me && ! $mineE)
            <button class="btn sm {{ $voted ? '' : 'ghost' }}" type="button" data-vote="{{ $en['entry']->id }}" @if ($myVote) disabled @endif>{{ $voted ? __('Ton vote ✓') : __('Voter') }}</button>
          @endif
          <a class="iconlink" href="https://wa.me/?text={{ rawurlencode(__('Vote pour').' '.$en['athlete']['name'].' – '.__($c->title).' : '.$shareUrl.'#e'.$en['entry']->id) }}" target="_blank" rel="noopener" aria-label="{{ __('Partager sur WhatsApp') }}">WhatsApp</a>
        </span>
      </li>
    @empty
      <li class="meta">{{ __('Sois le premier à participer !') }}</li>
    @endforelse
  </ol>

  <details class="more-opts"><summary><b>{{ __('Règles du défi') }}</b></summary>
    <ul class="plan-list" style="padding:0 16px 14px 34px">
      <li>{{ __('Une vidéo par sportif ; tu peux la changer jusqu’à la fin.') }}</li>
      <li>{{ __('Un vote par compte et par défi. On ne peut pas voter pour soi-même.') }}</li>
      <li>{{ __('Le gagnant est celui qui a le plus de votes à la fin. En cas d’égalité, la première vidéo envoyée gagne.') }}</li>
      <li>{{ __('Les votes achetés, les faux comptes et les vidéos qui ne sont pas les tiennes font retirer la participation.') }}</li>
    </ul>
  </details>

  @if ($others)
    <h2>{{ __('Autres défis en cours') }}</h2>
    @foreach ($others as $o)
      <a class="listrow" href="{{ route('challenges.show', $o->id) }}"><span><b>{{ __($o->title) }}</b><small>@if ($o->prize)🏆 {{ $o->prize }} · @endif{{ __('Fin :d', ['d' => \Illuminate\Support\Carbon::parse($o->ends_on)->endOfDay()->diffForHumans()]) }}</small></span><span class="pill">{{ __('Voir') }}</span></a>
    @endforeach
  @endif
</div>
@endsection

@push('scripts')
@include('web.partials.countdown-js')
<script>
document.querySelectorAll('[data-vote]').forEach(function (b) {
  b.addEventListener('click', function () {
    Terrain.post(@json(route('challenges.vote', '__ID__')).replace('__ID__', b.dataset.vote)).then(function (d) {
      Terrain.toast(d.message);
      if (d.ok) {
        var c = document.querySelector('.vcount[data-entry="' + b.dataset.vote + '"]'); c.textContent = parseInt(c.textContent, 10) + 1;
        document.querySelectorAll('[data-vote]').forEach(function (x) { x.disabled = true; });
        b.classList.remove('ghost'); b.textContent = @json(__('Ton vote ✓'));
      }
    }).catch(function () {});
  });
});
</script>
@endpush

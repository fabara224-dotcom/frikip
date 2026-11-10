@extends('layouts.web')

@section('title', __('Mon espace fan').' – '.config('terrain.app_name'))

@section('content')
{{-- Espace d'un compte « Fan » : ses sportifs suivis, les meilleurs du moment, le défi en cours. --}}
<div class="page gut">
  <div class="narrow" style="max-width:720px">
    <h1>{{ __('Salut :n', ['n' => $me['name']]) }}</h1>
    <p class="pub-lead">{{ __('Suis tes sportifs préférés, aime et partage leurs vidéos, et vote pour eux dans les défis : tu les aides à se faire repérer.') }}</p>

    @if ($challenge)
      <a class="partnerbar" href="{{ route('challenges.show', $challenge->id) }}">
        <span><b>🏆 @td($challenge->title)</b><small>{{ __('Vote pour ton sportif préféré dans le défi en cours.') }}</small></span>
        <span class="btn">{{ __('Voter') }}</span>
      </a>
    @endif

    <h2>{{ __('Les sportifs que je suis') }}</h2>
    @forelse ($followed as $a)
      <a class="listrow" href="{{ route('athletes.show', $a['id']) }}">
        <span style="display:flex;gap:12px;align-items:center"><x-av :name="$a['name']" :sport="$a['sport']" />
          <span><b>{{ $a['name'] }}</b><small>@td($a['sport']) · {{ $a['city'] }}</small></span></span>
        <span class="btn sm ghost">{{ __('Voir') }}</span>
      </a>
    @empty
      <p class="meta">{{ __('Tu ne suis encore personne. Dans le fil, touche le + sur la photo d’un sportif pour le suivre.') }}</p>
    @endforelse

    <h2 style="margin-top:26px">{{ __('Les meilleurs du moment') }}</h2>
    <ol class="rank">
      @forelse ($top as $i => $a)
        <li class="{{ $i < 3 ? 'podium p'.($i + 1) : '' }}">
          <span class="pos">{{ $i + 1 }}</span>
          <x-av :name="$a['name']" :sport="$a['sport']" />
          <a class="grow" href="{{ route('athletes.show', $a['id']) }}"><span class="t">{{ $a['name'] }}</span><span class="s">@td($a['sport']) · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}</span></a>
          <b class="rating">{{ round($a['rating']) }}</b>
        </li>
      @empty
        <p class="meta">{{ __('Le classement se remplit dès que des sportifs publient leurs vidéos.') }}</p>
      @endforelse
    </ol>
    <p><a class="btn ghost" href="{{ route('rankings') }}">{{ __('Voir tout le classement') }}</a></p>

    <div class="card" style="margin-top:22px">
      <b>{{ __('Tu veux mettre en avant un proche ?') }}</b>
      <p class="meta" style="margin:4px 0 10px">{{ __('Ton frère, ton fils, un ami joue bien ? Crée son profil sportif et publie ses vidéos pour lui avec une autre adresse e-mail ou un autre numéro : tu seras connecté à son profil.') }}</p>
      <a class="btn sm" href="{{ route('register', ['role' => 'proche']) }}">{{ __('Créer le profil d’un proche') }}</a>
    </div>
  </div>
</div>
@endsection

@extends('layouts.web')

@section('title', $a['name'].' – '.config('terrain.app_name'))
@section('description', $a['name'].', '.\App\Terrain\DataLang::t($a['sport']).', '.$a['age'].' '.__('ans').', '.$a['city'].'. '.__('Compilations vidéo et statistiques.'))

@php
    $mine = ($me['athlete_id'] ?? null) === $a['id'];
    $isRecruiter = ($me['role'] ?? null) === 'recruiter';
    $first = \Illuminate\Support\Arr::first($a['fields']) ?? $a['sport'];
    $starMin = \App\Terrain\Growth\Engagement::starThreshold();
    $fields = \App\Terrain\DataLang::fields($a['fields']);
@endphp

@push('meta')
<meta property="og:title" content="{{ $a['name'] }} – {{ $a['sport'] }}, {{ $a['age'] }} ans">
<meta property="og:description" content="{{ \Illuminate\Support\Arr::first($a['fields']) ?? $a['sport'] }} · {{ $a['city'] }}, {{ $a['country'] }} · {{ count($a['videos']) }} compilation(s) sur {{ config('terrain.app_name') }}">
<meta property="og:image" content="{{ route('athletes.card.png', $a['id']) }}">
<meta property="og:url" content="{{ $shareUrl }}">
<meta property="og:type" content="profile">
<meta name="twitter:card" content="summary_large_image">
@endpush

@section('content')
<div class="page gut">
  <div class="cols">
    <div>
      <div style="display:flex;gap:16px;align-items:center">
        <x-av :name="$a['name']" :sport="$a['sport']" lg />
        <div style="min-width:0">
          <h1 style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">{{ $a['name'] }} @if (! empty($a['star']))<x-star />@endif</h1>
          <div class="meta">@td($a['sport']) · @td($first) · {{ $a['club'] }}</div>
          @if (! empty($a['specialty']))<div class="spec-tag dark">★ {{ __('Point fort') }} : @td($a['specialty'])</div>@endif
        </div>
      </div>
      @if (count($badges))
        <div class="badges">@foreach ($badges as [$ico, $label, $why])<span class="bdg" title="{{ $why }}"><i>{{ $ico }}</i>{{ __($label) }}</span>@endforeach</div>
      @endif
      <div class="sharebar">
        <a class="btn sm wa" href="https://wa.me/?text={{ rawurlencode($a['name'].' – '.$a['sport'].' ('.$a['city'].') : '.$shareUrl) }}" target="_blank" rel="noopener">{{ __('Partager sur WhatsApp') }}</a>
        <a class="btn ghost sm" href="{{ route('athletes.card', $a['id']) }}">{{ __('Carte de joueur') }}</a>
        <a class="btn ghost sm" href="{{ route('athletes.cv', $a['id']) }}">{{ __('CV sportif (PDF)') }}</a>
        <button class="btn ghost sm" type="button" data-copy="{{ $shareUrl }}">{{ __('Copier le lien') }}</button>
        <span class="meta" id="followers-count">{{ number_format($a['followers'] ?? $followers, 0, ',', ' ') }} {{ ($a['followers'] ?? $followers) > 1 ? __('abonnés') : __('abonné') }}</span>
      </div>
      @if (! empty($a['star']))
        <div class="starprog"><x-star /> {{ __('Étoile bleue : plus de :n abonnés', ['n' => number_format($starMin, 0, ',', ' ')]) }}</div>
      @elseif ($mine)
        @php($pct = min(100, round(($a['followers'] ?? 0) / $starMin * 100)))
        <div class="starprog"><span>{{ __('Étoile bleue') }}</span><span class="bar"><i style="width:{{ $pct }}%"></i></span><span>{{ $a['followers'] ?? 0 }} / {{ number_format($starMin, 0, ',', ' ') }} {{ __('abonnés') }}</span></div>
      @endif
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px">
        @if ($a['verified'])<span class="pill ok"><x-ver /> {{ __('Identité vérifiée') }}</span>@else<span class="pill">{{ __('Vérification en attente') }}</span>@endif
        @if ($a['availability'])<span class="pill">{{ __('Disponible') }} : @td($a['availability'])</span>@endif
        @if ($a['age'] < 18)<span class="pill warn">{{ __('Moins de 18 ans') }}</span>@endif
        @if (! empty($a['demo']))<span class="pill">{{ __('Profil fictif (exemple)') }}</span>@endif
      </div>
      @if (! empty($a['demo']))
        <p class="note">Ce profil est un exemple inventé pour la démonstration : la personne et ses statistiques n'existent pas.
          @if (! empty($a['videos'][0]['credit']))Les vidéos sont des images d'illustration libres de droits ({{ $a['videos'][0]['source_name'] ?? 'Pexels' }}, auteurs crédités sur chaque vidéo) : les personnes filmées ne sont pas ce sportif.@else Les vidéos sont des illustrations dessinées.@endif</p>
      @endif

      @if ($a['age'] < 18)
        <p class="note">Compte encadré par un tuteur. Les messages des recruteurs sont transmis en copie au parent ou tuteur. Seuls les recruteurs vérifiés peuvent écrire, et un essai ne doit jamais être payant.</p>
      @endif

      <div class="section-label">{{ __('Profil commun') }}</div>
      <div class="kv">
        @foreach ([__('Âge') => $a['age'].' '.__('ans'), __('Taille') => $a['height'], __('Poids') => $a['weight'], __('Pays') => $a['country'], __('Ville') => $a['city'], __('Niveau') => $a['level']] as $k => $v)
          <div><span>{{ $k }}</span><b>@td($v)</b></div>
        @endforeach
      </div>

      <div class="section-label">{{ __('Fiche') }} {{ mb_strtolower(\App\Terrain\DataLang::t($a['sport'])) }}</div>
      <div class="spec">
        @foreach ($fields as $k => $v)
          <div class="r"><span>{{ $k }}</span><b>{{ $v }}</b></div>
        @endforeach
      </div>

      <div class="section-label">{{ __('Compilations') }} ({{ count($a['videos']) }})</div>
      @if (count($a['videos']))
        <div class="thumbs">
          @foreach ($a['videos'] as $v)
            @include('web.partials.thumb', ['a' => $a, 'v' => $v])
          @endforeach
        </div>
      @else
        <p class="meta">{{ __('Pas encore de vidéo.') }}@if ($mine) <a href="{{ route('publish') }}">{{ __('Publier ma première compilation') }}</a>@endif</p>
      @endif
    </div>

    <aside>
      <div class="card">
        @if ($mine)
          <p style="margin:0">{{ __('C’est ton profil public, tel que les recruteurs le voient.') }}</p>
          <a class="btn" href="{{ route('publish') }}">{{ __('Ajouter une vidéo') }}</a>
          <a class="btn ghost" href="{{ route('stats') }}">{{ __('Mes statistiques') }}</a>
          <a class="btn ghost" href="{{ route('referral') }}">{{ __('Inviter mes coéquipiers') }}</a>
        @elseif ($isRecruiter && ! empty($a['demo']) && ($realAccounts ?? false))
          <p class="meta" style="margin:0">{{ __('Profil d’exemple : il ne reçoit pas de messages. Les vrais sportifs inscrits peuvent être contactés.') }}</p>
        @elseif ($isRecruiter)
          <a class="btn" href="{{ route('messages.show', $a['contact_id']) }}">{{ __('Contacter') }}</a>
          <form method="POST" action="{{ route('athletes.follow', $a['id']) }}" data-ajax-follow>@csrf
            <button class="btn ghost" style="width:100%" type="submit">{{ $following ? __('Favori ✓') : __('Ajouter aux favoris') }}</button></form>
          <p class="meta" style="margin:0">{{ ! empty($me['premium']) ? 'Premium : contacts illimités.' : 'Compte gratuit : '.config('terrain.free_contacts').' nouveaux contacts.' }}</p>
        @elseif ($me)
          <form method="POST" action="{{ route('athletes.follow', $a['id']) }}" data-ajax-follow>@csrf
            <button class="btn ghost" style="width:100%" type="submit">{{ $following ? __('Suivi ✓') : __('Suivre') }}</button></form>
          <p class="meta" style="margin:0">{{ __('Seuls les recruteurs vérifiés peuvent écrire aux sportifs.') }}</p>
        @else
          <p style="margin:0">{{ __('Tu es recruteur ? Connecte-toi pour contacter :name.', ['name' => $a['name']]) }}</p>
          <a class="btn" href="{{ route('login') }}">{{ __('Se connecter') }}</a>
        @endif
      </div>

      @if (count($played))
        <div class="section-label">{{ __('Derniers matchs') }}</div>
        <div class="list">
          @foreach ($played as $m)
            @php($r = collect($m['ratings'])->firstWhere('athlete_id', $a['id']))
            <a class="li" href="{{ route('matches.show', $m['id']) }}">
              <span class="note10" style="background:{{ $r['note'] >= 8.5 ? 'var(--pitch)' : ($r['note'] >= 7 ? 'var(--accent)' : 'var(--gold)') }}">{{ str_replace('.', ',', number_format($r['note'], 1)) }}</span>
              <span class="grow"><span class="t">{{ $m['result'] === null ? $m['home'].' '.$m['hs'].'–'.$m['as'].' '.$m['away'] : $m['competition'] }}</span>
                <span class="s">{{ $r['detail'] }}</span></span>
            </a>
          @endforeach
        </div>
      @endif

      @if ($me && ! $mine)
        <details style="margin-top:16px">
          <summary class="meta" style="cursor:pointer">{{ __('Signaler ce profil') }}</summary>
          <form method="POST" action="{{ route('report') }}" class="card" style="margin-top:8px">@csrf
            <input type="hidden" name="target_type" value="profile"><input type="hidden" name="target_id" value="{{ $a['id'] }}">
            <label for="reason">{{ __('Que se passe-t-il ?') }}<textarea id="reason" name="reason" rows="3" required></textarea></label>
            <button class="btn ghost" type="submit">{{ __('Envoyer le signalement') }}</button>
          </form>
        </details>
      @endif
    </aside>
  </div>
</div>
@endsection

@push('scripts')
@php($fl = ['fav' => __('Favori ✓'), 'addFav' => __('Ajouter aux favoris'), 'on' => __('Suivi ✓'), 'follow' => __('Suivre'), 'copied' => __('Lien copié'), 'one' => __('abonné'), 'many' => __('abonnés'), 'favWord' => __('Favori')])
<script>
  var FL = @json($fl);
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.dataset.copy;
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { Terrain.toast(FL.copied); }, function () { Terrain.toast(t); });
    });
  });
  document.querySelectorAll('[data-ajax-follow]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var b = f.querySelector('button');
      window.Terrain.post(f.action).then(function (d) {
        var fav = b.textContent.indexOf(FL.fav) > -1 || b.textContent.indexOf(FL.addFav) > -1;
        b.textContent = d.following ? (fav ? FL.fav : FL.on) : (fav ? FL.addFav : FL.follow);
        var n = document.getElementById('followers-count');
        if (n && d.followers != null) n.textContent = d.followers + ' ' + (d.followers > 1 ? FL.many : FL.one);
      }).catch(function () {});
    });
  });
</script>
@endpush

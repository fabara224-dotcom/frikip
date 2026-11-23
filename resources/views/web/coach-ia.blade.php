@extends('layouts.web')

@section('title', __('Coach IA').' – '.config('terrain.app_name'))
@section('description', __('Ton coach de football personnel : séances sur mesure, conseils de poste, physique, récupération et suivi de tes progrès.'))

@php
  use App\Terrain\Growth\AiCoach;
  $limitHit = $me && $ready && $remaining <= 0;
@endphp

@section('content')
<div class="page gut aic">
  @include('web.partials.defi-coach-switch', ['on' => 'coach-ia'])

  <header class="aic-head">
    <span class="aic-av" aria-hidden="true"><x-ico name="coach" /></span>
    <div>
      <h1>{{ __('Coach IA') }}</h1>
      <p>{{ __('Ton coach personnel, disponible à toute heure : séances sur mesure, poste, physique, récupération.') }}</p>
    </div>
    @if ($me && $msgs->isNotEmpty())
      <form method="POST" action="{{ route('coach.ia.reset') }}" onsubmit="return confirm(@json(__('Effacer la conversation et repartir de zéro ?')))">@csrf
        <button class="aic-new" type="submit" title="{{ __('Nouvelle conversation') }}">{{ __('Nouvelle') }}</button></form>
    @endif
  </header>

  @if (! $ready)
    <div class="aic-empty"><b>{{ __('Le coach IA arrive très bientôt.') }}</b><span>{{ __('Il te fera des séances sur mesure et suivra tes progrès.') }}</span></div>
  @elseif (! $me)
    <div class="aic-empty">
      <b>{{ __('Un coach rien que pour toi') }}</b>
      <span>{{ __('Dis-lui ton poste et ton objectif : il te prépare tes séances, corrige ton programme après chaque match et te motive. :n messages gratuits par jour.', ['n' => $free]) }}</span>
      <a class="btn" href="{{ route('login') }}">{{ __('Se connecter pour commencer') }}</a>
      <a class="aic-link" href="{{ route('register') }}">{{ __('Créer un compte en 30 s') }}</a>
    </div>
  @else
    <div class="aic-thread" id="aic-thread" aria-live="polite">
      @if ($msgs->isEmpty())
        <div class="aic-msg bot">{!! AiCoach::html(__('Salut :name ! Je suis ton coach personnel. Dis-moi ton **poste**, ton **âge** et ce que tu veux améliorer : je te prépare tes séances.', ['name' => explode(' ', trim($me['name'] ?? ''))[0] ?: '👋'])) !!}</div>
      @endif
      @foreach ($msgs as $m)
        <div class="aic-msg {{ $m->role === 'user' ? 'me' : 'bot' }}">{!! $m->role === 'user' ? '<p>'.nl2br(e($m->content)).'</p>' : AiCoach::html($m->content) !!}</div>
      @endforeach
    </div>
    <div id="fin"></div>

    @if ($msgs->isEmpty() && ! $limitHit)
      <div class="aic-sugg" id="aic-sugg">
        @foreach ($suggestions as $s)<button type="button" data-sugg="{{ $s }}">{{ $s }}</button>@endforeach
      </div>
    @endif

    <div class="aic-limit" id="aic-limit" @if (! $limitHit) hidden @endif>
      <b>{{ $premium ? __('À demain !') : __('Tes :n messages gratuits du jour sont utilisés', ['n' => $free]) }}</b>
      <span>{{ $premium ? __('Tu as beaucoup échangé avec le coach aujourd’hui. Reviens demain.') : __('Reviens demain, ou passe en Premium pour discuter avec ton coach sans limite.') }}</span>
      @unless ($premium)<a class="btn" href="{{ route('premium', 'premium') }}">{{ __('Passer en Premium') }}</a>@endunless
    </div>

    <form method="POST" action="{{ route('coach.ia.send') }}" class="aic-form" id="aic-form" @if ($limitHit) hidden @endif>@csrf
      <label for="aic-input" class="visually-hidden">{{ __('Ta question au coach') }}</label>
      <textarea id="aic-input" name="message" rows="1" maxlength="1500" required placeholder="{{ __('Écris au coach…') }}">{{ old('message') }}</textarea>
      <button class="aic-send" type="submit" aria-label="{{ __('Envoyer') }}"><x-ico name="send" /></button>
    </form>
    @error('message')<p class="field-error" id="aic-err">{{ $message }}</p>@else<p class="field-error" id="aic-err" hidden></p>@enderror
    <p class="aic-note">
      <span id="aic-left">@if ($premium){{ __('Premium : messages illimités') }}@else{{ trans_choice('{0} Plus de message gratuit aujourd’hui|{1} 1 message gratuit restant aujourd’hui|[2,*] :count messages gratuits restants aujourd’hui', $remaining, ['count' => $remaining]) }}@endif</span>
      · {{ __('Le coach IA peut se tromper. En cas de douleur ou de blessure, consulte un médecin.') }}
      {{ __('N’écris pas d’informations personnelles (téléphone, adresse).') }} <a href="{{ route('privacy') }}#recommandations">{{ __('Confidentialité') }}</a>
    </p>
  @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('aic-form'); if (!form) return;
  var input = document.getElementById('aic-input'), thread = document.getElementById('aic-thread'), err = document.getElementById('aic-err');
  var left = document.getElementById('aic-left'), limitBox = document.getElementById('aic-limit'), sugg = document.getElementById('aic-sugg');
  var premium = @json($premium), busy = false;
  var T = { thinking: @json(__('Le coach réfléchit…')), fail: @json(__('Le coach ne répond pas pour le moment. Réessaie dans un instant.')),
    zero: @json(__('Plus de message gratuit aujourd’hui')), one: @json(__('1 message gratuit restant aujourd’hui')), many: @json(__(':count messages gratuits restants aujourd’hui')) };

  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function bubble(cls, html) { var d = document.createElement('div'); d.className = 'aic-msg ' + cls; d.innerHTML = html; thread.appendChild(d); return d; }
  function bottom() { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }
  function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 160) + 'px'; }
  function setLeft(n) {
    if (premium || n == null) return;
    left.textContent = n <= 0 ? T.zero : n === 1 ? T.one : T.many.replace(':count', n);
    if (n <= 0) { form.hidden = true; limitBox.hidden = false; }
  }
  function showErr(m) { err.textContent = m; err.hidden = false; }

  input.addEventListener('input', grow);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.matchMedia('(hover:hover)').matches) { e.preventDefault(); form.requestSubmit(); } });
  if (sugg) sugg.addEventListener('click', function (e) { var b = e.target.closest('[data-sugg]'); if (!b) return; input.value = b.dataset.sugg; form.requestSubmit(); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(); if (!text || busy) return;
    busy = true; err.hidden = true; if (sugg) sugg.remove(), sugg = null;
    bubble('me', '<p>' + esc(text).replace(/\n/g, '<br>') + '</p>');
    var wait = bubble('bot typing', '<span class="dots"><i></i><i></i><i></i></span><span class="visually-hidden">' + esc(T.thinking) + '</span>');
    input.value = ''; grow(); bottom();
    fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: (function () { var f = new FormData(form); f.set('message', text); return f; })() })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
      .then(function (res) {
        wait.remove();
        if (res.ok && res.j.html) { bubble('bot', res.j.html); setLeft(res.j.remaining); bottom(); return; }
        var m = res.j.message || (res.j.errors && Object.values(res.j.errors)[0][0]) || (res.status === 419 ? @json(__('La page a expiré : recharge-la.')) : T.fail);
        if (res.j.code === 'limit') setLeft(0); else { input.value = text; grow(); }
        showErr(m);
      })
      .catch(function () { wait.remove(); input.value = text; grow(); showErr(T.fail); })
      .finally(function () { busy = false; input.focus({ preventScroll: true }); });
  });
  grow();
  if (thread.children.length > 1) setTimeout(function () { window.scrollTo(0, document.body.scrollHeight); }, 50);
})();
</script>
@endpush

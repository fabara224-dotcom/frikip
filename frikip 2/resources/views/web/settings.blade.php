@extends('layouts.web')

@section('title', 'Paramètres – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:680px">
    <h1>Paramètres et confidentialité</h1>

    @php
      $m = app(\App\Terrain\Growth\Members::class)->current();
    @endphp
    <div class="section-label" id="preferences">Notifications</div>
    <form method="POST" action="{{ route('prefs') }}" class="card">@csrf
      <p class="meta" style="margin:0">Nouveau message, appel manqué, nouvelle offre dans ton sport, recruteur qui consulte ton profil, détection, défi…</p>
      <label class="check"><input type="checkbox" name="notify_email" value="1" @checked($m->notify_email ?? true)> Par e-mail</label>
      <label class="check"><input type="checkbox" name="notify_sms" value="1" @checked($m->notify_sms ?? false)> Par SMS</label>
      <label class="check"><input type="checkbox" name="notify_whatsapp" value="1" @checked($m->notify_whatsapp ?? false)> Par WhatsApp</label>
      <div class="grid-form">
        <label for="p-phone">Téléphone (SMS / WhatsApp)<input id="p-phone" name="phone" type="tel" value="{{ $m->phone ?? '' }}" placeholder="6XX XX XX XX"></label>
        <label for="p-email">E-mail<input id="p-email" name="email" type="email" value="{{ $m->email ?? ($me['email'] ?? '') }}"></label>
      </div>
      <label class="check"><input type="checkbox" name="data_saver" value="1" @checked(($m->data_saver ?? false) || request()->cookie('terrain_lite') === '1')> Économie de données (vidéos lancées seulement quand je touche)</label>
      @unless (\App\Terrain\Growth\Notifier::smsConfigured())<p class="meta" style="margin:0;font-size:12.5px">SMS et WhatsApp seront actifs dès que l'administrateur aura configuré Twilio.</p>@endunless
      <button class="btn ghost" type="submit">Enregistrer</button>
    </form>

    <div class="section-label" id="push">{{ __('Notifications sur ce téléphone') }}</div>
    <div class="card" id="push-card">
      @if (\App\Terrain\Growth\WebPush::configured())
        <p style="margin:0">{{ __('Reçois une alerte dès qu’on t’écrit ou qu’on t’appelle, même quand le site est fermé.') }}</p>
        <p class="meta" style="margin:0" id="push-state">…</p>
        <div class="row" style="gap:8px;flex-wrap:wrap">
          <button class="btn sm" type="button" id="push-enable">{{ __('Activer sur cet appareil') }}</button>
          <button class="btn ghost sm" type="button" id="push-test">{{ __('Envoyer une notification d’essai') }}</button>
        </div>
        <p class="meta" style="margin:0;font-size:12.5px">{{ __('Sur iPhone : ouvre le site dans Safari, touche Partager puis « Sur l’écran d’accueil », ouvre Frikip depuis l’écran d’accueil et active ici.') }}</p>
      @else
        <p class="meta" style="margin:0">{{ __('Bientôt disponible : l’administrateur doit lancer « php artisan terrain:cles-push ».') }}</p>
      @endif
    </div>

    <div class="section-label">Informations légales</div>
    <div class="list">
      <a class="li" href="{{ route('privacy') }}"><span class="grow t">Politique de confidentialité</span>›</a>
      <a class="li" href="{{ route('terms') }}"><span class="grow t">Conditions d'utilisation</span>›</a>
      <a class="li" href="{{ route('child-safety') }}"><span class="grow t">Sécurité des enfants</span>›</a>
      <a class="li" href="{{ route('support') }}"><span class="grow t">Aide et contact</span>›</a>
    </div>

    <div class="section-label">Signaler un problème</div>
    <form method="POST" action="{{ route('report') }}" class="card">@csrf
      <label for="reason">Comportement suspect, contenu choquant, demande d'argent…<textarea id="reason" name="reason" rows="3" required></textarea></label>
      @error('reason')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <button class="btn ghost" type="submit">Envoyer le signalement</button>
    </form>

    <div class="section-label" id="supprimer">Supprimer mon compte</div>
    <form method="POST" action="{{ route('account.destroy') }}" class="card" style="border-color:color-mix(in srgb,var(--danger) 45%,transparent)">@csrf
      <p style="margin:0">La suppression est <b>définitive</b> : profil, vidéos, messages et candidatures seront effacés. Si tu as un abonnement payé par un store, annule-le aussi dans le store.</p>
      <label for="confirmation">Écris SUPPRIMER pour confirmer<input id="confirmation" name="confirmation" autocomplete="off" required></label>
      @error('confirmation')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <button class="btn danger" type="submit">Supprimer définitivement mon compte</button>
      @if ($demo)<p class="meta" style="margin:0">Mode démo : seule ta session de démonstration est effacée.</p>@endif
    </form>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:20px">@csrf<button class="btn ghost" type="submit">Se déconnecter</button></form>
  </div>
</div>
@endsection

@push('scripts')
@php($pl = ['on' => __('Activées sur cet appareil ✅'), 'off' => __('Pas encore activées sur cet appareil.'), 'denied' => __('Bloquées dans les réglages du navigateur : autorise les notifications pour ce site.'), 'unsupported' => __('Ce navigateur ne prend pas en charge les notifications. Utilise Chrome, ou ajoute le site à l’écran d’accueil sur iPhone.'), 'insecure' => __('Les notifications demandent une adresse sécurisée (https://).'), 'sent' => __('Notification envoyée à :n appareil(s).'), 'none' => __('Aucun appareil abonné : active d’abord les notifications.')])
<script>
(function () {
  var st = document.getElementById('push-state'); if (!st) return;
  var L = @json($pl), T = window.Terrain;
  function show() {
    if (!window.isSecureContext) { st.textContent = L.insecure; return; }
    if (!('Notification' in window) || !('PushManager' in window)) { st.textContent = L.unsupported; return; }
    if (Notification.permission === 'denied') { st.textContent = L.denied; return; }
    navigator.serviceWorker.ready.then(function (r) { return r.pushManager.getSubscription(); }).then(function (s) { st.textContent = s ? L.on : L.off; });
  }
  document.getElementById('push-enable').onclick = function () { (T.pushSubscribe ? T.pushSubscribe() : Promise.reject()).then(show, show); };
  document.getElementById('push-test').onclick = function () {
    T.post(@json(route('push.test'))).then(function (d) { T.toast(d.sent ? L.sent.replace(':n', d.sent) : L.none); }).catch(function () {});
  };
  show();
})();
</script>
@endpush

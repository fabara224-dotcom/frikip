@extends('layouts.web')

@section('title', __('Fil').' – '.config('terrain.app_name'))
@section('body-class', 'feedpage')
@section('fullbleed', '1')


@section('rail')
  <a class="railhero" href="{{ ($me['role'] ?? null) === 'athlete' ? route('publish') : route('phone.login') }}">
    <span class="railhero-k">{{ __('Sur Frikip') }}</span>
    <b>{{ __('Découvre les talents de demain') }}</b>
    <small>{{ __('Entraînements · Matchs · Recrutement') }}</small>
    <span class="btn sm">{{ ($me['role'] ?? null) === 'athlete' ? __('Publier ma vidéo') : __('Rejoindre la communauté') }}</span>
  </a>
  @if (! empty($challenge))
    <section class="railbox">
      <header><h2>{{ __('Défi en cours') }}</h2><a href="{{ route('challenges') }}">{{ __('Voir tout') }} →</a></header>
      <a class="railrow" href="{{ route('challenges.show', $challenge->id) }}"><span class="lico"><x-ico name="trophy" /></span><span class="grow"><b>@td($challenge->title)</b><small>{{ __('Vote pour la meilleure action') }}</small></span></a>
    </section>
  @endif
  <section class="railbox">
    <header><h2>{{ __('À ne pas manquer') }}</h2><a href="{{ route('explore') }}">{{ __('Voir tout') }} →</a></header>
    <a class="railrow" href="{{ route('matches') }}"><span class="lico"><x-ico name="score" /></span><span class="grow"><b>{{ __('Matchs du jour') }}</b><small>{{ __('Scores en direct et résultats') }}</small></span></a>
    <a class="railrow" href="{{ route('rankings') }}"><span class="lico"><x-ico name="star" /></span><span class="grow"><b>{{ __('Classements') }}</b><small>{{ __('Les sportifs les plus suivis') }}</small></span></a>
    <a class="railrow" href="{{ route('events') }}"><span class="lico"><x-ico name="calendar" /></span><span class="grow"><b>{{ __('Détections') }}</b><small>{{ __('Essais organisés par les clubs') }}</small></span></a>
    <a class="railrow" href="{{ route('clubs') }}"><span class="lico"><x-ico name="stadium" /></span><span class="grow"><b>{{ __('Clubs et académies') }}</b><small>{{ __('Trouve une structure près de chez toi') }}</small></span></a>
  </section>
@endsection

@section('content')
<div class="feedwrap">
  <div class="tktop">
    @foreach ($onAir ?? [] as $st)
      <a class="tick onair" href="{{ route('live.show', $st->id) }}"><i aria-hidden="true"></i>{{ __('En direct') }} · @if (\App\Terrain\Growth\Live::hasScore($st)){{ $st->home }} {{ $st->home_score }}–{{ $st->away_score }} {{ $st->away }}@else{{ \Illuminate\Support\Str::limit(\App\Terrain\Growth\Live::label($st), 40) }}@endif</a>
    @endforeach
    @if ($partnerLive = app(\App\Terrain\Growth\PartnerLink::class)->get())
      <a class="tick onair" href="{{ route('live.partner') }}"><i aria-hidden="true"></i>{{ __('Matchs en direct') }} · {{ $partnerLive->name }}</a>
    @endif
    @if (! empty($challenge))
      <a class="tick chal" href="{{ route('challenges.show', $challenge->id) }}">🏆 {{ __('Défi') }} : @td($challenge->title)</a>
    @endif
    @php
      [$tkLead, $tkText] = $live ? \App\Terrain\Growth\ScoreChoice::text($live) : ['', ''];
      $sc = $scoreChoice ?? ['mode' => 'auto', 'value' => '', 'label' => ''];
    @endphp
    <span class="tkrow">
      <a class="tick" id="ticker" href="{{ $live ? route('matches.show', $live['id']) : route('matches') }}" @if (! $live) hidden @endif>
        @if ($live)<b>{{ $tkLead }}</b> {{ $tkText }}@endif
      </a>
      <button class="tick tkpick" type="button" id="scorepick" aria-haspopup="dialog" aria-label="{{ __('Choisir le score affiché') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>{{ __('Mon score') }}
      </button>
    </span>
  </div>

  <div class="tk" id="tk">
    @if (count($items))
      @include('web.partials.feed-items', ['items' => $items, 'following' => $following, 'me' => $me ?? null])
      @if ($next)<div class="tk-more" id="tk-more" data-next="{{ $next }}">{{ __('Chargement…') }}</div>@endif
    @else
      <div class="feedempty">
        <b>{{ __('Pas encore de vidéo ici.') }}</b>
        <p>{{ __('Sois le premier : publie ta compilation et les recruteurs la verront ici.') }}</p>
        <a class="btn" href="{{ ($me['role'] ?? null) === 'athlete' ? route('publish') : route('phone.login') }}">{{ ($me['role'] ?? null) === 'athlete' ? __('Publier ma compilation') : __('Créer mon compte sportif') }}</a>
        <a class="btn ghost" href="{{ route('live') }}" style="color:#fff;border-color:rgba(255,255,255,.4)">{{ __('Voir les matchs en direct') }}</a>
      </div>
    @endif
  </div>
</div>

{{-- Commentaires --}}
<div class="sheet-ov" id="cmts" hidden>
  <div class="sheet" role="dialog" aria-labelledby="cmts-title">
    <div class="hd"><b id="cmts-title">{{ __('Commentaires') }} <span id="cmts-count" class="meta"></span></b>
      <span class="row" style="gap:6px;align-items:center">
        <span class="seg" role="group" aria-label="{{ __('Trier') }}"><button type="button" data-sort="top" aria-pressed="true">{{ __('Pertinents') }}</button><button type="button" data-sort="recent" aria-pressed="false">{{ __('Récents') }}</button></span>
        <button class="x" type="button" data-close aria-label="{{ __('Fermer') }}">×</button>
      </span>
    </div>
    <div class="bd" id="cmts-list"></div>
    <div class="replying" id="cmts-replying" hidden><span></span><button type="button" data-cancel-reply aria-label="{{ __('Annuler la réponse') }}">×</button></div>
    <form id="cmts-form">
      <input id="cmts-input" type="text" maxlength="1000" placeholder="{{ $me ? __('Ajouter un commentaire…') : __('Connecte-toi pour commenter') }}" aria-label="{{ __('Commentaire') }}" autocomplete="off" @unless ($me) disabled @endunless>
      <button class="btn" type="submit" @unless ($me) disabled @endunless>{{ __('Envoyer') }}</button>
    </form>
  </div>
</div>

{{-- Choix du score affiché en haut du fil --}}
<div class="sheet-ov" id="scs" hidden>
  <div class="sheet" role="dialog" aria-labelledby="scs-title">
    <div class="hd"><b id="scs-title">{{ __('Score en haut du fil') }}</b><button class="x" type="button" data-close aria-label="{{ __('Fermer') }}">×</button></div>
    <form method="POST" action="{{ route('score.choose') }}" class="bd scorepick" id="scs-form">@csrf
      <input type="hidden" name="back" value="{{ route('feed') }}">
      <input type="hidden" name="label" id="scs-label" value="{{ $sc['label'] }}">
      <label class="scopt"><input type="radio" name="mode" value="auto" @checked($sc['mode'] === 'auto')><span><b>{{ __('Automatique') }}</b><small>{{ __('Le premier match en direct du moment.') }}</small></span></label>
      <label class="scopt"><input type="radio" name="mode" value="team" @checked($sc['mode'] === 'team')><span><b>{{ __('Mon équipe') }}</b><small>{{ __('Son match du jour : en direct, à venir ou le résultat.') }}</small></span></label>
      <div class="scval" data-for="team">
        <input name="value" list="scs-teams" maxlength="80" value="{{ $sc['mode'] === 'team' ? $sc['value'] : '' }}" placeholder="{{ __('ex. Horoya, Real Madrid, Liverpool') }}" aria-label="{{ __('Nom de l’équipe') }}" @disabled($sc['mode'] !== 'team')>
        <datalist id="scs-teams">@foreach ($scoreOptions['teams'] ?? [] as $t)<option value="{{ $t }}">@endforeach</datalist>
      </div>
      <label class="scopt"><input type="radio" name="mode" value="league" @checked($sc['mode'] === 'league')><span><b>{{ __('Une compétition') }}</b><small>{{ __('Le match en cours ou le prochain de la compétition.') }}</small></span></label>
      <div class="scval" data-for="league">
        <select name="value" aria-label="{{ __('Compétition') }}" @disabled($sc['mode'] !== 'league')>
          @foreach ($scoreOptions['leagues'] ?? [] as $id => $name)<option value="{{ $id }}" @selected($sc['mode'] === 'league' && $sc['value'] === (string) $id)>{{ $name }}</option>@endforeach
        </select>
      </div>
      @if ($sc['mode'] === 'match')
        <label class="scopt"><input type="radio" name="mode" value="match" checked><span><b>{{ __('Un match précis') }}</b><small>{{ $sc['label'] ?: __('Choisi depuis la page du match.') }}</small></span></label>
        <input type="hidden" name="value" value="{{ $sc['value'] }}" class="scmatch">
      @endif
      <label class="scopt"><input type="radio" name="mode" value="off" @checked($sc['mode'] === 'off')><span><b>{{ __('Aucun score') }}</b><small>{{ __('Seulement les vidéos.') }}</small></span></label>
      <p class="meta" style="margin:0">{{ __('Astuce : sur la page d’un match, touche « Afficher dans mon fil ».') }}</p>
      <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
    </form>
  </div>
</div>

{{-- Partage --}}
<div class="sheet-ov" id="shr" hidden>
  <div class="sheet" role="dialog" aria-labelledby="shr-title">
    <div class="hd"><b id="shr-title">{{ __('Partager') }}</b><button class="x" type="button" data-close aria-label="{{ __('Fermer') }}">×</button></div>
    <div class="bd shr-grid">
      <button type="button" data-ch="whatsapp"><i style="background:#25D366">W</i>WhatsApp</button>
      <button type="button" data-ch="facebook"><i style="background:#1877F2">f</i>Facebook</button>
      <button type="button" data-ch="lien"><i style="background:#555">🔗</i>{{ __('Copier le lien') }}</button>
      <button type="button" data-ch="natif" id="shr-native" hidden><i style="background:#111">⋯</i>{{ __('Autres applis') }}</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@php
  $jsL = [
    'reply' => __('Répondre'), 'replies' => __('réponses'), 'oneReply' => __('1 réponse'), 'hide' => __('Masquer'),
    'delete' => __('Supprimer'), 'pin' => __('Épingler'), 'unpin' => __('Désépingler'), 'pinned' => __('Épinglé par l’auteur'),
    'author' => __('Auteur'), 'recruiter' => __('Recruteur'), 'none' => __('Aucun commentaire. Sois le premier.'),
    'loading' => __('Chargement…'), 'replyTo' => __('Réponse à'), 'deleteQ' => __('Supprimer ce commentaire ?'),
    'notSent' => __('Commentaire non envoyé'), 'follow' => __('Abonné'), 'soundOn' => __('Son activé'), 'soundOff' => __('Son coupé'),
    'copied' => __('Lien copié'), 'reported' => __('Signalement envoyé à la modération'), 'share' => __('Partager'),
    'ccOff' => __('Sous-titres désactivés'), 'ccOn' => __('Sous-titres'), 'no' => __('Non'), 'justNow' => __('à l’instant'),
  ];
@endphp
<script>
(function () {
  var tk = document.getElementById('tk'); if (!tk) return;
  var T = window.Terrain, muted = true;
  var L = @json($jsL);
  var locale = @json(app()->getLocale());
  var fmt = function (n) { return n >= 1000 ? (Math.round(n / 100) / 10).toString().replace('.', locale === 'en' ? '.' : ',') + ' k' : String(n); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
  var urls = {
    like: @json(route('videos.like', '__ID__')), comments: @json(route('videos.comments', '__ID__')),
    comment: @json(route('videos.comment', '__ID__')), follow: @json(route('athletes.follow', '__ID__')),
    clike: @json(route('comments.like', '__ID__')), cdel: @json(route('comments.delete', '__ID__')), cpin: @json(route('comments.pin', '__ID__')),
    share: @json(route('videos.share', '__ID__')), view: @json(route('videos.view', '__ID__')),
    report: @json(route('report')), ticker: @json(route('matches.ticker')), adSeen: @json(route('ads.seen', '__ID__'))
  };
  var u = function (k, id) { return urls[k].replace('__ID__', encodeURIComponent(id)); };

  // ---------------------------------------------------------------- sous-titres
  // La piste par défaut est choisie par le serveur : vidéo en anglais + page en français → sous-titres français (et inversement).
  function setSubs(s, lang) {
    var v = s.querySelector('video'), box = s.querySelector('.subs'), btn = s.querySelector('[data-cc]');
    s.dataset.subs = lang || '';
    Array.prototype.forEach.call(v.textTracks, function (t) {
      t.mode = (lang && t.language === lang) ? 'hidden' : 'disabled';   // « hidden » : on affiche nous-mêmes le texte, au-dessus des boutons
      if (t.mode === 'hidden' && !t._terrain) {
        t._terrain = true;
        t.addEventListener('cuechange', function () {
          if (s.dataset.subs !== t.language) return;
          var c = t.activeCues && t.activeCues[0];
          box.textContent = c ? c.text : ''; box.hidden = !c;
          // juste au-dessus du nom et du titre, quelle que soit leur hauteur
          var meta = s.querySelector('.tmeta'); if (c && meta) box.style.bottom = (meta.offsetHeight + 34) + 'px';
        });
      }
    });
    if (!lang) { box.hidden = true; box.textContent = ''; }
    if (btn) { btn.classList.toggle('on', !!lang); btn.setAttribute('aria-pressed', lang ? 'true' : 'false'); btn.querySelector('span').textContent = lang ? lang.toUpperCase() : L.no; }
  }
  function cycleSubs(s) {
    var v = s.querySelector('video'), langs = Array.prototype.map.call(v.querySelectorAll('track'), function (t) { return t.srclang; });
    var order = [''].concat(langs), i = order.indexOf(s.dataset.subs || '');
    var next = order[(i + 1) % order.length];
    setSubs(s, next); T.toast(next ? L.ccOn + ' : ' + next.toUpperCase() : L.ccOff);
  }

  // ---------------------------------------------------------------- son
  function applySound() {
    tk.classList.toggle('sound-on', !muted);
    tk.querySelectorAll('video').forEach(function (v) { v.muted = muted; });
  }

  // ---------------------------------------------------------------- lecture
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var v = e.target.querySelector('video');
      if (e.target.dataset.live) { liveCard(e.target, e.isIntersecting && e.intersectionRatio > 0.6); return; }
      if (e.isIntersecting && e.intersectionRatio > 0.6) {
        if (e.target.dataset.ad && !e.target._seen) { e.target._seen = true; fetch(urls.adSeen.replace('__ID__', e.target.dataset.ad), { method: 'POST', headers: { 'X-CSRF-TOKEN': T.csrf } }).catch(function () {}); }
        if (T.lite) { e.target.classList.add('paused'); return; }   // économie de données : lecture au toucher
        v.muted = muted; v.play().catch(function () {}); e.target.classList.remove('paused');
        var hint = e.target.querySelector('.tap-sound'); if (hint && muted) hint.hidden = false;
      }
      else { v.pause(); }
    });
  }, { root: tk, threshold: [0, 0.6, 1] });

  // Match en direct dans le fil : le lecteur démarre (sans le son) quand la carte est à l'écran, s'arrête quand on passe
  function liveCard(s, on) {
    var f = s.querySelector('iframe[data-src]'); if (!f) return;
    if (on && !T.lite) { if (!f.getAttribute('src')) f.setAttribute('src', f.dataset.src); }
    else if (f.getAttribute('src')) f.removeAttribute('src');
  }
  function watch(s) {
    io.observe(s);
    if (s.dataset.live) return;
    if (s.dataset.ad) return;   // publicité : pas de vue, pas de sous-titres
    var v = s.querySelector('video'), bar = s.querySelector('.prog i'), counted = false, played = 0, last = null;
    setSubs(s, s.dataset.subs || '');
    // Une vue compte après 2 secondes de lecture réelle
    v.addEventListener('timeupdate', function () {
      if (v.duration) bar.style.width = (v.currentTime / v.duration * 100) + '%';
      if (last !== null && v.currentTime > last) played += Math.min(1, v.currentTime - last);
      last = v.currentTime;
      if (!counted && played >= 2) {
        counted = true;
        fetch(u('view', s.dataset.video), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': T.csrf }, body: JSON.stringify({ athlete: s.dataset.athlete, sport: s.dataset.sport }) }).catch(function () {});
      }
    });
    v.addEventListener('seeked', function () { last = v.currentTime; });
  }
  tk.querySelectorAll('.tki').forEach(watch);

  // Défilement infini : 20 vidéos de plus quand on approche de la fin
  var more = document.getElementById('tk-more'), loading = false;
  if (more) new IntersectionObserver(function (entries) {
    if (!entries[0].isIntersecting || loading || !more.dataset.next) return;
    loading = true;
    fetch(more.dataset.next, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) {
      more.dataset.next = r.headers.get('X-Next-Page') || ''; return r.text();
    }).then(function (html) {
      var box = document.createElement('div'); box.innerHTML = html;
      Array.prototype.slice.call(box.children).forEach(function (s) { tk.insertBefore(s, more); if (s.classList.contains('tki')) { watch(s); s.querySelectorAll('video').forEach(function (v) { v.muted = muted; }); } });
      if (!more.dataset.next) more.remove();
    }).catch(function () {}).then(function () { loading = false; });
  }, { root: tk, rootMargin: '0px 0px 1200px 0px' }).observe(more);

  // Toucher = pause ; double toucher = j'aime
  var timer = null;
  tk.addEventListener('click', function (e) {
    var vid = e.target.closest('.vid'); if (!vid || e.target.closest('.rail, a, button')) return;
    var s = vid.closest('.tki'), v = vid.querySelector('video'); if (!v) return;
    if (timer) { clearTimeout(timer); timer = null; if (!s.dataset.ad) likeVideo(s, true, e); return; }
    timer = setTimeout(function () { timer = null; if (v.paused) { v.play().catch(function(){}); s.classList.remove('paused'); } else { v.pause(); s.classList.add('paused'); } }, 260);
  });

  function likeVideo(s, onlyAdd, e) {
    var b = s.querySelector('[data-like]');
    if (e) {
      var r = s.querySelector('.vid').getBoundingClientRect(), h = document.createElement('div');
      h.className = 'pop'; h.innerHTML = b.querySelector('svg').outerHTML; h.style.left = (e.clientX - r.left) + 'px'; h.style.top = (e.clientY - r.top) + 'px';
      s.querySelector('.vid').appendChild(h); setTimeout(function () { h.remove(); }, 800);
    }
    if (onlyAdd && b.classList.contains('on')) return;
    if (!T.loggedIn) { location.href = T.loginUrl; return; }
    T.post(u('like', b.dataset.like)).then(function (d) {
      b.classList.toggle('on', d.liked); b.setAttribute('aria-pressed', d.liked ? 'true' : 'false'); b.querySelector('span').textContent = fmt(d.likes);
    }).catch(function () {});
  }

  // ---------------------------------------------------------------- boutons de la colonne de droite
  tk.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    var s = t.closest('.tki');
    if (t.dataset.like) { e.preventDefault(); likeVideo(s, false); }
    else if (t.dataset.follow) {
      e.preventDefault(); e.stopPropagation();
      if (!T.loggedIn) { location.href = T.loginUrl; return; }
      T.post(u('follow', t.dataset.follow)).then(function () { document.querySelectorAll('[data-follow="' + t.dataset.follow + '"]').forEach(function (p) { p.remove(); }); T.toast(L.follow); }).catch(function () {});
    }
    else if (t.dataset.comments) openComments(t.dataset.comments, t);
    else if (t.dataset.share) openShare(t);
    else if (t.hasAttribute('data-sound')) {
      muted = !muted; applySound();
      tk.querySelectorAll('.tap-sound').forEach(function (h) { h.hidden = true; });
      var v = s && s.querySelector('video'); if (v && v.paused && !T.lite) v.play().catch(function () {});
      T.toast(muted ? L.soundOff : L.soundOn);
    }
    else if (t.hasAttribute('data-cc')) cycleSubs(s);
    else if (t.dataset.report) {
      if (!T.loggedIn) { location.href = T.loginUrl; return; }
      T.post(urls.report, { reason: 'Vidéo signalée depuis le fil', target_type: 'video', target_id: t.dataset.report })
        .then(function () { T.toast(L.reported); }, function () { T.toast(L.reported); });
    }
  });

  // ---------------------------------------------------------------- partage (compté pour de vrai)
  var shr = document.getElementById('shr'), shrBtn = null, native = document.getElementById('shr-native');
  if (navigator.share) native.hidden = false;
  function openShare(btn) { shrBtn = btn; shr.hidden = false; }
  function countShare(channel) {
    T.post(u('share', shrBtn.dataset.shareVideo), { channel: channel }).then(function (d) {
      shrBtn.querySelector('span').textContent = d.shares ? fmt(d.shares) : L.share;
    }).catch(function () {});
  }
  shr.addEventListener('click', function (e) {
    if (e.target === shr || e.target.closest('[data-close]')) { shr.hidden = true; return; }
    var b = e.target.closest('[data-ch]'); if (!b || !shrBtn) return;
    var link = shrBtn.dataset.share, text = shrBtn.dataset.shareText || '', ch = b.dataset.ch;
    if (ch === 'whatsapp') window.open('https://wa.me/?text=' + encodeURIComponent(text + ' ' + link), '_blank');
    else if (ch === 'facebook') window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link), '_blank');
    else if (ch === 'lien') { (navigator.clipboard ? navigator.clipboard.writeText(link) : Promise.reject()).then(function () { T.toast(L.copied); }, function () { prompt('', link); }); }
    else if (ch === 'natif') { navigator.share({ title: text, url: link }).then(function () { countShare('natif'); }).catch(function () {}); shr.hidden = true; return; }
    countShare(ch); shr.hidden = true;
  });

  // ---------------------------------------------------------------- commentaires
  var ov = document.getElementById('cmts'), list = document.getElementById('cmts-list'), form = document.getElementById('cmts-form'), input = document.getElementById('cmts-input');
  var countEl = document.getElementById('cmts-count'), replying = document.getElementById('cmts-replying');
  var current = null, currentBtn = null, sort = 'top', replyTo = null, total = 0;
  var star = @json(view('components.star')->render());
  var heart = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.6-9.6-9.5A5.4 5.4 0 0 1 12 6a5.4 5.4 0 0 1 9.6 5.5C19.5 16.4 12 21 12 21z"/></svg>';

  function initials(n) { return (n || '?').split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase(); }
  function one(c, isReply) {
    var name = c.profile ? '<a href="' + esc(c.profile) + '">' + esc(c.author) + '</a>' : esc(c.author);
    var tags = (c.star ? star : '') + (c.is_owner ? '<span class="ctag">' + L.author + '</span>' : '') + (c.recruiter ? '<span class="ctag rec">' + L.recruiter + '</span>' : '');
    var acts = '';
    if (!isReply && T.loggedIn) acts += '<button type="button" data-reply="' + esc(c.id) + '" data-name="' + esc(c.author) + '">' + L.reply + '</button>';
    if (c.can_pin) acts += '<button type="button" data-pin="' + esc(c.id) + '" data-on="' + (c.pinned ? 0 : 1) + '">' + (c.pinned ? L.unpin : L.pin) + '</button>';
    if (c.can_delete) acts += '<button type="button" data-del="' + esc(c.id) + '">' + (c.mine ? L.delete : L.hide) + '</button>';
    var h = '<div class="cm' + (isReply ? ' reply' : '') + '" data-cid="' + esc(c.id) + '">'
      + '<span class="cav">' + esc(initials(c.author)) + '</span>'
      + '<div class="cbody">'
      + (c.pinned ? '<div class="cpin">📌 ' + L.pinned + '</div>' : '')
      + '<div class="cwho">' + name + tags + '<span class="cwhen">' + esc(c.when) + '</span></div>'
      + '<div class="ctext">' + esc(c.text) + '</div>'
      + '<div class="cacts">' + acts + '</div>';
    if (!isReply) {
      h += '<div class="creplies">' + (c.replies || []).map(function (r) { return one(r, true); }).join('') + '</div>';
    }
    h += '</div><button type="button" class="clike' + (c.liked ? ' on' : '') + '" data-clike="' + esc(c.id) + '" aria-pressed="' + (c.liked ? 'true' : 'false') + '" aria-label="J’aime">' + heart + '<span>' + (c.likes || '') + '</span></button></div>';
    return h;
  }
  function render(d) {
    total = d.count; countEl.textContent = total ? '(' + total + ')' : '';
    list.innerHTML = d.comments.length ? d.comments.map(function (c) { return one(c, false); }).join('') : '<p class="meta">' + L.none + '</p>';
    // Les longues séries de réponses sont repliées
    list.querySelectorAll('.creplies').forEach(function (box) {
      var n = box.children.length; if (n <= 2) return;
      Array.prototype.slice.call(box.children, 1).forEach(function (r) { r.hidden = true; });
      box.insertAdjacentHTML('beforeend', '<button type="button" class="cmore" data-more>' + (n - 1) + ' ' + L.replies + ' ▾</button>');
    });
  }
  function setCount(n) {
    total = Math.max(0, n); countEl.textContent = total ? '(' + total + ')' : '';
    if (currentBtn) currentBtn.querySelector('span').textContent = fmt(total);
  }
  function load() {
    list.innerHTML = '<p class="meta">' + L.loading + '</p>';
    fetch(u('comments', current) + '?tri=' + sort, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { render(d); setCount(d.count); });
  }
  function openComments(id, btn) { current = id; currentBtn = btn; ov.hidden = false; cancelReply(); load(); }
  function cancelReply() { replyTo = null; replying.hidden = true; input.placeholder = input.disabled ? input.placeholder : @json(__('Ajouter un commentaire…')); }
  function closeOv() { ov.hidden = true; shr.hidden = true; }
  ov.addEventListener('click', function (e) {
    if (e.target === ov || e.target.closest('[data-close]')) { closeOv(); return; }
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.sort) {
      sort = b.dataset.sort; ov.querySelectorAll('[data-sort]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); load();
    } else if (b.hasAttribute('data-cancel-reply')) cancelReply();
    else if (b.dataset.reply) {
      replyTo = b.dataset.reply; replying.hidden = false; replying.querySelector('span').textContent = L.replyTo + ' ' + b.dataset.name;
      input.placeholder = '@' + b.dataset.name; input.focus();
    } else if (b.hasAttribute('data-more')) {
      b.parentNode.querySelectorAll('.cm').forEach(function (r) { r.hidden = false; }); b.remove();
    } else if (b.dataset.clike) {
      if (!T.loggedIn) { location.href = T.loginUrl; return; }
      T.post(u('clike', b.dataset.clike)).then(function (d) {
        b.classList.toggle('on', d.liked); b.setAttribute('aria-pressed', d.liked ? 'true' : 'false'); b.querySelector('span').textContent = d.likes || '';
      }).catch(function () {});
    } else if (b.dataset.del) {
      if (!confirm(L.deleteQ)) return;
      T.post(u('cdel', b.dataset.del)).then(function () {
        var el = list.querySelector('[data-cid="' + b.dataset.del + '"]');
        var n = el ? 1 + el.querySelectorAll('.cm').length : 1; if (el) el.remove(); setCount(total - n);
      }).catch(function () {});
    } else if (b.dataset.pin) {
      T.post(u('cpin', b.dataset.pin), { on: b.dataset.on === '1' }).then(load).catch(function () {});
    }
  });
  shr.addEventListener('keydown', function () {});
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeOv(); });
  form.addEventListener('submit', function (e) {
    e.preventDefault(); var text = input.value.trim(); if (!text || !current) return;
    var btn = form.querySelector('button'); btn.disabled = true;
    T.post(u('comment', current), { text: text, parent_id: replyTo }).then(function (d) {
      input.value = '';
      var c = d.comment; if (!c) return;
      if (c.parent_id) {
        var parent = list.querySelector('[data-cid="' + c.parent_id + '"] .creplies');
        if (parent) parent.insertAdjacentHTML('beforeend', one(c, true)); else load();
      } else {
        var empty = list.querySelector('p.meta'); if (empty) empty.remove();
        list.insertAdjacentHTML('afterbegin', one(c, false));
      }
      setCount(total + 1); cancelReply();
    }).catch(function () { T.toast(L.notSent); }).then(function () { btn.disabled = false; });
  });

  // Lien « ?commentaires=1 » (notification) : ouvre les commentaires de la première vidéo
  if (/[?&]commentaires=1/.test(location.search)) {
    var first = tk.querySelector('[data-comments]'); if (first) openComments(first.dataset.comments, first);
  }

  // ---------------------------------------------------------------- score choisi par le visiteur, en haut du fil (toutes les 15 s)
  var ticker = document.getElementById('ticker'), pick = document.getElementById('scorepick');
  setInterval(function () {
    fetch(urls.ticker, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.id) { ticker.hidden = true; return; }
      ticker.hidden = false; ticker.href = d.url;
      ticker.innerHTML = '<b>' + esc(d.lead) + '</b> ' + esc(d.rest);
    }).catch(function () {});
  }, 15000);

  // Feuille « Score en haut du fil » : un seul champ actif selon le choix (équipe ou compétition)
  var scs = document.getElementById('scs'), scsForm = document.getElementById('scs-form');
  pick.addEventListener('click', function () { scs.hidden = false; });
  scs.addEventListener('click', function (e) { if (e.target === scs || e.target.closest('[data-close]')) scs.hidden = true; });
  function syncScore() {
    var mode = (scsForm.querySelector('[name=mode]:checked') || {}).value;
    scsForm.querySelectorAll('.scval').forEach(function (box) {
      var on = box.dataset.for === mode; box.hidden = !on;
      box.querySelectorAll('input,select').forEach(function (f) { f.disabled = !on; });
    });
    var m = scsForm.querySelector('.scmatch'); if (m) m.disabled = mode !== 'match';
  }
  scsForm.addEventListener('change', syncScore);
  scsForm.addEventListener('submit', function () {
    var sel = scsForm.querySelector('.scval[data-for=league] select');
    if (sel && !sel.disabled) document.getElementById('scs-label').value = sel.options[sel.selectedIndex].text;
  });
  syncScore();
})();
</script>
@endpush

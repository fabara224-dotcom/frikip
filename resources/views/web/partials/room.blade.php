{{-- Salon en direct d'un match ou d'un live : commentaires et réactions des supporters. $room : « m-<match> » ou « l-<live> ». --}}
@php
  $roomTitle ??= __('Salon du match');
@endphp
<section class="room" id="room" data-room="{{ $room }}" data-src="{{ route('room', $room) }}" data-post="{{ $me ? route('room.post', $room) : '' }}" data-report="{{ $me ? route('room.report', '__ID__') : '' }}">
  <div class="room-hd"><h2>💬 {{ $roomTitle }}</h2><span class="meta" id="room-active"></span></div>
  <ol class="room-list" id="room-list" aria-live="polite" aria-label="{{ __('Messages du salon') }}"><li class="room-empty meta">{{ __('Sois le premier à commenter !') }}</li></ol>
  @if ($me)
    <div class="room-reacts" role="group" aria-label="{{ __('Réagir') }}">
      @foreach (\App\Terrain\Growth\Rooms::REACTIONS as $r)<button type="button" data-react="{{ $r }}" aria-label="{{ __('Réagir') }} {{ $r }}">{{ $r }}</button>@endforeach
    </div>
    <form class="room-form" id="room-form">
      <input id="room-input" maxlength="280" placeholder="{{ __('Ton commentaire sur le match…') }}" aria-label="{{ __('Ton commentaire') }}" autocomplete="off">
      <button class="btn" type="submit">{{ __('Envoyer') }}</button>
    </form>
    <p class="meta room-rules">{{ __('Respect, pas d’insultes, pas d’argent demandé. Un message signalé 3 fois disparaît.') }}</p>
  @else
    <p class="meta"><a href="{{ route('register', ['type' => 'supporter']) }}">{{ __('Crée ton compte supporter') }}</a> {{ __('ou') }} <a href="{{ route('login') }}">{{ __('connecte-toi') }}</a> {{ __('pour commenter.') }}</p>
  @endif
</section>
@push('scripts')
<script>
(function () {
  var box = document.getElementById('room'); if (!box) return;
  var list = document.getElementById('room-list'), last = 0, T = window.Terrain;
  var L = { report: @json(__('Signaler')), reported: @json(__('Message signalé. Merci.')), online: @json(__(':n en ligne')) };
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function add(m) {
    var empty = list.querySelector('.room-empty'); if (empty) empty.remove();
    var li = document.createElement('li'); li.className = 'rm ' + (m.kind === 'react' ? 'react' : '') + (m.mine ? ' mine' : ''); li.dataset.id = m.id;
    li.innerHTML = '<b>' + esc(m.name) + '</b> <span class="rb">' + esc(m.body) + '</span> <time>' + esc(m.at) + '</time>' +
      (!m.mine && box.dataset.report ? ' <button type="button" class="rrep" title="' + L.report + '" aria-label="' + L.report + '">⚑</button>' : '');
    var stick = list.scrollTop + list.clientHeight >= list.scrollHeight - 40;
    list.appendChild(li); while (list.children.length > 200) list.firstChild.remove();
    if (stick) list.scrollTop = list.scrollHeight;
  }
  function poll() {
    if (document.hidden) return;
    fetch(box.dataset.src + '?apres=' + last, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      (d.messages || []).forEach(function (m) { if (m.id > last) { add(m); last = m.id; } });
      document.getElementById('room-active').textContent = d.active ? '🟢 ' + L.online.replace(':n', d.active) : '';
    }).catch(function () {});
  }
  function send(data) {
    return fetch(box.dataset.post, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': T.csrf, Accept: 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json(); }).then(function (d) { if (d.error) T.toast(d.error); else poll(); });
  }
  var form = document.getElementById('room-form');
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault(); var i = document.getElementById('room-input'), v = i.value.trim(); if (!v) return;
    send({ body: v }).then(function () { i.value = ''; });
  });
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-react]'); if (b) { send({ react: b.dataset.react }); return; }
    var r = e.target.closest('.rrep'); if (r) {
      var id = r.closest('.rm').dataset.id;
      fetch(box.dataset.report.replace('__ID__', id), { method: 'POST', headers: { 'X-CSRF-TOKEN': T.csrf } }).then(function () { T.toast(L.reported); r.remove(); });
    }
  });
  poll(); setInterval(poll, 3000);
})();
</script>
@endpush

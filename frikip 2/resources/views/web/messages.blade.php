@extends('layouts.web')

@section('title', 'Messages – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:720px">
    <h1>Messages</h1>

    @if ($invite)
      <div class="card invite">
        <b>Tester les messages et les appels à deux</b>
        <p class="meta" style="margin:4px 0 10px">Ouvre ce lien dans un autre navigateur, une fenêtre privée ou sur un autre appareil : tu y seras {{ $invite['as'] }}, et vous pourrez vous écrire, vous envoyer des vocaux et vous appeler.</p>
        <div class="row" style="gap:8px;flex-wrap:wrap">
          <input class="grow" type="text" readonly value="{{ $invite['url'] }}" aria-label="Lien d'invitation" onclick="this.select()">
          <button class="btn sm" type="button" data-copy="{{ $invite['url'] }}">Copier le lien</button>
        </div>
        @if ($invite['joined'])<p class="meta" style="margin:8px 0 0">✓ Un deuxième appareil a rejoint cette démo.</p>@endif
        <p class="meta" style="margin:8px 0 0;font-size:12.5px">Les appels demandent une adresse sécurisée : <b>https://…</b>, ou <b>http://127.0.0.1</b> / <b>localhost</b> sur ce Mac.</p>
      </div>
    @endif

    @if (count($threads) > 3)
      <input type="search" id="thread-q" placeholder="Rechercher une conversation" aria-label="Rechercher une conversation" style="margin:12px 0">
    @endif

    <div class="list threads" id="threads">
      @forelse ($threads as $t)
        @php
          $last = $t['last'];
        @endphp
        <a class="li {{ $t['unread'] ? 'unread' : '' }}" href="{{ route('messages.show', $t['partner']['id']) }}" data-name="{{ \Illuminate\Support\Str::lower($t['partner']['name']) }}">
          <x-av :name="$t['partner']['name']" :sport="$t['partner']['sport']" />
          <span class="grow" style="min-width:0">
            <span class="t">{{ $t['partner']['name'] }} @if ($t['partner']['verified'])<x-ver />@endif</span>
            <span class="s">
              @if ($last)
                @if ($last['mine'])<span class="tick {{ $last['read'] ? 'read' : '' }}">{{ $last['read'] ? '✓✓' : '✓' }}</span>@endif
                @switch($last['kind'])
                  @case('voice') <span class="kicon">🎤</span> Message vocal{{ $last['duration'] ? ' · '.gmdate('i:s', $last['duration']) : '' }} @break
                  @case('image') <span class="kicon">📷</span> Photo @break
                  @case('call') <span class="kicon">{{ ($last['call']['kind'] ?? '') === 'video' ? '🎥' : '📞' }}</span> {{ $last['text'] }} @break
                  @default {{ \Illuminate\Support\Str::limit($last['text'], 70) }}
                @endswitch
              @else
                Nouvelle conversation
              @endif
            </span>
          </span>
          <span class="tright">
            <span class="meta">{{ $last ? ($last['day'] === now()->format('Y-m-d') ? $last['at'] : \Illuminate\Support\Carbon::parse($last['day'])->format('d/m')) : '' }}</span>
            @if ($t['unread'])<span class="badge">{{ $t['unread'] }}</span>@endif
          </span>
        </a>
      @empty
        <p class="meta" style="padding:24px 0">Aucun message pour l'instant. Ouvre le profil d'un sportif ou d'un recruteur pour lui écrire.</p>
      @endforelse
    </div>
    <p class="note" style="margin-top:20px">Rappel de sécurité : un vrai club ne demande jamais d'argent pour un essai, un agent ou un visa. Les appels ne sont possibles qu'avec une personne qui t'a déjà répondu, et jamais avec un sportif mineur.</p>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var q = document.getElementById('thread-q');
  if (q) q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase();
    document.querySelectorAll('#threads .li').forEach(function (a) { a.hidden = v && a.dataset.name.indexOf(v) === -1; });
  });
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.dataset.copy;
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { Terrain.toast('Lien copié'); }, function () { Terrain.toast(t); });
    });
  });
  // La liste se met à jour toute seule quand un message arrive
  var last = {{ (int) ($unread ?? 0) }};
  document.addEventListener('terrain:unread', function (e) { if (e.detail !== last) location.reload(); });
})();
</script>
@endpush

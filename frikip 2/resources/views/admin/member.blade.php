@extends('layouts.admin')
@section('title', ($m->name ?: $m->user_key).' – Admin')
@section('content')
@php
  $rules = \App\Terrain\Growth\Algorithm::RULES;
  $labels = \App\Terrain\Growth\Algorithm::SETTINGS;
  $active = $strikes->whereNull('revoked_at')->count();
  $fmt = fn ($v) => number_format($v, 0, ',', ' ');
@endphp
<p class="meta" style="margin:0 0 6px"><a href="{{ route('admin.members') }}">Membres</a></p>
@include('admin._nav', ['title' => $m->name ?: $m->user_key, 'lead' => ($m->role === 'recruiter' ? 'Recruteur' : 'Sportif').' · '.($m->country ?: 'pays inconnu').' · inscrit '.\App\Http\Controllers\AdminSupervisionController::since($m->created_at).' · '.($m->email ?: $m->phone ?: 'sans contact')])

<div class="adm-grid">
  <section class="panel">
    <h2>État du compte</h2>
    <div class="state">
      @if ($sanction && $sanction->type === 'block_account')
        <span class="pill danger">Bloqué</span><span>{{ $rules[$sanction->rule] ?? $sanction->reason }}</span>
      @elseif ($sanction)
        <span class="pill warn">Suspendu jusqu’au {{ \Illuminate\Support\Carbon::parse($sanction->until)->format('d/m/Y') }}</span><span>{{ $rules[$sanction->rule] ?? $sanction->reason }}</span>
      @else
        <span class="pill ok">Actif</span>
      @endif
      @if ($verified)<span class="pill ok">Identité vérifiée</span>@endif
      <span class="pill {{ $active ? 'warn' : '' }}">{{ $active }} avertissement(s) en cours</span>
    </div>
    <form method="POST" action="{{ route('admin.member.action', $m->user_key) }}" class="actbox">@csrf
      <label>Règle enfreinte<select name="rule">@foreach ($rules as $k => $l)@if ($k !== 'signalements')<option value="{{ $k }}">{{ $l }}</option>@endif @endforeach</select></label>
      <label>Note (visible dans le journal)<input name="note" maxlength="500" placeholder="Ex. : vidéo de match truquée"></label>
      <label>Durée de la suspension (jours)<input name="days" type="number" min="1" max="365" value="{{ app(\App\Terrain\Growth\Algorithm::class)->get('suspension_days') }}"></label>
      <div class="actbtns">
        <button class="btn sm" name="action" value="warn" type="submit">Avertir</button>
        <button class="btn sm ghost" name="action" value="suspend" type="submit">Suspendre</button>
        <button class="btn sm danger" name="action" value="ban" type="submit" onclick="return confirm('Bloquer définitivement ce compte ?')">Bloquer</button>
        @if ($sanction)<button class="btn sm ghost" name="action" value="unblock" type="submit">Débloquer</button>@endif
        <button class="btn sm ghost" name="action" value="{{ $verified ? 'unverify' : 'verify' }}" type="submit">{{ $verified ? 'Retirer le badge vérifié' : 'Marquer comme vérifié' }}</button>
      </div>
      <p class="meta" style="margin:0">Le membre reçoit un message qui dit quelle règle il a enfreinte. Nudité et mise en danger de mineurs : blocage immédiat.</p>
    </form>
  </section>

  @if ($explain)
  <section class="panel">
    <h2>Score : {{ $explain['score'] }} / 100</h2>
    <p class="meta" style="margin:-4px 0 10px">Calculé avec les poids réglés dans <a href="{{ route('admin.algorithm') }}">Algorithme</a>.@if ($explain['warnings']) {{ $explain['warnings'] }} avertissement(s) retirent des points.@endif</p>
    <table class="explain">
      @foreach ($explain['parts'] as $k => [$v, $w])
        <tr><td>{{ $labels[$k][3] }}</td><td><i><em style="width:{{ round($v * 100) }}%"></em></i></td><td class="num">poids {{ $w }}</td></tr>
      @endforeach
    </table>
    <p style="margin:12px 0 0"><a href="{{ route('athletes.show', $athlete['id']) }}" target="_blank">Voir son profil public</a></p>
  </section>
  @endif

  <section class="panel">
    <h2>Avertissements</h2>
    @forelse ($strikes as $s)
      <div class="line {{ $s->revoked_at ? 'muted' : '' }}">
        <span><b>{{ $rules[$s->rule] ?? $s->rule }}</b><small>{{ ['admin' => 'donné par l’équipe', 'image' => 'analyse d’image', 'signalements' => 'signalements', 'mots' => 'mots interdits'][$s->source] ?? $s->source }} · {{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d/m/Y') }}@if ($s->note) · {{ $s->note }}@endif @if ($s->revoked_at) · retiré @endif</small></span>
        @unless ($s->revoked_at)
          <form method="POST" action="{{ route('admin.member.action', $m->user_key) }}">@csrf<input type="hidden" name="strike" value="{{ $s->id }}"><button class="linkbtn" name="action" value="revoke" type="submit">Retirer</button></form>
        @endunless
      </div>
    @empty
      <p class="meta">Aucun avertissement.</p>
    @endforelse
  </section>

  <section class="panel">
    <h2>Pays</h2>
    <form method="POST" action="{{ route('admin.member.action', $m->user_key) }}" class="row" style="gap:8px">@csrf
      <select name="country" aria-label="Pays">@foreach (\App\Terrain\Region::names() as $c)<option @selected($m->country === $c)>{{ $c }}</option>@endforeach</select>
      <button class="btn sm ghost" name="action" value="country" type="submit">Changer</button>
    </form>
    <h2 style="margin-top:20px">Paiements</h2>
    @forelse ($payments as $p)
      <div class="line"><span><b>{{ \App\Terrain\Growth\Payments::label($p->product) }} · {{ \App\Terrain\Region::format($p->amount, $p->currency) }}</b><small>{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/Y') }} · {{ $p->reference }}@if ($p->provider === 'simulation') · simulation @endif</small></span><span class="pill {{ $p->status === 'accepted' ? 'ok' : '' }}">{{ ['accepted' => 'Payé', 'pending' => 'En attente', 'refused' => 'Refusé', 'expired' => 'Abandonné'][$p->status] ?? $p->status }}</span></div>
    @empty
      <p class="meta">Aucun paiement.</p>
    @endforelse
  </section>

  @if ($videos)
  <section class="panel wide">
    <h2>Vidéos publiées</h2>
    <div class="vgrid">
      @foreach ($videos as $v)
        <div class="vbox"><a class="vcard" href="{{ route('feed', ['v' => $v->id]) }}" target="_blank">
          @if ($v->poster)<img src="{{ $v->poster }}" alt="" loading="lazy">@else<span class="vph"></span>@endif
          <span>{{ $v->title }}</span>
          @if (isset($hidden[$v->id]))<em class="pill {{ $hidden[$v->id] === 'pending_video' ? 'warn' : 'danger' }}">{{ $hidden[$v->id] === 'pending_video' ? 'À valider' : 'Masquée' }}</em>@endif
        </a>
        <form method="POST" action="{{ route('admin.videos.delete', $v->id) }}" onsubmit="return confirm('Supprimer définitivement cette vidéo ?')">@csrf<button class="linkbtn" type="submit">Supprimer</button></form></div>
      @endforeach
    </div>
  </section>
  @endif

  <section class="panel">
    <h2>Modifier le profil</h2>
    <form method="POST" action="{{ route('admin.member.manage', $m->user_key) }}" class="adform">@csrf<input type="hidden" name="do" value="edit">
      <div class="formgrid">
        <label class="wide">Nom<input name="name" value="{{ $m->name }}" required maxlength="120"></label>
        <label>E-mail<input name="email" type="email" value="{{ $m->email }}"></label>
        <label>Téléphone<input name="phone" value="{{ $m->phone }}" maxlength="30"></label>
        @if ($m->athlete_id)
          <label>Sport<select name="sport"><option value="">–</option>@foreach (\App\Terrain\SportFields::sports() as $sp)<option @selected(($athlete['sport'] ?? null) === $sp)>{{ $sp }}</option>@endforeach</select></label>
          <label>Ville<input name="city" value="{{ $athlete['city'] ?? '' }}" maxlength="120"></label>
        @endif
      </div>
      <div class="formbar"><button class="btn sm" type="submit">Enregistrer</button></div>
    </form>
    <h2 style="margin-top:18px">Nouveau mot de passe</h2>
    <form method="POST" action="{{ route('admin.member.manage', $m->user_key) }}" class="formbar">@csrf<input type="hidden" name="do" value="password">
      <input name="new_password" type="text" minlength="8" placeholder="8 caractères minimum" required style="flex:1"><button class="btn ghost sm" type="submit">Changer</button></form>
  </section>
  <section class="panel">
    <h2>Offrir une option</h2>
    <form method="POST" action="{{ route('admin.member.manage', $m->user_key) }}" class="formbar">@csrf<input type="hidden" name="do" value="gift">
      <select name="product" style="width:auto">@foreach (['premium' => 'Premium', 'boost' => 'Boost', 'structure' => 'Structure Pro', 'sans_pub' => 'Sans pub'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
      <input name="days" type="number" min="1" max="365" value="30" style="width:90px" aria-label="Jours"> jours
      <button class="btn ghost sm" type="submit">Offrir</button></form>
    <h2 style="margin-top:18px">Supprimer le compte</h2>
    <p class="meta" style="margin-top:-6px">Supprime le compte, le profil, les vidéos, les messages et les commentaires. Irréversible.</p>
    <form method="POST" action="{{ route('admin.member.manage', $m->user_key) }}" class="formbar" onsubmit="return confirm('Supprimer définitivement ce compte ?')">@csrf<input type="hidden" name="do" value="delete">
      <input name="confirm" placeholder="Écris SUPPRIMER" required pattern="SUPPRIMER" style="flex:1"><button class="btn sm danger" type="submit">Supprimer</button></form>
  </section>

  <section class="panel wide">
    <h2>Historique</h2>
    @foreach ($history as $h)
      <div class="line"><span><b>{{ ['hide_video' => 'Vidéo masquée', 'pending_video' => 'Vidéo en attente de validation', 'block_account' => 'Compte bloqué', 'suspend_account' => 'Compte suspendu'][$h->type] ?? $h->type }}</b><small>{{ $h->reason }} · {{ \Illuminate\Support\Carbon::parse($h->created_at)->format('d/m/Y H:i') }} · {{ $h->active ? 'en cours' : 'levée' }}</small></span></div>
    @endforeach
    @foreach ($reports as $r)
      <div class="line"><span><b>Signalé par un membre</b><small>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($r->reason, 140) }}</small></span></div>
    @endforeach
    @foreach ($events as $e)
      <div class="line"><span><b>{{ ['texte_refuse' => 'Texte refusé', 'image' => 'Image bloquée', 'signalements' => 'Vidéo retirée après signalements', 'validation' => 'Vidéo en attente de validation'][$e->kind] ?? $e->kind }}</b><small>{{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d/m/Y') }}@if ($e->rule) · {{ $rules[$e->rule] ?? $e->rule }}@endif @if ($e->excerpt) · « {{ $e->excerpt }} »@endif</small></span></div>
    @endforeach
    @if (! $history->count() && ! $reports->count() && ! $events->count())<p class="meta">Rien à signaler.</p>@endif
  </section>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Modération – Admin')
@section('content')
@php
  $rules = \App\Terrain\Growth\Algorithm::RULES;
  $kinds = ['texte_refuse' => 'Texte refusé à l’envoi', 'image' => 'Image bloquée par l’analyse', 'signalements' => 'Vidéo retirée après signalements', 'validation' => 'Vidéo d’un nouveau compte à valider'];
  $types = ['hide_video' => 'Vidéo masquée', 'pending_video' => 'Vidéo à valider', 'block_account' => 'Compte bloqué', 'suspend_account' => 'Compte suspendu'];
@endphp
@include('admin._nav', ['title' => 'Modération', 'lead' => 'Les contenus signalés par les membres et ceux que l’algorithme a arrêtés. Vous avez toujours le dernier mot.'])

<nav class="tabs" aria-label="Vues">
  @foreach (['a_traiter' => 'À traiter', 'refus' => 'Textes refusés', 'decisions' => 'Décisions en cours', 'historique' => 'Historique'] as $k => $l)
    <a href="{{ route('admin.moderation', ['vue' => $k]) }}" aria-current="{{ $tab === $k ? 'page' : 'false' }}">{{ $l }}@isset($counts[$k]) <b>{{ $counts[$k] }}</b>@endisset</a>
  @endforeach
</nav>

@if ($tab === 'decisions')
  <form method="POST" action="{{ route('admin.moderate') }}" class="adm-filters">@csrf
    <select name="type" aria-label="Type"><option value="hide_video">Masquer une vidéo</option><option value="block_account">Bloquer un compte</option></select>
    <input name="target" placeholder="Id de la vidéo ou du compte" required aria-label="Id">
    <input name="reason" placeholder="Motif" aria-label="Motif">
    <button class="btn sm" type="submit">Appliquer</button>
  </form>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Décision</th><th>Cible</th><th>Motif</th><th>Depuis</th><th></th></tr></thead>
    <tbody>
    @forelse ($decisions as $d)
      <tr><td>{{ $types[$d->type] ?? $d->type }}@if ($d->until)<br><span class="meta">jusqu’au {{ \Illuminate\Support\Carbon::parse($d->until)->format('d/m/Y') }}</span>@endif</td>
        <td>{{ $d->target_id }}</td><td class="meta">{{ $rules[$d->rule] ?? $d->reason }}</td><td>{{ \Illuminate\Support\Carbon::parse($d->created_at)->format('d/m/Y') }}</td>
        <td><form method="POST" action="{{ route('admin.lift', $d->id) }}">@csrf<button class="btn ghost sm" type="submit">Lever</button></form></td></tr>
    @empty
      <tr><td colspan="5" class="meta">Aucune décision en cours.</td></tr>
    @endforelse
    </tbody></table></div>
@else
  <div class="mqueue">
    @foreach ($events as $e)
      @php
        $v = $e->target_type === 'video' ? ($videos[$e->target_id] ?? null) : null;
      @endphp
      <article class="mcard">
        @if ($v)
          <a class="mthumb" href="{{ route('feed', ['v' => $e->target_id]) }}" target="_blank">@if ($v['poster'])<img src="{{ $v['poster'] }}" alt="" loading="lazy">@else<span class="vph"></span>@endif</a>
        @endif
        <div class="mbody">
          <span class="mkind">{{ $kinds[$e->kind] ?? $e->kind }}@if ($e->score !== null && $e->kind === 'image') · score {{ round($e->score, 2) }}@endif @if ($e->kind === 'signalements') · {{ (int) $e->score }} signalements @endif</span>
          <b>@if ($e->rule){{ $rules[$e->rule] ?? $e->rule }}@else Contenu à vérifier @endif</b>
          @if ($v)<span>{{ $v['title'] }}</span>@endif
          @if ($e->excerpt)<q>{{ $e->excerpt }}</q>@endif
          <small>
            @if ($e->user_key)<a href="{{ route('admin.member', $e->user_key) }}">{{ $names[$e->user_key] ?? $e->user_key }}</a> · @endif
            {{ ['video' => 'vidéo', 'comment' => 'commentaire', 'message' => 'message', 'profile' => 'profil', 'offer' => 'offre', 'club' => 'page club', 'event' => 'détection', 'story' => 'témoignage'][$e->target_type] ?? $e->target_type }}
            · {{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d/m/Y H:i') }}
            @if ($e->status !== 'a_traiter') · <b>{{ ['approuve' => 'remis en ligne', 'confirme' => 'retiré'][$e->status] ?? $e->status }}</b>@endif
          </small>
        </div>
        @if ($e->status === 'a_traiter')
          <form method="POST" action="{{ route('admin.moderation.decide', $e->id) }}" class="mact">@csrf
            <select name="rule" aria-label="Règle">@foreach ($rules as $k => $l)@if ($k !== 'signalements')<option value="{{ $k }}" @selected($e->rule === $k)>{{ $l }}</option>@endif @endforeach</select>
            <button class="btn sm" name="decision" value="approve" type="submit">{{ $e->target_type === 'video' ? 'Remettre en ligne' : 'Rien à reprocher' }}</button>
            <button class="btn sm ghost" name="decision" value="confirm" type="submit">Retirer et avertir</button>
            @if ($e->user_key)<button class="btn sm danger" name="decision" value="ban" type="submit" onclick="return confirm('Retirer le contenu et bloquer l’auteur ?')">Bloquer l’auteur</button>@endif
          </form>
        @endif
      </article>
    @endforeach

    @foreach ($reports as $r)
      @php
        $v = $r->target_type === 'video' ? ($videos[$r->target_id] ?? null) : null;
      @endphp
      <article class="mcard">
        @if ($v)<a class="mthumb" href="{{ route('feed', ['v' => $r->target_id]) }}" target="_blank">@if ($v['poster'])<img src="{{ $v['poster'] }}" alt="" loading="lazy">@else<span class="vph"></span>@endif</a>@endif
        <div class="mbody">
          <span class="mkind">Signalement d’un membre · {{ ['video' => 'vidéo', 'profile' => 'profil', 'message' => 'conversation', 'app' => 'général'][$r->target_type] ?? $r->target_type }}</span>
          <b>{{ $v['title'] ?? ($r->target_id ?: 'Sans cible') }}</b>
          <q>{{ $r->reason }}</q>
          <small>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}@if (in_array($r->target_type, ['profile', 'message']) && $r->target_id) · <a href="{{ route('athletes.show', $r->target_id) }}" target="_blank">voir le profil</a>@endif</small>
        </div>
        <form method="POST" action="{{ route('admin.reports.handle', $r->id) }}" class="mact">@csrf
          <input type="hidden" name="target" value="{{ $r->target_id }}">
          <button class="btn sm" name="action" value="dismiss" type="submit">Classer sans suite</button>
          @if ($r->target_type === 'video')<button class="btn sm ghost" name="action" value="hide_video" type="submit">Masquer la vidéo</button>@endif
          @if (in_array($r->target_type, ['profile', 'message']))<button class="btn sm danger" name="action" value="block_account" type="submit" onclick="return confirm('Bloquer ce compte ?')">Bloquer le compte</button>@endif
        </form>
      </article>
    @endforeach

    @if (! $events->count() && ! $reports->count())
      <div class="empty"><b>Rien à traiter.</b><span>Les signalements des membres et les contenus arrêtés par l’algorithme apparaîtront ici.</span></div>
    @endif
  </div>
@endif
@endsection

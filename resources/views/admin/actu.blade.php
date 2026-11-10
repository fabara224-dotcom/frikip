@extends('layouts.admin')
@section('title', 'Actu – Admin')
@section('content')
@include('admin._nav', ['title' => 'Actu et stories', 'lead' => 'Publications, commentaires et stories des membres. « À vérifier » regroupe ce qui a été signalé ; à 3 signalements, le contenu est masqué automatiquement en attendant ta décision.'])

<nav class="tabs" aria-label="Actu">
  @foreach (\App\Http\Controllers\AdminActuController::TABS as $k => $l)<a href="{{ route('admin.actu', ['onglet' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }} <b>{{ $counts[$k] }}</b></a>@endforeach
</nav>
<form class="adm-filters" method="GET" action="{{ route('admin.actu') }}">
  <input type="hidden" name="onglet" value="{{ $tab }}">
  <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher un texte ou un auteur…" aria-label="Rechercher">
  <button class="btn" type="submit">Rechercher</button>
</form>

@php $date = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y H:i'); @endphp

@if ($posts->isEmpty() && $stories->isEmpty() && $comments->isEmpty())
  <div class="empty"><b>Rien ici.</b><span>{{ $q !== '' ? 'Aucun résultat pour « '.$q.' ».' : ($tab === 'a_verifier' ? 'Aucun contenu signalé. Tout va bien.' : 'Les contenus des membres apparaîtront ici.') }}</span></div>
@endif

@if ($posts->isNotEmpty())
  @if ($tab === 'a_verifier')<h2 class="adm-h2">Publications signalées</h2>@endif
  <div class="admin-tbl"><table>
    <thead><tr><th>Publication</th><th>Auteur</th><th>État</th><th></th></tr></thead><tbody>
    @foreach ($posts as $p)
      <tr>
        <td>
          @if ($p->photo)<a href="{{ asset($p->photo) }}" target="_blank" rel="noopener"><img src="{{ asset($p->photo) }}" alt="Photo" style="width:72px;height:72px;object-fit:cover;border-radius:8px;float:left;margin-right:10px"></a>@endif
          @if ($p->body)<q>{{ \Illuminate\Support\Str::limit($p->body, 220) }}</q>@else<span class="meta">(photo seule)</span>@endif
          <span class="meta" style="display:block">{{ $date($p->created_at) }} · ❤ {{ $p->likes }} · 💬 {{ $p->comments }}</span>
        </td>
        <td><a href="{{ route('admin.member', $p->user_key) }}">{{ $p->author_name }}</a></td>
        <td>
          @if ($p->hidden)<span class="badge late">Masquée</span>@else<span class="pill ok">En ligne</span>@endif
          @if ($p->reports)<span class="badge verification">{{ $p->reports }} signalement{{ $p->reports > 1 ? 's' : '' }}</span>@endif
        </td>
        <td><div class="actbtns">
          @unless ($p->hidden)<a class="btn ghost sm" href="{{ route('posts.show', $p->id) }}" target="_blank" rel="noopener">Voir</a>@endunless
          @if ($p->hidden || $p->reports)
            <form method="POST" action="{{ route('admin.actu.post', $p->id) }}">@csrf<input type="hidden" name="do" value="garder"><button class="btn ghost sm" type="submit">Garder</button></form>
          @endif
          @unless ($p->hidden)
            <form method="POST" action="{{ route('admin.actu.post', $p->id) }}">@csrf<input type="hidden" name="do" value="masquer"><button class="btn ghost sm" type="submit">Masquer</button></form>
          @endunless
          <form method="POST" action="{{ route('admin.actu.post', $p->id) }}" onsubmit="return confirm('Supprimer définitivement cette publication ?')">@csrf<input type="hidden" name="do" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
        </div></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
@endif

@if ($stories->isNotEmpty())
  @if ($tab === 'a_verifier')<h2 class="adm-h2">Stories signalées</h2>@endif
  <div class="admin-tbl"><table>
    <thead><tr><th>Story</th><th>Auteur</th><th>État</th><th></th></tr></thead><tbody>
    @foreach ($stories as $s)
      <tr>
        <td>
          <a href="{{ asset($s->media) }}" target="_blank" rel="noopener">
            @if ($s->media_type === 'video')<video src="{{ asset($s->media) }}" muted preload="metadata" style="width:60px;height:96px;object-fit:cover;border-radius:8px;float:left;margin-right:10px;background:#000"></video>
            @else<img src="{{ asset($s->media) }}" alt="Story" style="width:60px;height:96px;object-fit:cover;border-radius:8px;float:left;margin-right:10px">@endif
          </a>
          {{ $s->caption ?: '(sans texte)' }}
          <span class="meta" style="display:block">{{ $s->media_type === 'video' ? 'Vidéo' : 'Photo' }} · {{ $date($s->created_at) }} · {{ $s->views }} vue{{ $s->views > 1 ? 's' : '' }} · disparaît le {{ $date($s->expires_at) }}</span>
        </td>
        <td><a href="{{ route('admin.member', $s->user_key) }}">{{ $s->author_name }}</a></td>
        <td>
          @if ($s->hidden)<span class="badge late">Masquée</span>@else<span class="pill ok">En ligne</span>@endif
          @if ($s->reports)<span class="badge verification">{{ $s->reports }} signalement{{ $s->reports > 1 ? 's' : '' }}</span>@endif
        </td>
        <td><div class="actbtns">
          @if ($s->hidden || $s->reports)
            <form method="POST" action="{{ route('admin.actu.story', $s->id) }}">@csrf<input type="hidden" name="do" value="garder"><button class="btn ghost sm" type="submit">Garder</button></form>
          @endif
          <form method="POST" action="{{ route('admin.actu.story', $s->id) }}" onsubmit="return confirm('Supprimer cette story ?')">@csrf<input type="hidden" name="do" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
        </div></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
@endif

@if ($comments->isNotEmpty())
  <div class="admin-tbl"><table>
    <thead><tr><th>Commentaire</th><th>Auteur</th><th>Date</th><th></th></tr></thead><tbody>
    @foreach ($comments as $c)
      <tr><td><q>{{ \Illuminate\Support\Str::limit($c->body, 200) }}</q><span class="meta" style="display:block"><a href="{{ route('posts.show', $c->post_id) }}#commentaires" target="_blank" rel="noopener">publication {{ $c->post_id }}</a></span></td>
        <td><a href="{{ route('admin.member', $c->user_key) }}">{{ $c->author_name }}</a></td>
        <td><span class="meta">{{ $date($c->created_at) }}</span></td>
        <td><form method="POST" action="{{ route('admin.actu.comment', $c->id) }}" onsubmit="return confirm('Supprimer ce commentaire ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></td></tr>
    @endforeach
    </tbody>
  </table></div>
@endif
@endsection

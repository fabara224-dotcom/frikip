@extends('layouts.admin')
@section('title', 'Vérifications – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Vérification des comptes'])
  <p class="meta">Compare le nom et la photo du document avec le profil. Pour un mineur, l'accord et la pièce du tuteur sont obligatoires. Les documents restent privés.</p>
  <nav class="filters" aria-label="Filtrer">
    @foreach (['en_attente' => 'En attente', 'validee' => 'Validées', 'refusee' => 'Refusées', 'toutes' => 'Toutes'] as $k => $l)
      <a href="{{ route('admin.verifications', ['statut' => $k]) }}" aria-current="{{ $status === $k ? 'true' : 'false' }}">{{ $l }}</a>
    @endforeach
  </nav>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>N°</th><th>Compte</th><th>Pièces</th><th>Tuteur (mineurs)</th><th>Décision</th></tr></thead>
    <tbody>
    @forelse ($rows as $v)
      <tr>
        <td>{{ $v->id }}<br><span class="meta">{{ \Illuminate\Support\Carbon::parse($v->created_at)->format('d/m') }}</span></td>
        <td><b>{{ $v->full_name }}</b><br><span class="meta">{{ $v->role === 'recruiter' ? 'Recruteur' : 'Sportif' }} · {{ $v->user_key }}</span>
          @if ($v->organization)<br>{{ $v->organization }}@endif @if ($v->licence_number)<br><span class="meta">Licence : {{ $v->licence_number }}</span>@endif</td>
        <td>{{ $v->document_type }}@if ($v->document_path)<br><a href="{{ route('admin.file', [$v->id, 'piece']) }}" target="_blank">Ouvrir la pièce</a>@endif</td>
        <td>@if ($v->guardian_name){{ $v->guardian_name }} · {{ $v->guardian_phone }}<br>{{ $v->guardian_consent ? '✓ accord donné' : '✕ pas d’accord' }}
            @if ($v->guardian_document_path)<br><a href="{{ route('admin.file', [$v->id, 'tuteur']) }}" target="_blank">Pièce du tuteur</a>@endif @else – @endif</td>
        <td>
          <span class="badge {{ $v->status }}">{{ ['en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée'][$v->status] }}</span>
          <form method="POST" action="{{ route('admin.verifications.decide', $v->id) }}" style="display:flex;flex-direction:column;gap:6px;margin-top:6px">@csrf
            <input name="note" value="{{ $v->admin_note }}" placeholder="Message au membre (si refus)" aria-label="Message">
            <div class="row" style="gap:6px"><button class="btn sm" name="status" value="validee">Valider</button><button class="btn ghost sm" name="status" value="refusee">Refuser</button></div>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="meta">Aucune demande.</td></tr>
    @endforelse
    </tbody></table></div>
</div></div>
@endsection

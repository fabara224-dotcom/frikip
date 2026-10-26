@extends('layouts.admin')

@section('title', 'Demandes de suppression – '.config('terrain.app_name'))
@section('noindex', '1')

@section('content')
<div class="doc gut">
  <div class="wrap">
    @include('admin._nav', ['title' => 'Demandes de suppression'])
    <p class="meta">Délai promis aux utilisateurs : 7 jours. Pour supprimer un compte : Supabase &gt; Authentication &gt; Users &gt; Delete user, puis passez la demande en « Terminée ».</p>


    <nav class="filters" aria-label="Filtrer par statut">
      <a href="{{ route('admin.deletions') }}" aria-current="{{ $status ? 'false' : 'true' }}">Toutes ({{ $counts->sum() }})</a>
      @foreach (\App\Models\DeletionRequest::STATUSES as $key => $label)
        <a href="{{ route('admin.deletions', ['statut' => $key]) }}" aria-current="{{ $status === $key ? 'true' : 'false' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
      @endforeach
    </nav>

    <div class="tbl admin-tbl">
      <table>
        <thead><tr><th>N°</th><th>Reçue</th><th>Contact</th><th>Demande</th><th>Statut</th><th>Mettre à jour</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td style="white-space:nowrap">{{ $r->created_at->format('d/m/Y H:i') }}
              @if ($r->isLate())<br><span class="badge late">En retard</span>@endif</td>
            <td><b>{{ $r->contact }}</b>@if ($r->profile_name)<br><span class="meta">{{ $r->profile_name }}</span>@endif</td>
            <td>{{ $r->targetLabel() }}@if ($r->note)<br><span class="meta">{{ $r->note }}</span>@endif</td>
            <td><span class="badge {{ $r->status }}">{{ $r->statusLabel() }}</span>
              @if ($r->done_at)<br><span class="meta">{{ $r->done_at->format('d/m/Y') }}</span>@endif</td>
            <td>
              <form method="POST" action="{{ route('admin.update', $r) }}">
                @csrf
                <select name="status" aria-label="Statut de la demande {{ $r->id }}">
                  @foreach (\App\Models\DeletionRequest::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>
                  @endforeach
                </select>
                <input name="admin_note" type="text" value="{{ $r->admin_note }}" placeholder="Note interne" aria-label="Note interne">
                <button class="btn ghost" type="submit">Enregistrer</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="meta">Aucune demande pour l'instant.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="pager">{{ $requests->links('pagination::simple-default') }}</div>
  </div>
</div>
@endsection

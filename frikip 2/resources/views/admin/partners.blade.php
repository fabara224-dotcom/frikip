@extends('layouts.admin')
@section('title', 'Partenaires – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Demandes de partenariat'])
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Reçue</th><th>Structure</th><th>Contact</th><th>Message</th><th>Suivi</th></tr></thead>
    <tbody>
    @forelse ($rows as $p)
      <tr><td>{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/Y') }}</td>
        <td><b>{{ $p->organization }}</b><br><span class="meta">{{ $p->type }}</span></td>
        <td>{{ $p->contact_name }}<br><span class="meta">{{ $p->phone }} {{ $p->email }}</span></td>
        <td style="max-width:340px">{{ $p->message }}</td>
        <td><form method="POST" action="{{ route('admin.partners.decide', $p->id) }}" class="row" style="gap:6px">@csrf
          <select name="status" aria-label="Statut">@foreach (['nouvelle' => 'Nouvelle', 'en_discussion' => 'En discussion', 'signee' => 'Signée', 'refusee' => 'Refusée'] as $k => $l)<option value="{{ $k }}" @selected($p->status === $k)>{{ $l }}</option>@endforeach</select>
          <button class="btn ghost sm">OK</button></form></td></tr>
    @empty
      <tr><td colspan="5" class="meta">Aucune demande.</td></tr>
    @endforelse
    </tbody></table></div>
</div></div>
@endsection

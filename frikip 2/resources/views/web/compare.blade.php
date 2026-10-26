@extends('layouts.web')

@section('title', 'Comparaison – '.config('terrain.app_name'))

@php
    $rows = [
        'Sport' => fn ($x) => $x['sport'], 'Âge' => fn ($x) => $x['age'].' ans', 'Taille' => fn ($x) => $x['height'],
        'Poids' => fn ($x) => $x['weight'], 'Pays' => fn ($x) => $x['country'], 'Niveau' => fn ($x) => $x['level'],
        'Club' => fn ($x) => $x['club'], 'Disponible' => fn ($x) => $x['availability'], 'Vérifié' => fn ($x) => $x['verified'] ? 'Oui' : 'Non',
        'Vues totales' => fn ($x) => number_format(array_sum(array_column($x['videos'], 'views')), 0, ',', ' '),
    ];
    $same = $a['sport'] === $b['sport'];
    $keys = $same ? array_unique(array_merge(array_keys($a['fields']), array_keys($b['fields']))) : [];
@endphp

@section('content')
<div class="page gut">
  <a href="{{ url()->previous() }}">← Retour</a>
  <h1>Comparaison</h1>
  <div class="tbl"><table>
    <thead><tr><th></th>
      @foreach ([$a, $b] as $x)
        <th style="text-transform:none;font-family:var(--f-body);font-size:15px;color:var(--ink)"><a href="{{ route('athletes.show', $x['id']) }}" style="display:flex;gap:8px;align-items:center;color:inherit"><x-av :name="$x['name']" :sport="$x['sport']" />{{ $x['name'] }}</a></th>
      @endforeach
    </tr></thead>
    <tbody>
      @foreach ($rows as $label => $f)
        <tr><td class="meta">{{ $label }}</td><td>{{ $f($a) }}</td><td>{{ $f($b) }}</td></tr>
      @endforeach
      @if ($same)
        <tr><td colspan="3"><b>Fiche {{ mb_strtolower($a['sport']) }}</b></td></tr>
        @foreach ($keys as $k)
          <tr><td class="meta">{{ $k }}</td><td>{{ $a['fields'][$k] ?? '–' }}</td><td>{{ $b['fields'][$k] ?? '–' }}</td></tr>
        @endforeach
      @else
        <tr><td colspan="3" class="meta">Sports différents : seule la base commune est comparée.</td></tr>
      @endif
    </tbody>
  </table></div>
</div>
@endsection

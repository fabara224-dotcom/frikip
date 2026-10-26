<div class="tbl"><table class="stand">
  <thead><tr><th>#</th><th>Équipe</th><th>J</th><th>G</th><th>N</th><th>P</th><th>Diff</th><th>Pts</th></tr></thead>
  <tbody>
  @foreach ($rows as $i => $r)
    <tr class="{{ $r['live'] ? 'lv' : '' }}">
      <td>{{ $i + 1 }}</td>
      <td><span class="team">@if (! empty($r['logo']))<img src="{{ $r['logo'] }}" alt="" width="18" height="18" loading="lazy" onerror="this.remove()">@endif{{ $r['team'] }}</span> @if ($r['live'])<span class="live" style="font-size:10px">LIVE</span>@endif</td>
      <td>{{ $r['p'] }}</td><td>{{ $r['w'] }}</td><td>{{ $r['d'] }}</td><td>{{ $r['l'] }}</td>
      <td>{{ $r['diff'] > 0 ? '+' : '' }}{{ $r['diff'] }}</td><td>{{ $r['pts'] }}</td>
    </tr>
  @endforeach
  </tbody>
</table></div>

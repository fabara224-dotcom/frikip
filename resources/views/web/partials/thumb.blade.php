@php($c = \App\Terrain\SportFields::color($a['sport']))
<a class="thumb" href="{{ route('feed', ['v' => $v['id']]) }}" style="background:@if (! empty($v['poster']))linear-gradient(180deg,rgba(0,0,0,.05) 40%,rgba(0,0,0,.65)),url('{{ $v['poster'] }}') center/cover,@endif linear-gradient(160deg,{{ $c[0] }},{{ $c[1] }})">
  <span class="play"><i><svg viewBox="0 0 24 24"><path d="M6 4l14 8-14 8z"/></svg></i></span>
  <span class="lbl">{{ \Illuminate\Support\Str::before(\App\Terrain\DataLang::video($v, 'title'), ' – ') }}</span>
  <span class="meta">{{ $v['duration'] }} · {{ number_format($v['views'] ?? 0, 0, ',', ' ') }} {{ ($v['views'] ?? 0) > 1 ? __('vues') : __('vue') }} · ♥ {{ $v['likes'] ?? 0 }}@if (! empty($v['tracks'])) · CC @endif</span>
</a>

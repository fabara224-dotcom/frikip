<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('CV sportif') }} – {{ $a['name'] }}</title>
<meta property="og:title" content="{{ __('CV sportif') }} – {{ $a['name'] }}">
<meta property="og:image" content="{{ route('athletes.card.png', $a['id']) }}">
<style>
  :root{--ink:#14201A;--muted:#5B6660;--line:#DDE3DF;--accent:#1F7A4A}
  *{box-sizing:border-box} body{margin:0;font-family:Barlow,Arial,sans-serif;color:var(--ink);background:#EEF1EF}
  .sheet{max-width:794px;margin:20px auto;background:#fff;padding:44px 48px;box-shadow:0 2px 14px rgba(0,0,0,.08)}
  header{display:flex;justify-content:space-between;gap:20px;border-bottom:3px solid var(--accent);padding-bottom:16px}
  h1{font-size:34px;margin:0;text-transform:uppercase;letter-spacing:.5px} h2{font-size:15px;text-transform:uppercase;letter-spacing:1.5px;color:var(--accent);margin:22px 0 8px}
  .sub{color:var(--muted);margin:4px 0 0} .kv{display:grid;grid-template-columns:repeat(3,1fr);gap:8px} .kv div{border:1px solid var(--line);border-radius:8px;padding:8px 10px}
  .kv span{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.6px} .kv b{font-size:16px}
  table{width:100%;border-collapse:collapse} td{padding:6px 0;border-bottom:1px solid var(--line)} td:last-child{text-align:right;font-weight:700}
  ul{margin:0;padding-left:18px} li{margin:4px 0} .qr{text-align:center;font-size:11px;color:var(--muted)} .qr #qr{width:110px;height:110px;margin-bottom:4px}
  .badges span{display:inline-block;border:1px solid var(--line);border-radius:20px;padding:3px 10px;margin:0 6px 6px 0;font-size:13px}
  .bar{max-width:794px;margin:16px auto 0;display:flex;gap:10px;justify-content:flex-end;padding:0 12px}
  .bar button,.bar a{font:inherit;font-weight:700;border:0;border-radius:10px;padding:10px 16px;background:var(--accent);color:#fff;text-decoration:none;cursor:pointer}
  .bar a{background:#fff;color:var(--ink);border:1px solid var(--line)} footer{margin-top:26px;font-size:11px;color:var(--muted);border-top:1px solid var(--line);padding-top:10px}
  @media print{body{background:#fff}.sheet{box-shadow:none;margin:0;padding:24px}.bar{display:none}}
  @media (max-width:600px){.sheet{padding:22px}.kv{grid-template-columns:1fr 1fr}header{flex-direction:column}}
</style>
</head>
<body>
<div class="bar"><a href="{{ route('athletes.show', $a['id']) }}">{{ __('Voir le profil') }}</a><button type="button" onclick="window.print()">{{ __('Enregistrer en PDF / Imprimer') }}</button></div>
<div class="sheet">
  <header>
    <div>
      <h1>{{ $a['name'] }}</h1>
      <p class="sub">{{ $a['sport'] }} · {{ \Illuminate\Support\Arr::first($a['fields']) }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}, {{ $a['country'] }}</p>
      @if (! empty($a['specialty']))<p class="sub"><b>{{ __('Point fort') }} :</b> {{ $a['specialty'] }}</p>@endif
      @if (count($badges))<div class="badges" style="margin-top:10px">@foreach ($badges as [$ico, $label])<span>{{ $ico }} {{ $label }}</span>@endforeach</div>@endif
    </div>
    <div class="qr"><div id="qr"></div>{{ __('Vidéos et contact') }}<br>{{ preg_replace('#^https?://#', '', $url) }}</div>
  </header>

  <h2>{{ __('Profil') }}</h2>
  <div class="kv">
    @foreach ([__('Taille') => $a['height'], __('Poids') => $a['weight'], __('Niveau') => $a['level'], __('Club actuel') => $a['club'], __('Disponibilité') => $a['availability'], __('Vérifié') => $a['verified'] ? __('Oui') : __('En cours')] as $k => $v)
      <div><span>{{ $k }}</span><b>{{ $v }}</b></div>
    @endforeach
  </div>

  <h2>{{ __('Fiche') }} {{ mb_strtolower($a['sport']) }}</h2>
  <table>@foreach ($a['fields'] as $k => $v)<tr><td>{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach</table>

  <h2>{{ __('Compilations vidéo') }}</h2>
  <ul>@forelse ($a['videos'] as $v)<li>{{ $v['title'] }} — {{ number_format($v['views'], 0, ',', ' ') }} {{ __('vues') }}</li>@empty<li>{{ __('Bientôt disponibles') }}</li>@endforelse</ul>

  <footer>{{ __('CV généré par') }} {{ config('terrain.app_name') }} · {{ now()->format('d/m/Y') }} · {{ __('Contact uniquement via la messagerie de la plateforme (recruteurs vérifiés). Un vrai club ne demande jamais d’argent pour un essai.') }}@if (! empty($a['demo'])) · {{ __('Profil fictif de démonstration') }}@endif</footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>try { new QRCode(document.getElementById('qr'), { text: @json($url), width: 110, height: 110 }); } catch (e) {}</script>
</body>
</html>

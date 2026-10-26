@props(['for' => 'carte'])
@php
  $shop = config('terrain.shop_name', 'Fabara');
  $base = rtrim((string) config('terrain.shop_url', 'https://www.fabara-boutique.com'), '/');
  $c = [
    'carte' => [__('Imprime ta carte de joueur'), __('Une vraie carte rigide et brillante, à donner aux recruteurs et à garder. Télécharge l’image de ta carte, puis commande l’impression.'), __('Commander l’impression')],
    'detection' => [__('Maillots et T-shirts de la détection'), __('Des maillots numérotés et des T-shirts au nom de ta journée de détection, pour les joueurs et l’encadrement.'), __('Commander les maillots')],
    'defi' => [__('Le maillot du défi'), __('T-shirts et maillots personnalisés pour les gagnants du défi et pour ton équipe.'), __('Voir les maillots')],
    'tournoi' => [__('Bâches et banderoles pour tes tournois'), __('Bâches de terrain, banderoles de sponsors et kakémonos imprimés aux couleurs de ton tournoi.'), __('Commander mes bâches')],
  ][$for] ?? null;
  $url = $base.'/?utm_source=frikip&utm_medium=site&utm_campaign='.$for;
@endphp
@if ($c)
<aside class="fabara" aria-label="{{ __('Impression avec :shop', ['shop' => $shop]) }}">
  <span class="fabara-ico" aria-hidden="true"><x-ico :name="['carte' => 'card', 'detection' => 'calendar', 'defi' => 'trophy', 'tournoi' => 'tag'][$for]" /></span>
  <div class="fabara-txt">
    <small>{{ __('Avec notre partenaire impression :shop', ['shop' => $shop]) }}</small>
    <b>{{ $c[0] }}</b>
    <span>{{ $c[1] }}</span>
  </div>
  <a class="btn sm dark" href="{{ $url }}" target="_blank" rel="noopener">{{ $c[2] }}</a>
</aside>
@endif

{{-- Carte « Meilleurs sportifs » au milieu du fil : le top 5 de l'algorithme dans le pays du visiteur. --}}
<section class="tki topslot">
  <div class="vid topcard">
    <div class="tophead">
      <b>⭐ {{ __('Meilleurs sportifs') }}</b>
      <small>{{ __('Le top du moment en :c', ['c' => \App\Terrain\DataLang::t(\App\Terrain\Region::viewer())]) }}</small>
    </div>
    <ol>
      @foreach ($top as $i => $a)
        <li><a href="{{ route('athletes.show', $a['id']) }}">
          <span class="pos">{{ $i + 1 }}</span>
          <x-av :name="$a['name']" :sport="$a['sport']" />
          <span class="who"><b>{{ $a['name'] }}</b><small>@td($a['sport']) · {{ $a['city'] }}</small></span>
          <span class="rk">{{ round($a['rating']) }}</span>
        </a></li>
      @endforeach
    </ol>
    <a class="btn" href="{{ route('rankings') }}">{{ __('Voir tout le classement') }}</a>
  </div>
</section>

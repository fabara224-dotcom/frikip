@extends('layouts.admin')
@section('title', 'Algorithme – Admin')
@section('content')
@include('admin._nav', ['title' => 'Algorithme', 'lead' => 'Chaque réglage s’applique tout de suite à tout le site. Chaque changement est inscrit dans le journal.'])

@php
  $groups = [
      'classement' => ['Classement des sportifs', 'Le score de 0 à 100 de chaque sportif. Plus un poids est élevé, plus ce signal compte. 0 = ignoré.'],
      'fil' => ['Ce que voient les visiteurs', 'L’ordre des vidéos dans le fil de chaque personne.'],
      'moderation' => ['Modération automatique', 'Quand un contenu est retiré et quand un compte est sanctionné. Nudité et mise en danger de mineurs : blocage immédiat, toujours.'],
      'mots' => ['Mots interdits', 'Un mot ou une expression par ligne. Refusés dès l’envoi dans les titres, commentaires, messages, profils et offres. Accents et majuscules ignorés.'],
  ];
@endphp

<div class="algo">
  <nav class="algo-nav" aria-label="Sections">
    @foreach ($groups as $g => [$t])<a href="#{{ $g }}">{{ $t }}</a>@endforeach
    <a href="#tester">Tester un texte</a>
  </nav>

  <div class="algo-main">
    @foreach ($groups as $g => [$title, $help])
      <form method="POST" action="{{ route('admin.algorithm.save') }}" class="panel" id="{{ $g }}">@csrf
        <input type="hidden" name="group" value="{{ $g }}">
        <h2>{{ $title }}</h2>
        <p class="meta" style="margin:-4px 0 14px">{{ $help }}</p>
        @if ($g === 'moderation' && ! $imageReady)
          <p class="note">L’analyse d’image n’est pas encore active : ajoutez <code>SIGHTENGINE_USER</code> et <code>SIGHTENGINE_SECRET</code> dans le fichier <code>.env</code>. En attendant, la protection repose sur les mots interdits, les signalements et la validation des nouveaux comptes.</p>
        @endif
        <div class="{{ $g === 'mots' ? 'words' : 'settings' }}">
          @foreach ($settings as $k => [$default, $type, $group, $label, $aide])
            @continue($group !== $g)
            @php
              $v = $values[$k];
            @endphp
            @if ($type === 'weight')
              <label class="set weight">
                <span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
                <span class="range"><input type="range" name="{{ $k }}" min="0" max="10" step="1" value="{{ $v }}" oninput="this.nextElementSibling.textContent=this.value"><output>{{ $v }}</output></span>
              </label>
            @elseif ($type === 'level')
              <div class="set">
                <span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
                <span class="seg">@foreach (array_keys($levels) as $lv)<label><input type="radio" name="{{ $k }}" value="{{ $lv }}" @checked($v === $lv)><span>{{ ucfirst($lv) }}</span></label>@endforeach</span>
              </div>
            @elseif ($type === 'bool')
              <label class="set">
                <span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
                <span class="switch"><input type="checkbox" name="{{ $k }}" value="1" @checked($v)><i></i></span>
              </label>
            @elseif ($type === 'words')
              <label class="wordbox"><b>{{ $label }}</b><textarea name="{{ $k }}" rows="7" spellcheck="false">{{ $v }}</textarea><small>{{ count(array_filter(preg_split('/\R/u', $v))) }} mot(s) ou expression(s)</small></label>
            @else
              <label class="set">
                <span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
                <input class="numin" type="number" name="{{ $k }}" value="{{ $v }}" min="0" @if ($type === 'float') step="0.05" max="1" @else step="1" @endif>
              </label>
            @endif
          @endforeach
        </div>
        <div class="formbar">
          <button class="btn" type="submit">Enregistrer</button>
          <button class="btn ghost" type="submit" name="reset" value="1" onclick="return confirm('Remettre les valeurs de départ pour cette section ?')">Valeurs de départ</button>
        </div>
      </form>

      @if ($g === 'classement' && $preview)
        <section class="panel">
          <h2>Les 8 meilleurs scores avec ces réglages</h2>
          <ol class="top8">
            @foreach ($preview as $p)
              <li><span>{{ $p['a']['name'] }}<small>{{ $p['a']['sport'] }} · {{ $p['a']['country'] }}@if (! empty($p['a']['demo'])) · profil d’exemple @endif</small></span><b>{{ $p['s'] }}</b></li>
            @endforeach
          </ol>
        </section>
      @endif
    @endforeach

    <form method="GET" action="{{ route('admin.algorithm') }}#tester" class="panel" id="tester">
      <h2>Tester un texte</h2>
      <p class="meta" style="margin:-4px 0 12px">Collez un commentaire ou un titre pour savoir s’il serait accepté.</p>
      <textarea name="tester" rows="3" aria-label="Texte à tester">{{ $test }}</textarea>
      <div class="formbar"><button class="btn ghost" type="submit">Tester</button>
        @if ($test !== '')
          @if ($testResult)<span class="pill danger">Refusé : {{ $rules[$testResult['rule']] }} (« {{ $testResult['word'] }} »)</span>
          @else<span class="pill ok">Accepté</span>@endif
        @endif
      </div>
    </form>
  </div>
</div>
@endsection

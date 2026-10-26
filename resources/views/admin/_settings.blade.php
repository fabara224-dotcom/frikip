{{-- Réglages d'une section (enregistrés avec ceux de l'algorithme et inscrits dans le journal). --}}
<form method="POST" action="{{ route('admin.media.settings') }}" class="panel">@csrf
  <input type="hidden" name="group" value="{{ $group }}">
  <h2>{{ $title }}</h2>
  <div class="settings">
    @foreach ($settings as $k => [$default, $type, $g, $label, $aide])
      @continue($g !== $group)
      @php
        $v = $values[$k];
      @endphp
      @if ($type === 'bool')
        <label class="set"><span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
          <span class="switch"><input type="checkbox" name="{{ $k }}" value="1" @checked($v)><i></i></span></label>
      @elseif ($type === 'text')
        <label class="wordbox"><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif<input name="{{ $k }}" value="{{ $v }}" maxlength="160"></label>
      @elseif ($type === 'url')
        <label class="wordbox"><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif<input name="{{ $k }}" type="url" value="{{ $v }}" maxlength="1000" placeholder="https://" inputmode="url" spellcheck="false"></label>
      @elseif ($type === 'words')
        <label class="wordbox"><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif<textarea name="{{ $k }}" rows="5" spellcheck="false">{{ $v }}</textarea></label>
      @else
        <label class="set"><span><b>{{ $label }}</b>@if ($aide)<small>{{ $aide }}</small>@endif</span>
          <input class="numin" type="number" name="{{ $k }}" value="{{ $v }}" min="0" step="1"></label>
      @endif
    @endforeach
  </div>
  <div class="formbar"><button class="btn" type="submit">Enregistrer</button></div>
</form>

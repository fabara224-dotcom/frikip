{{-- Étape « Qui es-tu ? » : une carte par acteur du foot. --}}
<div class="tcards" role="radiogroup" aria-label="{{ __('Qui es-tu ?') }}">
  @foreach ($types as $k => [$role, $title, $desc, $can, $icon])
    <label class="tcard">
      <input type="radio" name="type" value="{{ $k }}" @checked($type === $k)>
      <span class="ti" aria-hidden="true">{{ $icon }}</span>
      <span class="tt"><b>{{ __($title) }}</b><small>{{ __($desc) }}</small></span>
    </label>
  @endforeach
</div>

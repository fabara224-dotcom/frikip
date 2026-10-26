{{-- Langue parlée, son, titre dans l'autre langue et sous-titres (publication et page « Langue et sous-titres »). --}}
@php
  $vm = $v ?? [];
  $curLang = old('lang', $vm['lang'] ?? 'none');
  $hasAudio = old('has_audio', isset($vm['has_audio']) ? (int) $vm['has_audio'] : 1);
  $pageLang = $orig ?? (app()->getLocale() === 'en' ? 'en' : 'fr');   // langue du titre d'origine
  $otherLang = $pageLang === 'en' ? 'fr' : 'en';
  $otherName = $otherLang === 'en' ? __('en anglais') : __('en français');
  $tracks = $vm['tracks'] ?? [];
@endphp
<fieldset class="langfs">
  <legend><b>{{ __('Son et langue') }}</b></legend>
  <label class="check" for="has_audio"><input type="hidden" name="has_audio" value="0"><input id="has_audio" name="has_audio" type="checkbox" value="1" @checked($hasAudio)> {{ __('La vidéo a du son') }}</label>
  <label for="lang">{{ __('Langue parlée dans la vidéo') }}
    <select id="lang" name="lang">
      <option value="none" @selected($curLang === 'none')>{{ __('Personne ne parle (musique, bruit du match)') }}</option>
      <option value="fr" @selected($curLang === 'fr')>{{ __('Français') }}</option>
      <option value="en" @selected($curLang === 'en')>{{ __('Anglais') }}</option>
    </select></label>
  <p class="meta" style="margin:0">{{ __('Les visiteurs dont la page est dans l’autre langue voient les sous-titres automatiquement. Si la vidéo est dans la langue de la page, pas de sous-titres.') }}</p>

  <label for="title_alt">{{ __('Titre') }} {{ $otherName }} <span class="meta">({{ __('facultatif') }})</span>
    <input id="title_alt" name="title_alt" maxlength="150" value="{{ old('title_alt', $vm['i18n']['title'][$otherLang] ?? '') }}"></label>
  <label for="caption_alt">{{ __('Texte sur la vidéo') }} {{ $otherName }} <span class="meta">({{ __('facultatif') }})</span>
    <input id="caption_alt" name="caption_alt" maxlength="120" value="{{ old('caption_alt', $vm['i18n']['caption'][$otherLang] ?? '') }}"></label>
  @if ($auto ?? false)<p class="meta" style="margin:0">{{ __('Laissé vide, le titre est traduit automatiquement.') }}</p>@endif

  <div class="section-label" style="margin:6px 0 0">{{ __('Sous-titres') }}</div>
  @foreach (\App\Terrain\Growth\Subtitles::LANGS as $l => $name)
    <label for="sub_{{ $l }}">{{ $name }} (.srt ou .vtt)
      <input id="sub_{{ $l }}" name="sub_{{ $l }}" type="file" accept=".srt,.vtt,text/vtt,application/x-subrip"></label>
    @error('sub_'.$l)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
    @if (in_array($l, $tracks, true) && ! empty($vm['id']))
      <div class="row" style="gap:10px;align-items:center"><a class="meta" href="{{ route('videos.subtitles', [$vm['id'], $l]) }}" target="_blank">{{ __('Voir le fichier actuel') }}</a>
        <label class="check" for="remove_{{ $l }}"><input id="remove_{{ $l }}" name="remove_{{ $l }}" type="checkbox" value="1"> {{ __('Supprimer') }}</label></div>
    @endif
  @endforeach
  @if ($auto ?? false)
    <label class="check" for="auto"><input id="auto" name="auto" type="checkbox" value="1" @checked(empty($tracks))> {{ __('Créer les sous-titres automatiquement (transcription puis traduction)') }}</label>
  @else
    <p class="meta" style="margin:0">{{ __('Astuce : des applis gratuites comme CapCut créent un fichier .srt à partir de ta vidéo.') }}</p>
  @endif
</fieldset>

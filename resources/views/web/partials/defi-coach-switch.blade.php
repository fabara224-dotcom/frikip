{{-- Défis et Coaching côte à côte : on passe de l'un à l'autre d'un seul geste. --}}
<nav class="dcswitch" aria-label="{{ __('Défis et coaching') }}">
  <a href="{{ route('challenges') }}" @if ($on === 'defis') aria-current="page" @endif><x-ico name="trophy" />{{ __('Défis') }}</a>
  <a href="{{ route('coaching') }}" @if ($on === 'coaching') aria-current="page" @endif><x-ico name="coach" />{{ __('Coaching') }}</a>
</nav>

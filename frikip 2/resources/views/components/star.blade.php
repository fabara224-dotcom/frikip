@props(['followers' => null])
@php($min = \App\Terrain\Growth\Engagement::starThreshold())
<svg {{ $attributes->merge(['class' => 'star']) }} viewBox="0 0 24 24" role="img" aria-label="{{ __('Étoile bleue : plus de :n abonnés', ['n' => number_format($min, 0, ',', ' ')]) }}"><title>{{ __('Étoile bleue : plus de :n abonnés', ['n' => number_format($min, 0, ',', ' ')]) }}</title><circle cx="12" cy="12" r="11" fill="#1D9BF0"/><path d="M12 5.2l1.9 4 4.4.5-3.3 3 .9 4.3L12 14.8 8.1 17l.9-4.3-3.3-3 4.4-.5z" fill="#fff"/></svg>

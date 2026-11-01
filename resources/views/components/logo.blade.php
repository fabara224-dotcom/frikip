{{-- Logo Frikip : pastille ballon + mot « FRIKIP » en italique, avec le slogan en petit. --}}
@props(['tagline' => true])
<span {{ $attributes->merge(['class' => 'fklogo']) }}>
  <svg class="fklogo-mark" viewBox="0 0 40 40" aria-hidden="true">
    <circle cx="20" cy="20" r="18" fill="none" stroke="currentColor" stroke-width="3.2"/>
    <path d="M20 12.5l6.6 4.8-2.5 7.8h-8.2l-2.5-7.8z" fill="currentColor"/>
    <path d="M20 12.5V6.5M26.6 17.3l5.6-2M24.1 25.1l3.6 4.8M15.9 25.1l-3.6 4.8M13.4 17.3l-5.6-2" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
  </svg>
  <span class="fklogo-txt">
    <span class="fklogo-word">FRIK<em>IP</em></span>
    @if ($tagline)<span class="fklogo-tag">{{ __('Le réseau sport') }}</span>@endif
  </span>
</span>

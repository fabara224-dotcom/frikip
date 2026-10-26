{{-- Affiche un réglage de config/terrain.php, ou « À compléter » en jaune s'il est vide.
     Utilisation : <x-cfg key="email" />  ou  <x-cfg key="email" mailto /> --}}
@props(['key', 'mailto' => false])
@php
    $c = config('terrain');
    $v = (string) ($c[$key] ?? '');
    if ($v === '' && in_array($key, ['privacy_email', 'safety_email'], true)) {
        $v = (string) ($c['email'] ?? '');
    }
    $labels = [
        'app_name' => "nom de l'app", 'editor' => "nom de l'entreprise", 'editor_address' => 'adresse postale',
        'country' => 'pays', 'email' => 'e-mail de support', 'privacy_email' => 'e-mail confidentialité',
        'safety_contact_name' => 'nom du responsable sécurité des enfants', 'safety_email' => 'e-mail sécurité des enfants',
        'last_update' => 'date de mise à jour', 'min_age' => 'âge minimum',
    ];
@endphp
@if ($v !== '')
@if ($mailto)
<a href="mailto:{{ $v }}">{{ $v }}</a>
@else
{{ $v }}
@endif
@else
<mark class="todo">À compléter : {{ $labels[$key] ?? $key }}</mark>
@endif

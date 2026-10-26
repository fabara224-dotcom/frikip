@extends('layouts.web')

@section('title', __('Notifications').' – '.config('terrain.app_name'))

@php
  $icons = ['message' => '💬', 'call' => '📞', 'call_missed' => '📵', 'offer' => '📢', 'profile_view' => '👀', 'event' => '📅', 'challenge' => '🏆',
            'alert' => '📣', 'verification' => '✓', 'payment' => '💳', 'referral' => '🎁', 'video' => '🎬', 'report' => '🛡️'];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:720px">
    <div class="row" style="justify-content:space-between;align-items:center">
      <h1>{{ __('Notifications') }}</h1>
      <a class="meta" href="{{ route('settings') }}#preferences">{{ __('Recevoir par SMS / WhatsApp') }}</a>
    </div>
    <div class="list notifs">
      @forelse ($rows as $n)
        <a class="li {{ $n->read_at ? '' : 'unread' }}" href="{{ $n->url ?: '#' }}">
          <span class="nico">{{ $icons[$n->type] ?? '🔔' }}</span>
          <span class="grow" style="min-width:0"><span class="t">{{ $n->title }}</span>@if ($n->body)<span class="s">{{ $n->body }}</span>@endif</span>
          <span class="meta" style="flex:none">{{ \Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans(null, true) }}</span>
        </a>
      @empty
        <p class="meta" style="padding:24px 0">{{ __('Rien de nouveau pour l’instant. Publie une vidéo, participe au défi de la semaine ou inscris-toi à une détection !') }}</p>
      @endforelse
    </div>
  </div>
</div>
@endsection

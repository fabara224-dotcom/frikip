@extends('layouts.web')

@section('title', __('Suivi coaching').' – '.config('terrain.app_name'))

@php
  $kinds = ['message' => __('Message'), 'programme' => __('Programme'), 'bilan' => __('Bilan'), 'demande' => __('Demande')];
  $open = ! in_array($r->status, ['attente_paiement', 'annulee'], true);
@endphp

@section('content')
<div class="page gut">
  <div class="narrow">
    <p class="meta" style="margin:0"><a href="{{ route('coaching.follow') }}">← {{ $role === 'coach' ? __('Espace coach') : __('Mon coaching') }}</a></p>
    <h1>{{ $role === 'coach' ? ($athleteName ?? __('Sportif')) : __($r->package_label) }}</h1>
    <p class="plan-who">{{ __($r->goal) }} · <span class="pill">{{ __(\App\Terrain\Growth\Coaching::STATUS[$r->status] ?? $r->status) }}</span>
      @if ($r->ends_at) · {{ __('jusqu’au :d', ['d' => \Illuminate\Support\Carbon::parse($r->ends_at)->format('d/m/Y')]) }}@endif</p>

    @if ($role === 'sportif')
      @if ($coach)
        <div class="coachcard"><b>{{ $coach->name }}</b><span>{{ $coach->specialty }}</span>@if ($coach->bio)<small>{{ $coach->bio }}</small>@endif</div>
      @elseif ($r->status === 'payee')
        <p class="note">{{ __('Paiement reçu. Un coach est en train d’être choisi pour toi : tu seras prévenu dès qu’il te répond.') }}</p>
      @endif
    @elseif ($plan)
      <details class="more-opts"><summary><b>{{ __('Programme gratuit du sportif') }}</b></summary>
        <div style="padding:0 16px 14px">
          <ul class="plan-list">@foreach ($plan['findings'] as $f)<li>{{ __($f) }}</li>@endforeach</ul>
          <p class="meta">{{ __('Priorités') }} : {{ implode(', ', array_map(fn ($p) => __($p['title']), $plan['priorities'])) }}</p>
        </div>
      </details>
    @endif

    @if ($r->status === 'attente_paiement')
      <form method="POST" action="{{ route('coaching.pay', $r->reference) }}" class="note" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">@csrf
        <span>{{ __('Cette réservation n’est pas encore payée.') }}</span><button class="btn sm" type="submit">{{ __('Payer') }}</button></form>
    @endif

    <div class="cthread">
      @forelse ($msgs as $m)
        <div class="cmsg {{ $m->author === $role ? 'mine' : '' }} k-{{ $m->kind }}">
          <small>{{ $m->author === 'coach' ? __('Coach') : __('Sportif') }}@if ($m->author_name) · {{ $m->author_name }}@endif @if ($m->kind !== 'message')· <b>{{ $kinds[$m->kind] ?? $m->kind }}</b>@endif · {{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d/m H:i') }}</small>
          <p>{!! nl2br(e($m->body)) !!}</p>
        </div>
      @empty
        <p class="meta">{{ __('Pas encore de message.') }}</p>
      @endforelse
    </div>

    @if ($open)
      <form method="POST" action="{{ route('coaching.send', $r->reference) }}" class="card">@csrf
        @if ($role === 'coach')
          <div class="plans" style="grid-template-columns:repeat(3,1fr)">
            @foreach (['message', 'programme', 'bilan'] as $k)<label class="opt" style="padding:10px"><input type="radio" name="kind" value="{{ $k }}" @checked($k === 'message')><span>{{ $kinds[$k] }}</span></label>@endforeach
          </div>
        @else
          <input type="hidden" name="kind" value="message">
        @endif
        <label for="body" class="visually-hidden">{{ __('Ton message') }}</label>
        <textarea id="body" name="body" rows="4" maxlength="4000" required placeholder="{{ $role === 'coach' ? __('Programme de la semaine, conseils, bilan…') : __('Ta question, ta demande, comment s’est passé ton match…') }}"></textarea>
        @error('body')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn" type="submit">{{ __('Envoyer') }}</button>
      </form>
    @endif
  </div>
</div>
@endsection

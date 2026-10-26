{{-- En-tête des pages de l'espace admin (le menu est dans layouts/admin) --}}
<div class="adm-head">
  <h1>{{ $title }}</h1>
  @isset($lead)<p class="adm-lead">{{ $lead }}</p>@endisset
</div>
@if (session('saved'))<div class="msg ok" role="status">{{ session('saved') }}</div>@endif
@if ($errors->any())<div class="msg err" role="alert">{{ $errors->first() }}</div>@endif

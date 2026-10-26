@extends('layouts.web')

@section('title', 'Vérifier mon compte – '.config('terrain.app_name'))

@php($isRecruiter = $me['role'] === 'recruiter')

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:620px">
    <h1>Vérifier mon compte</h1>
    <p class="meta">Le badge <x-ver /> rassure les clubs et les familles. Tes documents restent privés : seule l'équipe de vérification les voit, puis ils sont supprimés après la décision.</p>

    @if ($alreadyVerified)
      <div class="card"><b>Ton compte est vérifié <x-ver /></b><span class="meta">Le badge apparaît sur ton profil et tes messages.</span></div>
    @elseif ($last && $last->status === 'en_attente')
      <div class="card"><b>Demande en cours d'examen</b><span class="meta">Envoyée le {{ \Illuminate\Support\Carbon::parse($last->created_at)->format('d/m/Y') }}. Réponse sous 72 h.</span></div>
    @else
      @if ($last && $last->status === 'refusee')
        <p class="note">Ta dernière demande a été refusée : {{ $last->admin_note ?: 'document illisible ou incomplet' }}. Tu peux en envoyer une nouvelle.</p>
      @endif
      <form method="POST" action="{{ route('verification') }}" enctype="multipart/form-data" class="card">@csrf
        <label for="full_name">Nom complet (comme sur le document)<input id="full_name" name="full_name" value="{{ old('full_name', $me['name']) }}" required></label>
        @if ($isRecruiter)
          <label for="organization">Club, académie, agence ou université<input id="organization" name="organization" value="{{ old('organization') }}" required></label>
          <label for="licence_number">Numéro de licence (club, fédération ou agent FIFA)<input id="licence_number" name="licence_number" value="{{ old('licence_number') }}" required></label>
        @else
          <label for="licence_number">Numéro de licence sportive (si tu en as une)<input id="licence_number" name="licence_number" value="{{ old('licence_number') }}"></label>
        @endif
        <label for="document_type">Document<select id="document_type" name="document_type">
          @foreach ($isRecruiter
              ? ['licence_agent' => 'Licence d’agent ou de recruteur', 'statuts_club' => 'Statuts / attestation du club', 'cni' => 'Carte d’identité', 'passeport' => 'Passeport']
              : ['cni' => 'Carte d’identité', 'passeport' => 'Passeport', 'licence' => 'Licence sportive', 'acte' => 'Extrait d’acte de naissance', 'carte_scolaire' => 'Carte scolaire'] as $k => $l)
            <option value="{{ $k }}" @selected(old('document_type') === $k)>{{ $l }}</option>
          @endforeach
        </select></label>
        <label for="document">Photo ou PDF du document<input id="document" name="document" type="file" accept="image/*,application/pdf" required></label>
        @foreach (['full_name', 'organization', 'licence_number', 'document'] as $f)@error($f)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach

        @if ($minor)
          <div class="section-label" style="margin:8px 0 0">Parent ou tuteur (obligatoire : tu as moins de 18 ans)</div>
          <label for="guardian_name">Nom du parent ou tuteur<input id="guardian_name" name="guardian_name" value="{{ old('guardian_name') }}" required></label>
          <label for="guardian_phone">Téléphone du parent ou tuteur<input id="guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone') }}" required></label>
          <label for="guardian_document">Pièce d'identité du parent ou tuteur<input id="guardian_document" name="guardian_document" type="file" accept="image/*,application/pdf" required></label>
          <label class="check"><input type="checkbox" name="guardian_consent" value="1" @checked(old('guardian_consent'))> Le parent ou tuteur accepte que ce profil soit visible par les recruteurs vérifiés et reçoit une copie des messages.</label>
          @foreach (['guardian_name', 'guardian_phone', 'guardian_document', 'guardian_consent'] as $f)@error($f)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach
        @endif
        <button class="btn" type="submit">Envoyer ma demande</button>
      </form>
    @endif
  </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Sécurité des enfants – '.config('terrain.app_name'))
@section('description', "Normes contre l'exploitation et les abus sexuels sur les enfants (CSAE) : interdictions, protections, signalement et contact.")

@section('content')
<div class="doc gut">
<article class="narrow">
  <span class="eyebrow">Normes de sécurité des enfants · Child Safety Standards</span>
  <h1>Protection des enfants sur <x-cfg key="app_name" /></h1>
  <p class="lead">Beaucoup de jeunes sportifs utilisent {{ config('terrain.app_name', 'Frikip') }}. <b><x-cfg key="editor" /></b>, éditeur de l'application, applique une tolérance zéro envers l'exploitation et les abus sexuels sur les enfants (CSAE) et envers les contenus d'abus sexuels sur mineurs (CSAM).</p>
  <p class="meta">Dernière mise à jour : <x-cfg key="last_update" /></p>

  <h2 id="interdit">Ce qui est strictement interdit</h2>
  <ul>
    <li>Tout contenu montrant ou décrivant des abus sexuels sur un mineur, ou sexualisant un mineur.</li>
    <li>Le pédopiégeage (grooming) : gagner la confiance d'un enfant pour l'exploiter, lui demander des photos, le pousser à quitter l'application pour une conversation privée, lui proposer un rendez-vous hors cadre sportif.</li>
    <li>La sextorsion, le chantage, la traite et l'exploitation, y compris sous couvert de faux essais, de faux contrats ou de voyages à l'étranger.</li>
    <li>Demander de l'argent à un jeune sportif ou à sa famille en échange d'un essai, d'un visa ou d'un transfert.</li>
  </ul>

  <h2 id="protections">Nos protections</h2>
  <ul>
    <li><b>Âge minimum</b> de <x-cfg key="min_age" /> ans ; accord d'un parent ou tuteur obligatoire jusqu'à 18 ans.</li>
    <li><b>Seuls les recruteurs vérifiés</b> (licence de club ou d'agent contrôlée) peuvent écrire à un mineur. Les sportifs ne peuvent pas écrire à des mineurs inconnus.</li>
    <li><b>Copie au tuteur</b> des messages reçus par un mineur.</li>
    <li><b>Alertes automatiques</b> sur les messages qui parlent d'argent, de visa ou de rendez-vous, avec rappel des règles de sécurité.</li>
    <li><b>Analyse des vidéos et images</b> publiées pour détecter les contenus interdits.</li>
    <li><b>Bouton « Signaler »</b> sur chaque profil, vidéo, commentaire et conversation, et possibilité de bloquer un utilisateur.</li>
  </ul>

  <h2 id="signaler">Signaler un problème</h2>
  <div class="callout warn">
    <p style="margin-top:0"><b>Dans l'application :</b> touchez « Signaler » ou « ••• » sur le profil, la vidéo ou la conversation concernée. Vous pouvez aussi passer par Paramètres et confidentialité &gt; Signaler un problème. Vous n'avez pas besoin de quitter l'application.</p>
    <p><b>Par e-mail :</b> <x-cfg key="safety_email" mailto /></p>
    <p><b>Par téléphone :</b> <x-phones /></p>
    <p style="margin-bottom:0">Si un enfant est en danger immédiat, contactez d'abord la police ou la gendarmerie de votre pays.</p>
  </div>

  <h2 id="traitement">Ce que nous faisons après un signalement</h2>
  <ol>
    <li>Les signalements liés à la sécurité des enfants sont traités en priorité, avant tous les autres.</li>
    <li>Dès que nous avons connaissance d'un contenu d'abus sexuel sur mineur, nous le retirons et fermons le compte responsable.</li>
    <li>Nous conservons les preuves de manière sécurisée et les transmettons aux autorités compétentes, conformément à la loi. Lorsque c'est applicable, nous signalons aussi au National Center for Missing &amp; Exploited Children (NCMEC).</li>
    <li>Nous prévenons, si c'est approprié, le parent ou tuteur du mineur concerné.</li>
  </ol>

  <h2 id="lois">Respect de la loi</h2>
  <p><x-cfg key="app_name" /> respecte les lois sur la protection de l'enfance applicables dans les pays où l'application est disponible, et coopère avec les autorités judiciaires et policières.</p>

  <h2 id="contact">Personne responsable</h2>
  <p>Pour toute question sur nos pratiques de prévention et notre conformité :</p>
  <p><b><x-cfg key="safety_contact_name" /></b><br><x-cfg key="editor" /> – éditeur de <x-cfg key="app_name" /><br><x-cfg key="safety_email" mailto /></p>
</article>
</div>
@endsection

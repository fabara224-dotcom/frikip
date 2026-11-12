/*
 * Réglages du prototype.
 *
 * mode "demo" : tout fonctionne dans le navigateur avec les données fictives de js/data/demo-data.js.
 * mode "api"  : les mêmes écrans parlent au vrai site Laravel (voir js/services/api.js et README.md).
 *               Il suffit de changer "mode" et "apiBase" : aucun écran n'est à réécrire.
 */
window.Frikip = window.Frikip || {};
Frikip.config = Object.assign({
  mode: 'demo',                 // 'demo' | 'api'
  apiBase: '/api/v1',           // ex. 'https://frikip.com/api/v1' une fois l'API Laravel en ligne
  pollMessagesMs: 4000,         // rafraîchissement des messages en mode api
  pollScoresMs: 30000,          // rafraîchissement des scores en direct (API-Football côté serveur)
  appName: 'FootPro',
  tagline: 'Le réseau foot',
  loginUrl: null,               // page de connexion du site (mode api)
  shareBase: 'https://frikip.com/v/'
}, window.FRIKIP_CONFIG || {});   // le site Laravel peut fournir ses réglages (voir web/app-v2.blade.php)

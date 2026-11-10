/*
 * État partagé de l'application + petit bus d'événements.
 * Les écrans s'abonnent (Frikip.bus.on) au lieu de se connaître entre eux.
 */
window.Frikip = window.Frikip || {};
(function () {
  var handlers = {};
  Frikip.bus = {
    on: function (name, fn) { (handlers[name] = handlers[name] || []).push(fn); },
    emit: function (name, data) { (handlers[name] || []).forEach(function (fn) { try { fn(data); } catch (e) { console.error(e); } }); }
  };

  Frikip.state = {
    me: null,              // compte connecté (Frikip.api.me())
    view: 'accueil',       // écran affiché
    feedTab: 'pour-toi',   // onglet du fil
    profileId: null,       // profil ouvert (null = le mien)
    conversationId: null,  // conversation ouverte
    counts: { messages: 0, notifications: 0 }
  };

  // Préférences locales (facultatives) : onglet du fil retenu entre deux visites
  Frikip.prefs = {
    get: function (k, d) { try { var v = localStorage.getItem('frikip.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem('frikip.' + k, JSON.stringify(v)); } catch (e) { /* stockage indisponible : on ignore */ } }
  };
})();

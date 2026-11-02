/* SOMADI – connexion par empreinte (WebAuthn / clés d'accès).
 * L'empreinte est vérifiée par le téléphone lui-même ; le navigateur ne transmet qu'une signature.
 * Les boutons ne sont montrés que sur un téléphone équipé d'un capteur : sur PC, mot de passe + code par e-mail. */
(function () {
  'use strict';

  var MEMO = 'somadi-empreinte-active';

  // ---- Conversions base64url <-> octets ----
  function versOctets(b64u) {
    var b64 = b64u.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) b64 += '=';
    var bin = atob(b64), t = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) t[i] = bin.charCodeAt(i);
    return t.buffer;
  }
  function versB64u(buffer) {
    if (!buffer) return null;
    var t = new Uint8Array(buffer), bin = '';
    for (var i = 0; i < t.length; i++) bin += String.fromCharCode(t[i]);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  // ---- Détection : téléphone (ou tablette) avec capteur intégré ----
  function estMobile() {
    if (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean' && navigator.userAgentData.mobile) return true;
    var ua = navigator.userAgent || '';
    if (/Android|iPhone|iPad|iPod/i.test(ua)) return true;
    return /Macintosh/i.test(ua) && navigator.maxTouchPoints > 1; // iPad récent, qui se présente comme un Mac
  }
  function capteurDisponible() {
    if (!window.PublicKeyCredential || !navigator.credentials || !window.isSecureContext || !estMobile()) {
      return Promise.resolve(false);
    }
    return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
  }

  // ---- Échanges avec le serveur ----
  function jeton() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) return m.content;
    var i = document.querySelector('input[name="_token"]');
    return i ? i.value : '';
  }
  function envoyer(url, donnees) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': jeton(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(donnees || {})
    }).then(function (rep) {
      return rep.json().catch(function () { return {}; }).then(function (corps) {
        if (!rep.ok) {
          var msg = corps.message || (rep.status === 429 ? 'Trop d’essais. Patientez une minute.' : rep.status === 419 ? 'La page a expiré. Rechargez-la.' : 'Une erreur est survenue. Réessayez.');
          throw new Error(msg);
        }
        return corps;
      });
    });
  }

  function erreurNavigateur(e) {
    if (e && e.name === 'NotAllowedError') return 'Opération annulée ou délai dépassé. Réessayez.';
    if (e && e.name === 'InvalidStateError') return 'L’empreinte est déjà activée sur ce téléphone pour votre compte.';
    if (e && e.name === 'SecurityError') return 'Le domaine du site ne correspond pas à la configuration de SOMADI.';
    return (e && e.message) || 'Une erreur est survenue. Réessayez.';
  }
  function afficherErreur(msg) {
    var el = document.getElementById('erreur-empreinte');
    if (!el) return;
    el.textContent = msg;
    el.classList.toggle('hidden', !msg);
  }
  function occuper(bouton, oui) {
    if (!bouton) return;
    bouton.disabled = oui;
    bouton.setAttribute('aria-busy', oui ? 'true' : 'false');
  }

  // ---- Connexion ----
  function connexion(bloc) {
    var bouton = bloc.querySelector('[data-empreinte-bouton]');
    bouton.addEventListener('click', function () {
      if (!navigator.onLine) { afficherErreur('Pas de réseau : la connexion est impossible pour le moment.'); return; }
      afficherErreur('');
      occuper(bouton, true);
      envoyer(bloc.getAttribute('data-options'))
        .then(function (o) {
          var pk = o.publicKey;
          pk.challenge = versOctets(pk.challenge);
          (pk.allowCredentials || []).forEach(function (c) { c.id = versOctets(c.id); });
          return navigator.credentials.get({ publicKey: pk });
        })
        .then(function (cred) {
          var r = cred.response;
          return envoyer(bloc.getAttribute('data-verifier'), {
            id: versB64u(cred.rawId),
            reponse: {
              clientDataJSON: versB64u(r.clientDataJSON),
              authenticatorData: versB64u(r.authenticatorData),
              signature: versB64u(r.signature),
              userHandle: versB64u(r.userHandle)
            }
          });
        })
        .then(function (res) {
          try { localStorage.setItem(MEMO, '1'); } catch (e) { /* stockage indisponible */ }
          window.location.assign(res.redirection || '/');
        })
        .catch(function (e) {
          var msg = erreurNavigateur(e);
          if (e && e.name === 'NotAllowedError') {
            msg = 'Connexion par empreinte annulée, ou aucune empreinte SOMADI sur ce téléphone. Sinon, connectez-vous avec votre mot de passe, puis activez l’empreinte dans Mon profil.';
          }
          afficherErreur(msg);
          occuper(bouton, false);
        });
    });
  }

  // ---- Activation (Mon profil) ----
  function activation(bloc) {
    var bouton = bloc.querySelector('[data-empreinte-bouton]');
    var mdp = bloc.querySelector('input[type="password"]');
    bouton.addEventListener('click', function () {
      if (!mdp.value) { afficherErreur('Saisissez votre mot de passe.'); mdp.focus(); return; }
      if (!navigator.onLine) { afficherErreur('Pas de réseau : l’activation est impossible pour le moment.'); return; }
      afficherErreur('');
      occuper(bouton, true);
      envoyer(bloc.getAttribute('data-options'), { mot_de_passe: mdp.value })
        .then(function (o) {
          mdp.value = '';
          var pk = o.publicKey;
          pk.challenge = versOctets(pk.challenge);
          pk.user.id = versOctets(pk.user.id);
          (pk.excludeCredentials || []).forEach(function (c) { c.id = versOctets(c.id); });
          return navigator.credentials.create({ publicKey: pk });
        })
        .then(function (cred) {
          return envoyer(bloc.getAttribute('data-activer'), {
            reponse: {
              clientDataJSON: versB64u(cred.response.clientDataJSON),
              attestationObject: versB64u(cred.response.attestationObject)
            }
          });
        })
        .then(function (res) {
          try { localStorage.setItem(MEMO, '1'); } catch (e) { /* stockage indisponible */ }
          window.location.assign(res.redirection);
        })
        .catch(function (e) {
          afficherErreur(erreurNavigateur(e));
          occuper(bouton, false);
        });
    });
  }

  capteurDisponible().then(function (ok) {
    if (!ok) return;
    var memo = null;
    try { memo = localStorage.getItem(MEMO); } catch (e) { /* stockage indisponible */ }

    var bloc = document.querySelector('[data-empreinte-connexion]');
    if (bloc) {
      bloc.classList.remove('hidden');
      connexion(bloc);
      // Téléphone déjà utilisé avec l'empreinte : le bouton empreinte est mis en avant, le mot de passe passe en second.
      var bmdp = document.querySelector('[data-bouton-mot-de-passe]');
      if (bmdp && memo) { bmdp.classList.remove('btn-primaire'); bmdp.classList.add('btn-secondaire'); }
      var email = document.getElementById('email');
      if (email && memo) { email.removeAttribute('autofocus'); email.blur(); }
    }

    var profil = document.querySelector('[data-empreinte-activation]');
    if (profil) {
      var f = profil.querySelector('[data-empreinte-formulaire]');
      var ind = profil.querySelector('[data-empreinte-indisponible]');
      if (f) { f.classList.remove('hidden'); activation(profil); }
      if (ind) ind.classList.add('hidden');
    }

    var proposition = document.querySelector('[data-empreinte-proposition]');
    if (proposition && !memo) proposition.classList.remove('hidden');
  });
})();

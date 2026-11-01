/* SOMADI – comportements communs : menu mobile, mode hors connexion (EC21), service worker. */
(function () {
  'use strict';

  // ---- Tiroir de navigation (mobile) ----
  var tiroir = document.getElementById('tiroir');
  function ouvrir() { if (tiroir) { tiroir.classList.remove('hidden'); document.body.style.overflow = 'hidden'; } }
  function fermer() { if (tiroir) { tiroir.classList.add('hidden'); document.body.style.overflow = ''; } }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-ouvrir-tiroir]')) { ouvrir(); }
    else if (e.target.closest('[data-fermer-tiroir]')) { fermer(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermer(); });

  // ---- Hors connexion ----
  var bandeau = document.getElementById('bandeau-hors-ligne');
  function fraicheur() {
    var meta = document.querySelector('meta[name="somadi-genere-le"]');
    if (!meta) return '';
    var d = new Date(meta.content);
    if (isNaN(d)) return '';
    return 'du ' + d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
  function majEtatReseau() {
    var horsLigne = !navigator.onLine;
    document.documentElement.classList.toggle('hors-ligne', horsLigne);
    if (bandeau) {
      bandeau.classList.toggle('hidden', !horsLigne);
      var f = bandeau.querySelector('[data-fraicheur]');
      if (f) f.textContent = fraicheur();
    }
  }
  window.addEventListener('online', majEtatReseau);
  window.addEventListener('offline', majEtatReseau);
  majEtatReseau();

  // Aucune écriture hors connexion : on bloque l'envoi plutôt que de perdre la saisie en silence.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var methode = (form.getAttribute('method') || 'get').toLowerCase();
    if (methode === 'post' && !navigator.onLine && !form.hasAttribute('data-hors-ligne-ok')) {
      e.preventDefault();
      alerte('Pas de réseau : la saisie n\'a pas été envoyée. Gardez la page ouverte et réessayez dès le retour de la connexion.');
    }
    if (form.hasAttribute('data-deconnexion')) { viderCaches(); }
    // Évite les doubles envois (double appui sur mobile).
    if (methode === 'post' && !e.defaultPrevented) {
      var b = form.querySelector('button[type="submit"]');
      if (b) { setTimeout(function () { b.disabled = true; }, 0); }
    }
  }, true);

  function alerte(message) {
    var el = document.createElement('div');
    el.setAttribute('role', 'alert');
    el.className = 'fixed inset-x-4 bottom-24 z-50 mx-auto max-w-md rounded-lg bg-stone-900 px-4 py-3 text-sm text-white shadow-lg lg:bottom-6';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 6000);
  }

  // ---- Graphiques : réticule et infobulle au survol (ou au toucher) ----
  var NS = 'http://www.w3.org/2000/svg';
  function fmt(v, dec) { return v === null || v === undefined ? '—' : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec }); }
  document.querySelectorAll('[data-graphe]').forEach(function (bloc) {
    var d; try { d = JSON.parse(bloc.getAttribute('data-graphe')); } catch (e) { return; }
    var svg = bloc.querySelector('svg'), zone = bloc.querySelector('[data-zone]'), curseur = bloc.querySelector('[data-curseur]');
    var marqueurs = bloc.querySelector('[data-marqueurs]'), bulle = bloc.querySelector('[data-infobulle]');
    var n = d.dates.length;
    function y(v) { return d.h + d.lh - (v / d.haut) * d.lh; }
    function montrer(evt) {
      var r = svg.getBoundingClientRect();
      var vx = (evt.clientX - r.left) / r.width * d.W;
      var i = n > 1 ? Math.round((vx - d.g) / d.lw * (n - 1)) : 0;
      i = Math.max(0, Math.min(n - 1, i));
      var x = d.g + (n > 1 ? i / (n - 1) : 0.5) * d.lw;
      curseur.setAttribute('x1', x); curseur.setAttribute('x2', x); curseur.setAttribute('visibility', 'visible');
      while (marqueurs.firstChild) marqueurs.removeChild(marqueurs.firstChild);
      var lignes = [];
      d.series.forEach(function (s) {
        var v = s.valeurs[i];
        if (v === null || v === undefined) return;
        var c = document.createElementNS(NS, 'circle');
        c.setAttribute('cx', x); c.setAttribute('cy', y(v)); c.setAttribute('r', 4.5);
        c.setAttribute('fill', s.couleur); c.setAttribute('stroke', '#fff'); c.setAttribute('stroke-width', 2);
        marqueurs.appendChild(c);
        lignes.push({ nom: s.nom, couleur: s.couleur, v: v });
      });
      var dateTxt = new Date(d.dates[i] + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
      lignes.sort(function (a, b) { return b.v - a.v; });
      bulle.innerHTML = '';
      var t = document.createElement('div'); t.className = 'mb-1 font-semibold text-stone-800'; t.textContent = dateTxt; bulle.appendChild(t);
      if (!lignes.length) { var e = document.createElement('div'); e.className = 'text-stone-500'; e.textContent = 'Pas de saisie'; bulle.appendChild(e); }
      lignes.forEach(function (l) {
        var row = document.createElement('div'); row.className = 'flex items-center gap-2 text-stone-700';
        var sw = document.createElement('span'); sw.style.cssText = 'display:inline-block;width:10px;height:2px;border-radius:2px;background:' + l.couleur;
        var nom = document.createElement('span'); nom.className = 'flex-1'; nom.textContent = l.nom;
        var val = document.createElement('strong'); val.className = 'tabular-nums'; val.textContent = fmt(l.v, d.decimales) + (d.unite === '%' ? ' %' : ' ' + d.unite);
        row.appendChild(sw); row.appendChild(nom); row.appendChild(val); bulle.appendChild(row);
      });
      bulle.classList.remove('hidden');
      var px = (x / d.W) * r.width, bw = bulle.offsetWidth;
      bulle.style.left = (px + 12 + bw > r.width ? Math.max(0, px - 12 - bw) : px + 12) + 'px';
    }
    function cacher() { curseur.setAttribute('visibility', 'hidden'); bulle.classList.add('hidden'); while (marqueurs.firstChild) marqueurs.removeChild(marqueurs.firstChild); }
    zone.addEventListener('pointermove', montrer);
    zone.addEventListener('pointerdown', montrer);
    zone.addEventListener('pointerleave', cacher);
  });

  // ---- Service worker ----
  function viderCaches() {
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: 'vider-pages' });
    }
  }
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () { /* PWA facultative */ });
      if (document.querySelector('meta[name="somadi-vider-cache"]')) { viderCaches(); }
    });
  }
})();

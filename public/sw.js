/* SOMADI – service worker (EC21).
 * - Ressources statiques : cache d'abord.
 * - Pages : réseau d'abord ; la dernière version reçue est gardée pour la consultation hors connexion.
 * - Aucune requête d'écriture (POST, PUT…) n'est mise en cache ni rejouée.
 * - À la déconnexion, les pages gardées sont effacées de l'appareil. */
const PREFIXE = 'somadi-app-';
const VERSION = PREFIXE + 'v6';
const STATIQUE = VERSION + '-statique';
const PAGES = VERSION + '-pages';
const PRECACHE = ['/hors-ligne', '/css/app.css', '/js/app.js', '/icons/icon-192.png', '/manifest.webmanifest'];
const JAMAIS = ['/connexion', '/deconnexion', '/livewire', '/up'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIQUE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      // Ne supprime que les anciennes versions de CE service worker : les caches de l'ancienne application restent intacts.
      .then((cles) => Promise.all(cles.filter((k) => k.startsWith(PREFIXE) && !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'vider-pages') {
    event.waitUntil(caches.delete(PAGES));
  }
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (JAMAIS.some((p) => url.pathname.startsWith(p))) return;

  const statique = /\.(css|js|png|svg|webmanifest|woff2?)$/.test(url.pathname);
  if (statique) {
    event.respondWith(
      caches.match(req).then((cache) => cache || fetch(req).then((rep) => {
        if (rep.ok) { const copie = rep.clone(); caches.open(STATIQUE).then((c) => c.put(req, copie)); }
        return rep;
      }))
    );
    return;
  }

  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((rep) => {
          // On ne garde que les pages réellement affichées (pas les redirections vers la connexion).
          if (rep.ok && !rep.redirected) { const copie = rep.clone(); caches.open(PAGES).then((c) => c.put(req, copie)); }
          return rep;
        })
        .catch(() => caches.match(req).then((cache) => cache || caches.match('/hors-ligne')))
    );
  }
});

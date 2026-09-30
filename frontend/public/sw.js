/*
 * Nexus Flow service worker (hand-written, classic / non-module).
 *
 * Served from the public root (/sw.js) so its scope is "/".
 *
 * ALLOW / DENY POLICY
 * ===================
 * This SW caches ONLY the app shell and public static assets. It NEVER caches
 * personal data, uploaded documents, or API responses.
 *
 * DENY (return early, do NOT call event.respondWith — let the browser handle
 *       the request normally over the network, never read/write the cache):
 *   1. Cross-origin requests (url.origin !== self.location.origin). This covers
 *      ALL calls to the API host (NEXT_PUBLIC_API_URL is a different origin),
 *      and the full-page OAuth redirect to Google / the API.
 *   2. Non-GET methods (POST/PUT/PATCH/DELETE, e.g. /api/auth/exchange).
 *   3. Any same-origin path starting with "/auth/" — the OAuth round-trip
 *      (/auth/callback?code=...) must never be intercepted or cached.
 *   4. Any path starting with "/api" — API responses must never be cached.
 *   5. Range requests / document downloads (Range header present) — streamed
 *      media and file downloads must never be served from cache.
 *
 * ALLOW (same-origin GET only):
 *   - Navigation requests (request.mode === "navigate") to non-/auth paths:
 *     NETWORK-FIRST, falling back to the precached "/offline" page when the
 *     network is unavailable.
 *   - Next static assets under "/_next/static/", files under "/icons/", the
 *     web manifest, favicon and fonts: CACHE-FIRST with a background update
 *     (stale-while-revalidate).
 */

const CACHE_VERSION = "nf-pwa-v1";
const PRECACHE_URLS = ["/offline", "/icons/icon-192.png", "/icons/icon-512.png", "/icons/favicon-32.png"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_VERSION);
      await cache.addAll(PRECACHE_URLS);
      await self.skipWaiting();
    })(),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)));
      await self.clients.claim();
    })(),
  );
});

// True for static assets that are safe to cache-first.
function isCacheableAsset(url) {
  return (
    url.pathname.startsWith("/_next/static/") ||
    url.pathname.startsWith("/icons/") ||
    url.pathname === "/manifest.webmanifest" ||
    url.pathname === "/favicon.ico" ||
    url.pathname.startsWith("/fonts/") ||
    url.pathname === "/offline"
  );
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(CACHE_VERSION);
  const cached = await cache.match(request);
  const network = fetch(request)
    .then((response) => {
      if (response && response.ok && response.type === "basic") {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => undefined);
  return cached || (await network) || fetch(request);
}

async function networkFirstNavigation(request) {
  try {
    return await fetch(request);
  } catch {
    const cache = await caches.open(CACHE_VERSION);
    const offline = await cache.match("/offline");
    return offline || Response.error();
  }
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // DENY 1: cross-origin (includes the API host and the OAuth redirect).
  if (url.origin !== self.location.origin) {
    return;
  }
  // DENY 2: non-GET methods.
  if (request.method !== "GET") {
    return;
  }
  // DENY 3: OAuth round-trip.
  if (url.pathname === "/auth" || url.pathname.startsWith("/auth/")) {
    return;
  }
  // DENY 4: API paths.
  if (url.pathname === "/api" || url.pathname.startsWith("/api/")) {
    return;
  }
  // DENY 5: Range requests / document downloads.
  if (request.headers.has("range")) {
    return;
  }

  // ALLOW: navigations — network-first with offline fallback.
  if (request.mode === "navigate") {
    event.respondWith(networkFirstNavigation(request));
    return;
  }

  // ALLOW: static assets — cache-first with background update.
  if (isCacheableAsset(url)) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  // Anything else same-origin GET: let the browser handle it normally.
});

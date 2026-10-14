{{-- The student portal's service worker.

     Served from student/sw.js, so its scope is the student prefix and it cannot
     see an admin or applicant request at all.

     It is deliberately dull. It caches static files and one offline page, and
     nothing else, because this portal:
       - puts a live CSRF token in the head of every page,
       - gates students on the platform fee, the first instalment and which
         programme they are currently viewing,
       - and polls payment status endpoints where a cached answer is a money bug.
     Every one of those breaks if authenticated HTML is cached, so none ever is.
--}}
const VERSION = @json($version);
const SCOPE = @json($scope);
const OFFLINE_URL = @json($offlineUrl);
const PRECACHE = @json($precache);
const ENABLED = @json($enabled);

const STATIC_CACHE = 'pax-student-static-v' + VERSION;
const SHELL_CACHE = 'pax-student-shell-v' + VERSION;

@if(!$enabled)
@verbatim
// ---------------------------------------------------------------------------
// The kill switch.
//
// A worker lives on the student's phone, not on the server, so a bad one cannot
// be fixed by deploying. With PWA_ENABLED=false this is what they receive: it
// throws away every cache and unregisters itself on the next visit.
// ---------------------------------------------------------------------------
  self.addEventListener('install', function (event) {
    self.skipWaiting();
  });

  self.addEventListener('activate', function (event) {
    event.waitUntil(
      caches.keys()
        .then(function (names) {
          return Promise.all(names.map(function (name) { return caches.delete(name); }));
        })
        .then(function () { return self.registration.unregister(); })
        .then(function () { return self.clients.matchAll(); })
        .then(function (clients) {
          clients.forEach(function (client) { client.navigate(client.url); });
        })
    );
  });
@endverbatim
@else
@verbatim

// ---------------------------------------------------------------------------
// Requests this worker must never touch, whatever else changes.
//
// Listed separately from the "what may be cached" rule on purpose: a careless
// future edit to the asset matcher still cannot reach a payment result.
// ---------------------------------------------------------------------------
const NEVER_TOUCH = [
  '/payment/',          // momo initiate, status polling, returns, webhooks
  '/status/',
  '/fees',
  '/payment-plan',
  '/installment-payment',
  '/manual-payment',
  '/multi-payment',
  '/platform-fee',
  '/progression/',
  '/course-registration',
  '/switch-program',
  '/current-enrollment',
  '/leave-impersonation',
  '/login',
  '/logout',
  '/2fa'
];

function isForbidden(url) {
  for (var i = 0; i < NEVER_TOUCH.length; i++) {
    if (url.pathname.indexOf(NEVER_TOUCH[i]) !== -1) {
      return true;
    }
  }
  return false;
}

// Static files only: the dashboard theme, the school's uploaded branding, and
// this app's own icons. Everything else is live.
function isStaticAsset(url) {
  return /\/dashboard\//.test(url.pathname)
      || /\/pwa\//.test(url.pathname)
      || /\/uploads\/setting\//.test(url.pathname)
      || /\.(css|js|woff2?|ttf|eot|svg|png|jpe?g|gif|ico)$/i.test(url.pathname);
}

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then(function (cache) { return cache.addAll(PRECACHE); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(names.map(function (name) {
        // Anything from an older version goes. The portal's CSS and JS URLs
        // carry no fingerprint, so this is the only thing standing between a
        // student and a month-old stylesheet.
        if (name !== STATIC_CACHE && name !== SHELL_CACHE) {
          return caches.delete(name);
        }
        return null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  const request = event.request;

  // Writes are never our business.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Another origin's problem.
  if (url.origin !== self.location.origin) {
    return;
  }

  if (isForbidden(url)) {
    return;
  }

  // A page. Always from the network, so the CSRF token is fresh, the fee gates
  // are evaluated, and the programme being shown is the one they selected. The
  // only fallback is the offline page, which carries nothing personal.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match(OFFLINE_URL).then(function (cached) {
          return cached || new Response('', { status: 504, statusText: 'Offline' });
        });
      })
    );
    return;
  }

  if (!isStaticAsset(url)) {
    return;
  }

  // A static file: serve what we have, and quietly refresh it for next time.
  event.respondWith(
    caches.open(STATIC_CACHE).then(function (cache) {
      return cache.match(request).then(function (cached) {
        const network = fetch(request).then(function (response) {
          if (response && response.status === 200 && response.type === 'basic') {
            cache.put(request, response.clone());
          }
          return response;
        }).catch(function () {
          return cached;
        });

        return cached || network;
      });
    })
  );
});
@endverbatim
@endif

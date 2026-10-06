// Service worker minimal : l'application doit rester TOUJOURS connectée au serveur
// (données financières en direct) ; on ne met donc rien en cache, on affiche
// seulement une page claire si le serveur est injoignable.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith(
    fetch(event.request).catch(() => new Response(
      '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
      '<body style="font-family:system-ui;background:#0B1220;color:#fff;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px">' +
      '<div><h2>Serveur injoignable</h2><p style="opacity:.7">Vérifiez que l\'ordinateur est allumé, que le serveur tourne et que vous êtes sur le même Wi-Fi.</p>' +
      '<button onclick="location.reload()" style="margin-top:12px;padding:10px 18px;border:0;border-radius:10px;background:#6366F1;color:#fff;font-size:15px">Réessayer</button></div></body>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    ))
  );
});

const SHELL_CACHE = "allerscan-shell-v1";
const OFFLINE_PAGE = "/offline.html";
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll([OFFLINE_PAGE]))
            .then(() => self.skipWaiting()),
    );
});
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith("allerscan-shell-") &&
                                key !== SHELL_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});
// Product pages and allergen records are always fetched from the server, never cached here.
self.addEventListener("fetch", (event) => {
    if (event.request.method !== "GET" || event.request.mode !== "navigate")
        return;
    event.respondWith(
        fetch(event.request).catch(async () => {
            const fallback = await caches.match(OFFLINE_PAGE);
            return (
                fallback ||
                new Response(
                    "You are offline. Reconnect to view current product information.",
                    {
                        status: 503,
                        headers: {
                            "Content-Type": "text/plain; charset=utf-8",
                        },
                    },
                )
            );
        }),
    );
});

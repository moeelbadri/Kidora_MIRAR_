/* Loaded synchronously before page scripts; never alter safe or cross-origin calls. */
(() => {
  const originalFetch = window.fetch;
  window.fetch = function (input, init) {
    const request = input instanceof Request ? input : null;
    const method = String(init?.method ?? request?.method ?? 'GET').toUpperCase();
    const url = new URL(request ? request.url : input, document.baseURI);
    if (url.origin !== location.origin || ['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      return originalFetch.call(this, input, init);
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return originalFetch.call(this, input, init);
    const headers = new Headers(init?.headers !== undefined ? init.headers : request?.headers);
    headers.set('X-CSRF-Token', token);
    return originalFetch.call(this, input, { ...init, headers });
  };
})();

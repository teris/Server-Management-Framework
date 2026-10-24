/* Attach the server-issued CSRF token only to same-origin write requests. */
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return;
    const write = method => !['GET', 'HEAD', 'OPTIONS'].includes((method || 'GET').toUpperCase());
    const local = url => new URL(url, location.href).origin === location.origin;
    const originalFetch = window.fetch;
    window.fetch = (input, init) => {
        const request = new Request(input, init);
        if (local(request.url) && write(request.method)) request.headers.set('X-CSRF-Token', token);
        return originalFetch.call(window, request);
    };
    const open = XMLHttpRequest.prototype.open;
    const send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this.smfCsrf = local(url) && write(method);
        return open.call(this, method, url, ...rest);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (this.smfCsrf) this.setRequestHeader('X-CSRF-Token', token);
        return send.call(this, body);
    };
})();

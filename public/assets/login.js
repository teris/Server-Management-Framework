// Native form submission preserves the server's CSRF checks, errors and redirects.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('login-form');
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    toggle?.addEventListener('click', () => {
        if (!password) return;
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-label', visible ? 'Passwort ausblenden' : 'Passwort anzeigen');
        const icon = toggle.querySelector('i');
        if (icon) icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
    form?.addEventListener('submit', () => {
        const button = document.getElementById('login-btn');
        if (button) { button.disabled = true; button.textContent = 'Anmeldung läuft…'; }
    });
    document.getElementById('email')?.focus();
});

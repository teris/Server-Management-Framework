# Kundenportal

Öffentlicher Einstieg: `/public/index.php`, Verwaltung: `/src/login.php`. Die Anwendung wird aus ihrem Projektwurzelverzeichnis mit den mitgelieferten Webserver-Sperren betrieben; `public/` allein ist derzeit kein vollständiger DocumentRoot.

Registrierung, E-Mail-Bestätigung, erneuter Versand und Passwort-Rücksetzen teilen denselben Bootstrap. GET auf einen Bestätigungslink zeigt ein Formular; erst POST mit CSRF-Token aktiviert das Konto. Neue Konten erhalten keine automatisch angelegten Providerressourcen. Kundendaten und Produkte benötigen ausdrückliche Zuordnungen.

Die [Betriebsanleitung](../docs/pruefung-2026-10-24/BETRIEB-AP10-AP17.md) ist für Installation, SMTP und Upgrade verbindlich. Kein Import alter Beispieldaten, kein Webinstaller und keine Standardkennwörter. Die APIs liegen unter `src/api/`, nicht unter `public/api/`. Ticket-/Statusumfang und weitere öffentliche Grenzen werden in AP20 weitergeführt.

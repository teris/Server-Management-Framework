# 🆘 Support

## 📞 Hilfe bekommen

### GitHub Issues
Für Bug Reports und Feature Requests verwenden Sie bitte [GitHub Issues](https://github.com/teris/server-management-framework/issues).

### GitHub Discussions
Für Fragen, Diskussionen und allgemeine Hilfe verwenden Sie [GitHub Discussions](https://github.com/teris/server-management-framework/discussions).

## 🔧 Häufige Probleme

### Installation
**Problem:** "PHP Parse error: syntax error"
- **Lösung:** Stellen Sie sicher, dass PHP >= 7.4 installiert ist

**Problem:** "Connection refused" bei API-Aufrufen
- **Lösung:** Überprüfen Sie die API-Credentials in `config/config.inc.php`

**Problem:** "Database connection failed"
- **Lösung:** Überprüfen Sie die Datenbankverbindung und führen Sie `database-structure.sql` aus

### API-Probleme
**Problem:** Proxmox API funktioniert nicht
- **Lösung:** Überprüfen Sie API-Token und Node-Namen

**Problem:** ISPConfig API Fehler
- **Lösung:** Überprüfen Sie SOAP-Zugang und Credentials

**Problem:** OVH API Authentifizierung fehlschlägt
- **Lösung:** Erstellen Sie einen neuen Consumer Key unter https://eu.api.ovh.com/createToken/

**Problem** OpenGamePanel API Fehler
- **Lösung** Erstellen Sie einen neuen Token direkt in der Benutzerverwaltung vom OpenGamePanel

## 📚 Ressourcen

### Dokumentation
- **[GitHub Wiki](https://github.com/teris/Server-Management-Framework/wiki)** - Vollständige Dokumentation
- **[FrameWorkShema](FrameWorkShema/)** - HTML-Version der Dokumentation
- **[README.md](README.md)** - Projektübersicht und Installation
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Beiträge zum Projekt
- **[CHANGELOG.md](CHANGELOG.md)** - Versionshistorie

### Externe Links
- [Proxmox VE Dokumentation](https://pve.proxmox.com/wiki/Documentation)
- [ISPConfig Dokumentation](https://www.ispconfig.org/documentation/)
- [OVH API Dokumentation](https://docs.ovh.com/gb/en/api/)
- [OpenGamePanel API Dokumentation](https://github.com/OpenGamePanel/OGP-Website/blob/master/ogp_api.php)

## 🧪 Debugging

### Debug-Modus aktivieren
```php
// In config/config.inc.php
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
```

### Debug-Tools verwenden
```bash
# Debug-Interface öffnen
php debug.php

# API-Tests ausführen
php auth_handler.php

# Spezifische Debug-Tests
php debug/index.php
```

### Logs überprüfen
- **Error Log:** `logs/error.log`
- **API Log:** `logs/api.log`
- **Activity Log:** Über das Admin Dashboard

## 🐛 Bug Report Template

Verwenden Sie dieses Template für Bug Reports:

```markdown
## Beschreibung
Kurze Beschreibung des Problems

## Schritte zur Reproduktion
1. Gehen Sie zu '...'
2. Klicken Sie auf '...'
3. Scrollen Sie zu '...'
4. Fehler tritt auf

## Erwartetes Verhalten
Was sollte passieren?

## Tatsächliches Verhalten
Was passiert stattdessen?

## Environment
- PHP Version: [z.B. 8.1]
- OS: [z.B. Ubuntu 22.04]
- Browser: [z.B. Chrome 120]
- Framework Version: [z.B. 2.9.5]

## Zusätzliche Informationen
Screenshots, Logs, etc.
```

## 💡 Feature Request Template

```markdown
## Beschreibung
Beschreibung des gewünschten Features

## Begründung
Warum ist dieses Feature nützlich?

## Beispiele
Beispiele für die Verwendung

## Mockups (optional)
Screenshots oder Skizzen
```

## 🤝 Community

### Beitragen
Möchten Sie zum Projekt beitragen? Lesen Sie [CONTRIBUTING.md](CONTRIBUTING.md).

### Feedback
Ihr Feedback ist wichtig! Teilen Sie Ihre Erfahrungen und Verbesserungsvorschläge mit uns.

---

**Vielen Dank für Ihr Interesse am Server Management Framework! 🚀** 

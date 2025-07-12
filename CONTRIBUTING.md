# 🤝 Contributing zum Server Management Framework

Vielen Dank für Ihr Interesse, zu diesem Projekt beizutragen! 🎉

## 📋 Inhaltsverzeichnis

- [Erste Schritte](#-erste-schritte)
- [Development Setup](#-development-setup)
- [Contribution Guidelines](#-contribution-guidelines)
- [Code Style](#-code-style)
- [Testing](#-testing)
- [Pull Request Process](#-pull-request-process)
- [Was wir suchen](#-was-wir-suchen)

## 🚀 Erste Schritte

1. **Fork** das Repository auf GitHub
2. **Clone** Ihren Fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/server-management-framework.git
   cd server-management-framework
   ```
3. **Branch** für Ihr Feature erstellen:
   ```bash
   git checkout -b feature/amazing-feature
   ```
4. **Remote** zum Original-Repository hinzufügen:
   ```bash
   git remote add upstream https://github.com/teris/server-management-framework.git
   ```

## ⚙️ Development Setup

### Voraussetzungen
- PHP >= 7.4
- MySQL >= 5.7 oder MariaDB >= 10.2
- Composer (optional)
- Git

### Setup-Schritte
```bash
# Dependencies installieren (falls vorhanden)
composer install

# Konfiguration kopieren
cp config/config.inc.php.example config/config.inc.php

# Datenbank einrichten
mysql -u root -p < database-structure.sql

# API-Tests ausführen
php auth_handler.php

# Debug-Modus testen
php debug.php
```

## 🎯 Contribution Guidelines

### Allgemeine Richtlinien

- **Ein Issue erstellen** bevor Sie mit der Entwicklung beginnen
- **Kleine, fokussierte Commits** bevorzugen
- **Dokumentation aktualisieren** bei neuen Features
- **Tests schreiben** für neue Funktionalität
- **Backward Compatibility** wahren

### Issue Guidelines

**Bug Reports:**
- Klare Beschreibung des Problems
- Schritte zur Reproduktion
- Erwartetes vs. tatsächliches Verhalten
- Environment-Informationen (PHP Version, OS, etc.)
- Relevante Logs oder Screenshots

**Feature Requests:**
- Beschreibung des gewünschten Features
- Begründung für das Feature
- Beispiele für die Verwendung
- Mockups oder Skizzen (falls relevant)

## 💻 Code Style

### PHP Standards
- **PSR-12** Coding Standard befolgen
- **Aussagekräftige Variablennamen** verwenden
- **Kommentare** für komplexe Logik
- **Type Hints** verwenden wo möglich
- **DocBlocks** für alle öffentlichen Methoden

### Beispiel für korrekten Code-Style
```php
<?php
declare(strict_types=1);

namespace ServerManagement\Core;

/**
 * Service Manager für API-Operationen
 * 
 * @package ServerManagement\Core
 * @author Your Name <your.email@example.com>
 */
class ServiceManager
{
    private ProxmoxAPI $proxmoxAPI;
    private ISPConfigAPI $ispconfigAPI;
    private OVHAPI $ovhAPI;

    public function __construct()
    {
        $this->proxmoxAPI = new ProxmoxAPI();
        $this->ispconfigAPI = new ISPConfigAPI();
        $this->ovhAPI = new OVHAPI();
    }

    /**
     * Erstellt eine neue Proxmox VM
     * 
     * @param array $vmData VM-Konfigurationsdaten
     * @return array|false API-Response oder false bei Fehler
     */
    public function createProxmoxVM(array $vmData): array|false
    {
        try {
            return $this->proxmoxAPI->createVM($vmData);
        } catch (Exception $e) {
            error_log('VM creation failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

### JavaScript Standards
- **ES6+** Syntax verwenden
- **const/let** statt var
- **Arrow Functions** wo möglich
- **Template Literals** für Strings
- **Destructuring** für Objekte

### CSS Standards
- **Bootstrap 5.3.2** Klassen bevorzugen
- **Custom CSS** nur wenn nötig
- **Responsive Design** berücksichtigen
- **CSS-Variablen** für Theme-Farben

## 📝 Commit Messages

Verwenden Sie aussagekräftige Commit-Messages im **Conventional Commits** Format:

```
feat: Add VPS monitoring functionality
fix: Resolve OVH API authentication issue
docs: Update API documentation
test: Add unit tests for ServiceManager
refactor: Improve error handling in ProxmoxAPI
style: Update CSS for better mobile responsiveness
perf: Optimize database queries
ci: Add GitHub Actions workflow
```

### Commit Message Struktur
```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat`: Neue Features
- `fix`: Bug Fixes
- `docs`: Dokumentation
- `style`: Formatierung
- `refactor`: Code-Refactoring
- `test`: Tests
- `chore`: Wartungsarbeiten

## 🧪 Testing

### API Tests
```bash
# Alle APIs testen
php auth_handler.php

# Einzelne APIs testen
php auth_handler.php proxmox
php auth_handler.php ispconfig
php auth_handler.php ovh
```

### Debug Tests
```bash
# Debug-Interface
php debug.php

# Spezifische Debug-Tests
php debug/ispconfig_debug.php
php debug/ovh_failover_mac.php
php debug/soap_test.php
```

### Unit Tests (falls implementiert)
```bash
# PHPUnit Tests
./vendor/bin/phpunit tests/

# Spezifische Test-Suite
./vendor/bin/phpunit tests/UnitTests/ServiceManagerTest.php
```

## 🔄 Pull Request Process

### Vor dem PR
- [ ] Code folgt PSR-12 Standard
- [ ] Alle Tests bestehen
- [ ] Neue Features haben Tests
- [ ] Dokumentation ist aktualisiert
- [ ] Keine sensiblen Daten im Code
- [ ] Error Handling implementiert
- [ ] Backward Compatibility gewahrt

### PR erstellen
1. **Branch aktualisieren:**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Tests ausführen:**
   ```bash
   php auth_handler.php
   # Weitere relevante Tests
   ```

3. **PR beschreiben:**
   - Klare Beschreibung der Änderungen
   - Referenz zu Issues
   - Screenshots (falls UI-Änderungen)
   - Checkliste der Änderungen

### PR Template
```markdown
## Beschreibung
Kurze Beschreibung der Änderungen

## Änderungen
- [ ] Feature A hinzugefügt
- [ ] Bug B behoben
- [ ] Dokumentation aktualisiert

## Tests
- [ ] API-Tests bestanden
- [ ] Debug-Tests bestanden
- [ ] Neue Tests hinzugefügt

## Screenshots (falls relevant)
[Fügen Sie Screenshots hier ein]

## Checkliste
- [ ] Code folgt PSR-12
- [ ] Keine sensiblen Daten
- [ ] Dokumentation aktualisiert
- [ ] Tests hinzugefügt/aktualisiert

Closes #123
```

## 🎁 Was wir suchen

### Einfache Beiträge (Good First Issues)
- [ ] Typos in Dokumentation korrigieren
- [ ] Beispiele hinzufügen
- [ ] UI/UX Verbesserungen
- [ ] Code-Kommentare hinzufügen
- [ ] README aktualisieren

### Mittlere Beiträge
- [ ] Neue API-Endpunkte implementieren
- [ ] Performance-Optimierungen
- [ ] Bessere Fehlerbehandlung
- [ ] Unit Tests schreiben
- [ ] Debug-Tools erweitern

### Komplexe Beiträge
- [ ] Neue Service-Integrationen
- [ ] Backup/Restore Features
- [ ] Multi-User Support
- [ ] Plugin System
- [ ] REST API für externe Integration

## 📚 Ressourcen

### Dokumentation
- **[README.md](README.md)** - Projektübersicht
- **[how_to_use.md](how_to_use.md)** - API-Dokumentation
- **[BOOTSTRAP_MIGRATION.md](BOOTSTRAP_MIGRATION.md)** - UI-Migration

### Externe Ressourcen
- [PSR-12 Coding Standards](https://www.php-fig.org/psr/psr-12/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Bootstrap 5.3.2 Documentation](https://getbootstrap.com/docs/5.3/)
- [jQuery 3.7.1 Documentation](https://api.jquery.com/)

## 🆘 Hilfe benötigt?

- **GitHub Issues** für Bug Reports und Feature Requests
- **GitHub Discussions** für Fragen und Diskussionen
- **Pull Request Reviews** für Code-Feedback

## 🙏 Danksagung

Vielen Dank für Ihren Beitrag zum Server Management Framework! Jeder Beitrag, egal wie klein, hilft dabei, das Projekt zu verbessern.

---

**Happy Contributing! 🚀**

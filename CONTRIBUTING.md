# Contributing zum Server Management Framework

Vielen Dank für Ihr Interesse, zu diesem Projekt beizutragen! 🎉

## 🚀 Erste Schritte

1. **Fork** das Repository
2. **Clone** Ihren Fork:
   ```bash
   git clone https://github.com/teris/server-management-framework.git
   ```
3. **Branch** für Ihr Feature erstellen:
   ```bash
   git checkout -b feature/amazing-feature
   ```

## 📋 Development Setup

```bash
# Dependencies installieren
composer install
# Tests ausführen
php auth.php
```

## 🎯 Contribution Guidelines

### Code Style

- **PSR-12** Coding Standard befolgen
- **Aussagekräftige Variablennamen** verwenden
- **Kommentare** für komplexe Logik
- **Type Hints** verwenden wo möglich

### Commit Messages

Verwenden Sie aussagekräftige Commit-Messages:

```
feat: Add VPS monitoring functionality
fix: Resolve OVH API authentication issue
docs: Update API documentation
test: Add unit tests for ServiceManager
refactor: Improve error handling in ProxmoxAPI
```

### Pull Request Process

1. **Tests** müssen alle bestehen
2. **Dokumentation** aktualisieren
3. **Changelog** erweitern
4. **Code Review** abwarten

### Was wir suchen

- 🐛 **Bug Fixes**
- ✨ **Neue Features** 
- 📚 **Dokumentation**
- 🧪 **Tests**
- 🎨 **UI/UX Verbesserungen**

## 🧪 Testing

```bash
# API Tests
php auth.php

# Unit Tests
php tests/UnitTests/ServiceManagerTest.php
```

## 📝 Code Review Checklist

- [ ] Code folgt PSR-12 Standard
- [ ] Alle Tests bestehen
- [ ] Neue Features haben Tests
- [ ] Dokumentation ist aktualisiert
- [ ] Keine sensiblen Daten im Code
- [ ] Error Handling implementiert

## 🎁 Was Sie beitragen können

### Einfache Beiträge
- Typos in Dokumentation korrigieren
- Beispiele hinzufügen
- UI/UX Verbesserungen

### Mittlere Beiträge
- Neue API-Endpunkte implementieren
- Performance-Optimierungen
- Bessere Fehlerbehandlung

### Komplexe Beiträge
- Neue Service-Integrationen
- Backup/Restore Features
- Multi-User Support

Vielen Dank für Ihren Beitrag! 🙏

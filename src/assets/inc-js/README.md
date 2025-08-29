# JavaScript-Dateien für inc-Ordner

Dieser Ordner enthält alle JavaScript-Dateien, die zuvor direkt in den PHP-Dateien im `/src/inc/` Ordner eingebettet waren.

## Übersicht der Dateien

### Hauptdateien
- **`inc-js-loader.js`** - Zentrale Datei, die alle anderen JavaScript-Dateien lädt
- **`users.js`** - JavaScript-Funktionalität für die Benutzerverwaltung
- **`createuser.js`** - JavaScript-Funktionalität für die Benutzererstellung
- **`settings.js`** - JavaScript-Funktionalität für die Einstellungen
- **`resources.js`** - JavaScript-Funktionalität für die Ressourcenverwaltung
- **`system.js`** - JavaScript-Funktionalität für die Systemverwaltung
- **`domain-settings.js`** - JavaScript-Funktionalität für Domain-Einstellungen
- **`domain-registrations.js`** - JavaScript-Funktionalität für Domain-Registrierungen
- **`logs.js`** - JavaScript-Funktionalität für die Protokollverwaltung

## Verwendung

### 1. Einbinden des Loaders
Fügen Sie den folgenden Code in den `<head>` Bereich Ihrer HTML-Seiten ein:

```html
<script src="assets/inc-js/inc-js-loader.js"></script>
```

### 2. Automatisches Laden
Der Loader erkennt automatisch, auf welcher Seite Sie sich befinden und lädt die entsprechenden JavaScript-Dateien.

### 3. Manuelles Laden
Sie können auch manuell spezifische JavaScript-Dateien laden:

```javascript
// Einzelne Datei laden
IncJsLoader.loadScript('assets/inc-js/users.js');

// CSS-Datei laden
IncJsLoader.loadCSS('assets/css/style.css');
```

## Vorteile der Auslagerung

1. **Bessere Wartbarkeit** - JavaScript-Code ist von PHP-Code getrennt
2. **Wiederverwendbarkeit** - JavaScript-Funktionen können auf mehreren Seiten verwendet werden
3. **Caching** - Browser können JavaScript-Dateien zwischenspeichern
4. **Debugging** - Einfachere Fehlersuche in JavaScript-Code
5. **Modularität** - Klare Trennung der Funktionalitäten

## Anpassungen

### Neue JavaScript-Datei hinzufügen
1. Erstellen Sie eine neue `.js` Datei in diesem Ordner
2. Fügen Sie die entsprechende Logik in `inc-js-loader.js` hinzu
3. Aktualisieren Sie die `getCurrentPage()` Funktion

### Bestehende Datei bearbeiten
- Alle JavaScript-Funktionen sind in den entsprechenden `.js` Dateien verfügbar
- Änderungen werden automatisch auf allen Seiten übernommen, die diese Datei verwenden

## Abhängigkeiten

Alle JavaScript-Dateien setzen voraus, dass folgende Bibliotheken geladen sind:
- jQuery (für AJAX-Funktionalität)
- Bootstrap (für UI-Komponenten)

## Fehlerbehebung

### JavaScript wird nicht geladen
1. Überprüfen Sie, ob der Loader korrekt eingebunden ist
2. Prüfen Sie die Browser-Konsole auf Fehlermeldungen
3. Stellen Sie sicher, dass der Pfad zu den JavaScript-Dateien korrekt ist

### Funktionen funktionieren nicht
1. Überprüfen Sie, ob alle erforderlichen HTML-Elemente vorhanden sind
2. Stellen Sie sicher, dass jQuery und Bootstrap geladen sind
3. Prüfen Sie die Browser-Konsole auf JavaScript-Fehler

## Migration von eingebetteten Scripts

Um von eingebetteten `<script>` Tags zu den neuen Dateien zu wechseln:

1. **Entfernen Sie den `<script>` Block** aus der PHP-Datei
2. **Fügen Sie den Loader ein** in den HTML-Header
3. **Testen Sie die Funktionalität** - alles sollte weiterhin funktionieren

### Beispiel Migration

**Vorher (in PHP-Datei):**
```php
<script>
function myFunction() {
    // JavaScript-Code
}
</script>
```

**Nachher:**
- JavaScript-Code in entsprechende `.js` Datei verschieben
- `<script>` Block aus PHP-Datei entfernen
- Loader in HTML-Header einbinden

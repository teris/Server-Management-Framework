## Projektstruktur
- `/src/` → Backend (Einstieg: index.php, Includes + Module-Handling).
- `/public/` → Frontend (jede Datei einzeln aufrufbar, benötigt Standard-Includes).
- `/config/` → zentrale Konfigurationsdateien.
- `framework.php` → zentrales Framework, definiert Standard-Variablen und lädt Datenbanksystem.

## Basis
- `framework.php` ist das zentrale Framework:
  - Lädt `config/conf.inc.php` (Master-Konfiguration).
  - Lädt `src/core/DatabaseManager.php` (Datenbanksystem).
  - Definiert Variablen und Standard-Aktionen, die in 90% aller Fälle benötigt werden.

## Backend (`/src/`)
- Einstieg über `src/index.php`.
- Module und weitere Komponenten werden über `include` eingebunden.
- Stark von `framework.php` abhängig.

## Frontend (`/public/`)
- Keine zentrale Steuerung über Includes.
- Jede Datei wird einzeln geladen.
- Globale Variablen stammen trotzdem aus `framework.php`.

## Wichtig
- Master-Konfiguration + Datenbanksystem **immer über `framework.php`**.
- Einheitliche Variablen sind projektweit verfügbar und dort definiert.

---

## Backend Details

### Einstiegspunkt
- `src/index.php` ist der zentrale Einstieg.

### Basis-Includes (immer geladen)
| Datei                    | Zweck                           |
|---------------------------|--------------------------------|
| `core/AdminCore.php`      | AdminCore (Dashboard, zentrale Verwaltung) |
| `core/LanguageManager.php`| Sprachverwaltung (Singleton)   |
| `module/ModuleBase.php`   | Modul-Basis, Loader            |
| `sys.conf.php`            | Framework-Auswahl (`framework.php` oder `core/DatabaseOnlyFramework.php`) |
| `auth_handler.php`        | Authentifizierung              |

---

### Statische Includes
- Steuerung über `switch($option)` in `index.php`.
- Feste Includes aus `inc/`:
  - `inc/admin.php`
  - `inc/module.php`
  - `inc/settings.php`
  - `inc/profile.php`
  - `inc/logs.php`
  - `inc/resources.php`
  - `inc/users.php`
  - `inc/createuser.php`
  - `inc/domain-registrations.php`
  - `inc/domain-settings.php`
  - `inc/system.php`
- Besonderheit `case 'users'`: zusätzlich POST-Aktions-Handling (z. B. Aktivieren, Zusammenführen, Berechtigungen ändern, Passwort-Update, Session verlängern).


## Frontend Details

### Basis-Includes
Jede Frontend-Datei muss folgende Includes einbinden:
1. `../src/sys.conf.php`
2. `../framework.php`
3. `../src/core/LanguageManager.php`
4. `../src/core/ActivityLogger.php`

### Sprache
- Sprachverwaltung über:
  ```php
  $lang = LanguageManager::getInstance();
  $currentLang = $lang->getCurrentLanguage();
---

### Dynamisches Plugin-System
- Aktiv bei `case 'modules'`.
- `Module.php` prüft `$_GET['mod']`.
- Schema: `/module/<mod_key>/templates/main.php`.
- Datei vorhanden → Modul wird geladen.  
- Datei fehlt → Fehlermeldung *"Module template not found"*.  
- Ohne `$_GET['mod']` → Hinweis *"Bitte wählen Sie ein Modul aus den Tabs oben aus"*.  

---

*To be extended:* Neue Module, Includes oder Framework-Komponenten werden hier ergänzt.

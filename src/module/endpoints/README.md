# Endpoints-Modul - Mehrsprachige Dokumentation

## Übersicht

Das Endpoints-Modul ist ein API-Tester für Entwickler und Admins. Es ermöglicht das Testen einzelner API-Endpunkte der verschiedenen Services im Framework. Es ist vollständig mehrsprachig implementiert und unterstützt Deutsch und Englisch.

## Funktionen

### 🔌 API-Endpunkt-Testing
- Testen von Proxmox API-Endpunkten
- Testen von ISPConfig API-Endpunkten
- Testen von OVH API-Endpunkten
- Testen von Virtual MAC API-Endpunkten
- Testen von Database API-Endpunkten
- Testen von Email API-Endpunkten
- Testen von System-Endpunkten

### 🔧 Custom Endpoint Testing
- Benutzerdefinierte Endpunkt-Tests
- JSON-Parameter-Unterstützung
- Dynamische Modul-Auswahl
- Echtzeit-Response-Anzeige

### 📊 Statistiken
- Anzahl verfügbarer Endpunkte
- Anzahl aktiver Module
- Übersicht über API-Coverage

### 🌍 Mehrsprachigkeit
- Vollständige deutsche und englische Übersetzungen
- Dynamische Sprachumschaltung
- Fallback auf Deutsch bei fehlenden Übersetzungen

## Sprachdateien

### Struktur
```
module/endpoints/lang/
├── de.xml          # Deutsche Übersetzungen
└── en.xml          # Englische Übersetzungen
```

### Wichtige Übersetzungsschlüssel

#### Allgemeine UI-Elemente
- `module_title` - Modul-Titel
- `api_endpoints_tester` - API Endpoints Tester
- `test_api_endpoints` - Testen Sie einzelne API-Endpunkte
- `module` - Module
- `select_module` - Module wählen
- `action` - Action
- `parameters` - Parameters (JSON)
- `test_endpoint` - Test Endpoint

#### Proxmox API
- `proxmox_api_endpoints` - Proxmox API Endpoints
- `load_nodes` - Nodes laden
- `load_storages` - Storages laden
- `vm_config` - VM Config
- `vm_status` - VM Status
- `clone_vm` - VM Klonen

#### ISPConfig API
- `ispconfig_api_endpoints` - ISPConfig API Endpoints
- `load_clients` - Clients laden
- `server_config` - Server Config
- `website_details` - Website Details
- `ftp_user_test` - FTP User Test

#### OVH API
- `ovh_api_endpoints` - OVH API Endpoints
- `domain_zone` - Domain Zone
- `dns_records` - DNS Records
- `vps_ips` - VPS IPs
- `ip_details` - IP Details
- `vps_control` - VPS Control
- `create_dns_record` - DNS Record erstellen
- `refresh_dns_zone` - DNS Zone aktualisieren
- `failover_ips` - Failover IPs

#### Virtual MAC API
- `virtual_mac_api_endpoints` - Virtual MAC API Endpoints
- `all_virtual_macs` - Alle Virtual MACs
- `dedicated_servers` - Dedicated Servers
- `mac_details` - MAC Details
- `create_virtual_mac` - Virtual MAC erstellen
- `assign_ip` - IP zuweisen
- `create_reverse_dns` - Reverse DNS erstellen

#### Database API
- `database_api_endpoints` - Database API Endpoints
- `all_databases` - Alle Datenbanken
- `create_database` - DB erstellen
- `delete_database` - DB löschen

#### Email API
- `email_api_endpoints` - Email API Endpoints
- `all_emails` - Alle E-Mails
- `create_email` - Email erstellen
- `delete_email` - Email löschen

#### System Endpoints
- `system_endpoints` - System Endpoints
- `activity_log` - Activity Log
- `session_heartbeat` - Session Heartbeat

#### Response-Handling
- `endpoint_response` - Endpoint Response
- `success` - Success
- `error` - Error
- `copy` - Kopieren
- `response_copied` - Response kopiert!
- `copy_failed` - Kopieren fehlgeschlagen
- `testing` - Testing
- `invalid_json` - Invalid JSON in parameters

#### Statistiken
- `total_endpoints` - Total Endpoints
- `active_modules` - Active Modules

## Verwendung

### PHP-Code

```php
// Module instanziieren
$module = new EndpointsModule();

// Übersetzungen abrufen
$translations = $module->tMultiple([
    'module_title',
    'api_endpoints_tester',
    'proxmox_api_endpoints'
]);

// Statistiken abrufen
$stats = $module->getStats();

// Template rendern
$content = $module->getContent();
```

### JavaScript

```javascript
// Übersetzungen laden
endpointsModule.loadTranslations();

// Übersetzung verwenden
const message = endpointsModule.t('response_copied');

// Endpoint testen
await testEndpoint('proxmox', 'get_proxmox_nodes');

// Endpoint mit Parameter testen
await testEndpointWithParam('proxmox', 'get_proxmox_storages', 'node', 'pve');

// Endpoint mit mehreren Parametern testen
await testEndpointWithParams('proxmox', 'get_vm_config', {
    node: 'pve',
    vmid: '100'
});
```

### AJAX-Endpunkte

#### Übersetzungen abrufen
```javascript
const result = await fetch('?module=endpoints&action=get_translations');
const translations = await result.json();
```

#### Statistiken abrufen
```javascript
const result = await fetch('?module=endpoints&action=get_stats');
const stats = await result.json();
```

## Template-Struktur

Das Template verwendet Bootstrap-Klassen für ein modernes Design:

```html
<div class="card">
    <div class="card-header">
        <h2>🔌 <?php echo $translations['api_endpoints_tester']; ?></h2>
    </div>
    <div class="card-body">
        <!-- Endpoint-Buttons -->
    </div>
</div>
```

## Verfügbare Endpunkte

### Proxmox API
- `get_proxmox_nodes` - Alle Nodes abrufen
- `get_proxmox_storages` - Storages eines Nodes abrufen
- `get_vm_config` - VM-Konfiguration abrufen
- `get_vm_status` - VM-Status abrufen
- `clone_vm` - VM klonen

### ISPConfig API
- `get_ispconfig_clients` - Alle Clients abrufen
- `get_ispconfig_server_config` - Server-Konfiguration abrufen
- `get_website_details` - Website-Details abrufen
- `create_ftp_user` - FTP-User erstellen

### OVH API
- `get_ovh_domain_zone` - Domain-Zone abrufen
- `get_ovh_dns_records` - DNS-Records abrufen
- `get_vps_ips` - VPS-IPs abrufen
- `get_vps_ip_details` - IP-Details abrufen
- `control_ovh_vps` - VPS steuern
- `create_dns_record` - DNS-Record erstellen
- `refresh_dns_zone` - DNS-Zone aktualisieren
- `get_ovh_failover_ips` - Failover-IPs abrufen

### Virtual MAC API
- `get_all_virtual_macs` - Alle Virtual MACs abrufen
- `get_dedicated_servers` - Dedicated Server abrufen
- `get_virtual_mac_details` - MAC-Details abrufen
- `create_virtual_mac` - Virtual MAC erstellen
- `assign_ip_to_virtual_mac` - IP zuweisen
- `create_reverse_dns` - Reverse DNS erstellen

### Database API
- `get_all_databases` - Alle Datenbanken abrufen
- `create_database` - Datenbank erstellen
- `delete_database` - Datenbank löschen

### Email API
- `get_all_emails` - Alle E-Mails abrufen
- `create_email` - E-Mail erstellen
- `delete_email` - E-Mail löschen

### System API
- `get_activity_log` - Aktivitäts-Log abrufen
- `heartbeat` - Session-Heartbeat

## Konfiguration

### Sprachauswahl
Die Sprache wird in `sys.conf.php` konfiguriert:

```php
// Standardsprache
$_SESSION['language'] = 'de';

// Verfügbare Sprachen
$available_languages = ['de', 'en'];
```

### Fallback-Verhalten
- Primär: Gewählte Sprache
- Sekundär: Deutsch (Standard)
- Tertiär: Übersetzungsschlüssel

## Best Practices

### Neue Übersetzungen hinzufügen

1. **Sprachdatei erweitern**
```xml
<new_endpoint>Neuer Endpoint</new_endpoint>
```

2. **Module-Code aktualisieren**
```php
$translation = $this->t('new_endpoint');
```

3. **JavaScript erweitern**
```javascript
const message = endpointsModule.t('new_endpoint');
```

### Endpoint-Testing

```javascript
// Einfacher Test
await testEndpoint('module', 'action');

// Test mit Parameter
await testEndpointWithParam('module', 'action', 'param', 'value');

// Test mit mehreren Parametern
await testEndpointWithParams('module', 'action', {
    param1: 'value1',
    param2: 'value2'
});
```

### Response-Handling

```javascript
// Response anzeigen
endpointsModule.displayResult(module, action, result);

// Response kopieren
endpointsModule.copyResponse();
```

## Fehlerbehandlung

### Übersetzungsfehler
- Fehlende Schlüssel werden als Schlüssel selbst zurückgegeben
- Ungültige XML-Dateien werden ignoriert
- Fallback auf Standardsprache

### API-Fehler
```javascript
try {
    const result = await testEndpoint('module', 'action');
    // Erfolgreich
} catch (error) {
    // Fehlerbehandlung
    console.error('API Error:', error);
}
```

## Testing

### Testskript ausführen
```bash
php debug/test_endpoints_multilingual.php
```

### Tests umfassen
- ✅ Sprachdateien-Validierung
- ✅ LanguageManager-Tests
- ✅ Module-Übersetzungen
- ✅ AJAX-Funktionalität
- ✅ Template-Rendering
- ✅ Fehlerbehandlung
- ✅ Endpoint-Testing

## Wartung

### Cache leeren
```php
// LanguageManager-Cache leeren
$language_manager = new LanguageManager();
$language_manager->clearCache();
```

### Logs prüfen
```php
// Übersetzungsfehler loggen
$this->log('Translation missing: ' . $key, 'WARNING');
```

## Erweiterungen

### Neue Sprache hinzufügen

1. Sprachdatei erstellen: `module/endpoints/lang/fr.xml`
2. In `sys.conf.php` hinzufügen: `$available_languages[] = 'fr';`
3. Übersetzungen vervollständigen

### Neue Endpunkte hinzufügen

1. Übersetzungsschlüssel definieren
2. Button im Template hinzufügen
3. JavaScript-Funktion implementieren
4. Tests schreiben

## Support

Bei Fragen oder Problemen:
- Dokumentation prüfen
- Testskript ausführen
- Logs analysieren
- GitHub-Issue erstellen 
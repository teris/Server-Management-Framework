# Migrationsleitfaden - Server Management System

## Übersicht

Dieser Leitfaden beschreibt die Migration von der ursprünglichen Datenbankstruktur zur optimierten Version 2.0.

## Vorbereitung

### 1. System-Backup erstellen
```bash
# Vollständiges System-Backup
tar -czf system_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/project

# Datenbank-Backup
mysqldump -u root -p server_management > database_backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Aktuelle Struktur dokumentieren
```sql
-- Tabellen auflisten
SHOW TABLES;

-- Struktur dokumentieren
SHOW CREATE TABLE users;
SHOW CREATE TABLE customers;
-- ... für alle Tabellen
```

## Migrationsschritte

### Schritt 1: Daten exportieren

#### Verwendete Tabellen exportieren
```sql
-- Admin-Benutzer
SELECT * FROM users INTO OUTFILE '/tmp/users_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden
SELECT * FROM customers INTO OUTFILE '/tmp/customers_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzerberechtigungen
SELECT * FROM user_permissions INTO OUTFILE '/tmp/user_permissions_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzersitzungen
SELECT * FROM user_sessions INTO OUTFILE '/tmp/user_sessions_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Login-Versuche
SELECT * FROM login_attempts INTO OUTFILE '/tmp/login_attempts_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Aktivitätsprotokoll
SELECT * FROM activity_log INTO OUTFILE '/tmp/activity_log_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Support-Tickets
SELECT * FROM support_tickets INTO OUTFILE '/tmp/support_tickets_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Ticket-Antworten
SELECT * FROM ticket_replies INTO OUTFILE '/tmp/ticket_replies_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kontaktnachrichten
SELECT * FROM contact_messages INTO OUTFILE '/tmp/contact_messages_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Domain-Registrierungen
SELECT * FROM domain_registrations INTO OUTFILE '/tmp/domain_registrations_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Domains
SELECT * FROM domains INTO OUTFILE '/tmp/domains_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Verifizierungstoken
SELECT * FROM verification_tokens INTO OUTFILE '/tmp/verification_tokens_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden-Remember-Token
SELECT * FROM customer_remember_tokens INTO OUTFILE '/tmp/customer_remember_tokens_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden-Login-Logs
SELECT * FROM customer_login_logs INTO OUTFILE '/tmp/customer_login_logs_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzeraktivitäten
SELECT * FROM user_activities INTO OUTFILE '/tmp/user_activities_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Virtuelle Maschinen
SELECT * FROM vms INTO OUTFILE '/tmp/vms_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Websites
SELECT * FROM websites INTO OUTFILE '/tmp/websites_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Datenbanken
SELECT * FROM sm_databases INTO OUTFILE '/tmp/sm_databases_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- E-Mail-Konten
SELECT * FROM email_accounts INTO OUTFILE '/tmp/email_accounts_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- SSL-Zertifikate
SELECT * FROM ssl_certificates INTO OUTFILE '/tmp/ssl_certificates_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- IP-Adressen
SELECT * FROM ips INTO OUTFILE '/tmp/ips_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- API-Zugangsdaten
SELECT * FROM api_credentials INTO OUTFILE '/tmp/api_credentials_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Module
SELECT * FROM modules INTO OUTFILE '/tmp/modules_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Systemeinstellungen
SELECT * FROM system_settings INTO OUTFILE '/tmp/system_settings_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Einstellungen
SELECT * FROM settings INTO OUTFILE '/tmp/settings_export.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';
```

### Schritt 2: Alte Datenbank sichern und entfernen

```sql
-- Datenbank umbenennen (als Backup)
RENAME DATABASE server_management TO server_management_old;

-- Neue Datenbank erstellen
CREATE DATABASE server_management DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE server_management;
```

### Schritt 3: Neue Struktur installieren

```bash
# MySQL/MariaDB
mysql -u root -p server_management < database-structure-optimized.sql

# PostgreSQL
psql -U postgres -d server_management -f database-structure-postgresql.sql

# SQLite
sqlite3 server_management.db < database-structure-sqlite.sql
```

### Schritt 4: Daten importieren

```sql
-- Admin-Benutzer
LOAD DATA INFILE '/tmp/users_export.csv'
INTO TABLE users
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden
LOAD DATA INFILE '/tmp/customers_export.csv'
INTO TABLE customers
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzerberechtigungen
LOAD DATA INFILE '/tmp/user_permissions_export.csv'
INTO TABLE user_permissions
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzersitzungen
LOAD DATA INFILE '/tmp/user_sessions_export.csv'
INTO TABLE user_sessions
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Login-Versuche
LOAD DATA INFILE '/tmp/login_attempts_export.csv'
INTO TABLE login_attempts
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Aktivitätsprotokoll
LOAD DATA INFILE '/tmp/activity_log_export.csv'
INTO TABLE activity_log
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Support-Tickets
LOAD DATA INFILE '/tmp/support_tickets_export.csv'
INTO TABLE support_tickets
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Ticket-Antworten
LOAD DATA INFILE '/tmp/ticket_replies_export.csv'
INTO TABLE ticket_replies
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kontaktnachrichten
LOAD DATA INFILE '/tmp/contact_messages_export.csv'
INTO TABLE contact_messages
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Domain-Registrierungen
LOAD DATA INFILE '/tmp/domain_registrations_export.csv'
INTO TABLE domain_registrations
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Domains
LOAD DATA INFILE '/tmp/domains_export.csv'
INTO TABLE domains
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Verifizierungstoken
LOAD DATA INFILE '/tmp/verification_tokens_export.csv'
INTO TABLE verification_tokens
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden-Remember-Token
LOAD DATA INFILE '/tmp/customer_remember_tokens_export.csv'
INTO TABLE customer_remember_tokens
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Kunden-Login-Logs
LOAD DATA INFILE '/tmp/customer_login_logs_export.csv'
INTO TABLE customer_login_logs
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Benutzeraktivitäten
LOAD DATA INFILE '/tmp/user_activities_export.csv'
INTO TABLE user_activities
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Virtuelle Maschinen
LOAD DATA INFILE '/tmp/vms_export.csv'
INTO TABLE vms
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Websites
LOAD DATA INFILE '/tmp/websites_export.csv'
INTO TABLE websites
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Datenbanken
LOAD DATA INFILE '/tmp/sm_databases_export.csv'
INTO TABLE sm_databases
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- E-Mail-Konten
LOAD DATA INFILE '/tmp/email_accounts_export.csv'
INTO TABLE email_accounts
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- SSL-Zertifikate
LOAD DATA INFILE '/tmp/ssl_certificates_export.csv'
INTO TABLE ssl_certificates
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- IP-Adressen
LOAD DATA INFILE '/tmp/ips_export.csv'
INTO TABLE ips
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- API-Zugangsdaten
LOAD DATA INFILE '/tmp/api_credentials_export.csv'
INTO TABLE api_credentials
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Module
LOAD DATA INFILE '/tmp/modules_export.csv'
INTO TABLE modules
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Systemeinstellungen
LOAD DATA INFILE '/tmp/system_settings_export.csv'
INTO TABLE system_settings
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Einstellungen
LOAD DATA INFILE '/tmp/settings_export.csv'
INTO TABLE settings
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';
```

### Schritt 5: Auto-Increment Werte anpassen

```sql
-- Auto-Increment Werte für alle Tabellen anpassen
ALTER TABLE users AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM users);
ALTER TABLE customers AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM customers);
ALTER TABLE user_permissions AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM user_permissions);
ALTER TABLE user_sessions AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM user_sessions);
ALTER TABLE login_attempts AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM login_attempts);
ALTER TABLE activity_log AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM activity_log);
ALTER TABLE user_activities AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM user_activities);
ALTER TABLE support_tickets AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM support_tickets);
ALTER TABLE ticket_replies AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM ticket_replies);
ALTER TABLE contact_messages AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM contact_messages);
ALTER TABLE domain_registrations AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM domain_registrations);
ALTER TABLE domains AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM domains);
ALTER TABLE verification_tokens AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM verification_tokens);
ALTER TABLE customer_remember_tokens AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM customer_remember_tokens);
ALTER TABLE customer_login_logs AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM customer_login_logs);
ALTER TABLE vms AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM vms);
ALTER TABLE websites AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM websites);
ALTER TABLE sm_databases AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM sm_databases);
ALTER TABLE email_accounts AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM email_accounts);
ALTER TABLE ssl_certificates AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM ssl_certificates);
ALTER TABLE ips AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM ips);
ALTER TABLE api_credentials AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM api_credentials);
ALTER TABLE modules AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM modules);
ALTER TABLE system_settings AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM system_settings);
ALTER TABLE settings AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM settings);
```

## Validierung

### 1. Datenintegrität prüfen

```sql
-- Anzahl der Datensätze vergleichen
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'customers', COUNT(*) FROM customers
UNION ALL
SELECT 'user_permissions', COUNT(*) FROM user_permissions
UNION ALL
SELECT 'user_sessions', COUNT(*) FROM user_sessions
UNION ALL
SELECT 'login_attempts', COUNT(*) FROM login_attempts
UNION ALL
SELECT 'activity_log', COUNT(*) FROM activity_log
UNION ALL
SELECT 'user_activities', COUNT(*) FROM user_activities
UNION ALL
SELECT 'support_tickets', COUNT(*) FROM support_tickets
UNION ALL
SELECT 'ticket_replies', COUNT(*) FROM ticket_replies
UNION ALL
SELECT 'contact_messages', COUNT(*) FROM contact_messages
UNION ALL
SELECT 'domain_registrations', COUNT(*) FROM domain_registrations
UNION ALL
SELECT 'domains', COUNT(*) FROM domains
UNION ALL
SELECT 'verification_tokens', COUNT(*) FROM verification_tokens
UNION ALL
SELECT 'customer_remember_tokens', COUNT(*) FROM customer_remember_tokens
UNION ALL
SELECT 'customer_login_logs', COUNT(*) FROM customer_login_logs
UNION ALL
SELECT 'vms', COUNT(*) FROM vms
UNION ALL
SELECT 'websites', COUNT(*) FROM websites
UNION ALL
SELECT 'sm_databases', COUNT(*) FROM sm_databases
UNION ALL
SELECT 'email_accounts', COUNT(*) FROM email_accounts
UNION ALL
SELECT 'ssl_certificates', COUNT(*) FROM ssl_certificates
UNION ALL
SELECT 'ips', COUNT(*) FROM ips
UNION ALL
SELECT 'api_credentials', COUNT(*) FROM api_credentials
UNION ALL
SELECT 'modules', COUNT(*) FROM modules
UNION ALL
SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL
SELECT 'settings', COUNT(*) FROM settings;
```

### 2. Foreign Key Constraints prüfen

```sql
-- Foreign Key Constraints testen
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'server_management'
AND REFERENCED_TABLE_NAME IS NOT NULL;
```

### 3. Indizes prüfen

```sql
-- Indizes auflisten
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'server_management'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

## Rollback-Plan

Falls die Migration fehlschlägt:

### 1. Sofortiger Rollback
```sql
-- Neue Datenbank entfernen
DROP DATABASE server_management;

-- Alte Datenbank wiederherstellen
RENAME DATABASE server_management_old TO server_management;
```

### 2. Vollständiger Rollback
```bash
# Datenbank aus Backup wiederherstellen
mysql -u root -p < database_backup_YYYYMMDD_HHMMSS.sql

# System aus Backup wiederherstellen
tar -xzf system_backup_YYYYMMDD_HHMMSS.tar.gz -C /
```

## Post-Migration

### 1. Anwendung testen
- Login-Funktionalität testen
- Alle Module testen
- API-Endpunkte testen
- Support-System testen

### 2. Performance überwachen
```sql
-- Langsame Queries identifizieren
SHOW PROCESSLIST;

-- Query Performance analysieren
EXPLAIN SELECT * FROM users WHERE username = 'admin';
```

### 3. Logs überwachen
- Aktivitätsprotokoll prüfen
- Fehler-Logs überwachen
- Performance-Metriken sammeln

## Troubleshooting

### Häufige Probleme

1. **Foreign Key Fehler beim Import**
   ```sql
   -- Foreign Key Checks temporär deaktivieren
   SET FOREIGN_KEY_CHECKS = 0;
   -- Import durchführen
   SET FOREIGN_KEY_CHECKS = 1;
   ```

2. **Encoding-Probleme**
   ```sql
   -- Zeichensatz prüfen
   SHOW VARIABLES LIKE 'character_set%';
   
   -- Datenbank-Zeichensatz ändern
   ALTER DATABASE server_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Auto-Increment Probleme**
   ```sql
   -- Auto-Increment zurücksetzen
   ALTER TABLE users AUTO_INCREMENT = 1;
   ```

4. **Index-Probleme**
   ```sql
   -- Indizes neu erstellen
   REPAIR TABLE users, customers, support_tickets;
   ```

## Support

Bei Problemen:
1. Logs in `activity_log` prüfen
2. Support-System verwenden
3. Systemadministrator kontaktieren
4. Backup wiederherstellen falls nötig

## Checkliste

- [ ] Vollständiges Backup erstellt
- [ ] Daten exportiert
- [ ] Alte Datenbank gesichert
- [ ] Neue Struktur installiert
- [ ] Daten importiert
- [ ] Auto-Increment Werte angepasst
- [ ] Datenintegrität validiert
- [ ] Foreign Keys geprüft
- [ ] Indizes geprüft
- [ ] Anwendung getestet
- [ ] Performance überwacht
- [ ] Logs überwacht
- [ ] Dokumentation aktualisiert

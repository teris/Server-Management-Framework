// =============================================================================
// FRAMEWORK-SPEZIFISCHE MONGODB-STRUKTUR FÜR STANDALONE-SYSTEM
// =============================================================================
// Erstellt: 2025-09-03
// Version: 2.0
// Beschreibung: Minimale MongoDB-Struktur für DatabaseOnlyFramework.php
// Enthält nur die für das Framework notwendigen Collections
// =============================================================================

// Datenbank erstellen
use server_management;

// =============================================================================
// FRAMEWORK-COLLECTIONS (minimal für DatabaseOnlyFramework.php)
// =============================================================================

// 1. LOGIN_ATTEMPTS COLLECTION
db.createCollection("login_attempts", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["username", "success"],
            properties: {
                username: { bsonType: "string" },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                success: { enum: ["y", "n"] },
                failure_reason: { bsonType: "string" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 2. ACTIVITY_LOG COLLECTION
db.createCollection("activity_log", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["action", "status"],
            properties: {
                action: { bsonType: "string" },
                details: { bsonType: "string" },
                status: { enum: ["success", "error", "pending", "info"] },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 3. SYSTEM_SETTINGS COLLECTION
db.createCollection("system_settings", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["setting_key", "setting_value", "setting_type"],
            properties: {
                setting_key: { bsonType: "string" },
                setting_value: { bsonType: "string" },
                setting_type: { enum: ["string", "integer", "boolean", "json"] },
                description: { bsonType: "string" },
                is_public: { enum: ["y", "n"] },
                updated_by: { bsonType: "objectId" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 4. SETTINGS COLLECTION
db.createCollection("settings", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["site_title", "mode"],
            properties: {
                site_title: { bsonType: "string" },
                logo_path: { bsonType: "string" },
                favicon_path: { bsonType: "string" },
                mode: { enum: ["live", "database"] }
            }
        }
    }
});

// =============================================================================
// INDIZES ERSTELLEN
// =============================================================================

// Users Collection Indizes
db.users.createIndex({ "username": 1 }, { unique: true });
db.users.createIndex({ "email": 1 }, { unique: true });
db.users.createIndex({ "role": 1 });
db.users.createIndex({ "active": 1 });
db.users.createIndex({ "last_login": 1 });

// User Sessions Collection Indizes
db.user_sessions.createIndex({ "session_id": 1 }, { unique: true });
db.user_sessions.createIndex({ "user_id": 1 });
db.user_sessions.createIndex({ "is_active": 1 });
db.user_sessions.createIndex({ "expires_at": 1 });

// Login Attempts Collection Indizes
db.login_attempts.createIndex({ "username": 1 });
db.login_attempts.createIndex({ "ip_address": 1 });
db.login_attempts.createIndex({ "success": 1 });
db.login_attempts.createIndex({ "created_at": 1 });

// Activity Log Collection Indizes
db.activity_log.createIndex({ "action": 1 });
db.activity_log.createIndex({ "status": 1 });
db.activity_log.createIndex({ "created_at": 1 });

// System Settings Collection Indizes
db.system_settings.createIndex({ "setting_key": 1 }, { unique: true });

// =============================================================================
// GRUNDDATEN FÜR FRAMEWORK (Standard-Einstellungen)
// =============================================================================

// Standard-Systemeinstellungen
db.system_settings.insertMany([
    {
        setting_key: "site_name",
        setting_value: "Server Management Framework",
        setting_type: "string",
        description: "Name der Website",
        is_public: "y",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "site_url",
        setting_value: "https://framework.example.com",
        setting_type: "string",
        description: "URL der Website",
        is_public: "y",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "support_email",
        setting_value: "support@framework.example.com",
        setting_type: "string",
        description: "Support-E-Mail-Adresse",
        is_public: "y",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "system_email",
        setting_value: "system@framework.example.com",
        setting_type: "string",
        description: "System-E-Mail-Adresse",
        is_public: "n",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "admin_email",
        setting_value: "admin@framework.example.com",
        setting_type: "string",
        description: "Admin-E-Mail-Adresse",
        is_public: "n",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "max_login_attempts",
        setting_value: "5",
        setting_type: "integer",
        description: "Maximale Login-Versuche",
        is_public: "n",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "session_timeout",
        setting_value: "3600",
        setting_type: "integer",
        description: "Session-Timeout in Sekunden",
        is_public: "n",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "password_min_length",
        setting_value: "8",
        setting_type: "integer",
        description: "Mindestlänge für Passwörter",
        is_public: "n",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "enable_registration",
        setting_value: "true",
        setting_type: "boolean",
        description: "Registrierung aktiviert",
        is_public: "y",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    },
    {
        setting_key: "maintenance_mode",
        setting_value: "false",
        setting_type: "boolean",
        description: "Wartungsmodus aktiviert",
        is_public: "y",
        updated_by: null,
        created_at: new Date(),
        updated_at: new Date()
    }
]);

// Standard-Einstellungen
db.settings.insertOne({
    site_title: "Server Management Framework",
    logo_path: null,
    favicon_path: null,
    mode: "live"
});

print("MongoDB Framework-Struktur erfolgreich erstellt!");
print("Verwenden Sie 'mongosh server_management < framework-mongodb.js' zum Ausführen.");

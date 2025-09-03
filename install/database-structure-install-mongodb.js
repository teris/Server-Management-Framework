// =============================================================================
// INSTALLATION-OPTIMIERTE MONGODB-STRUKTUR FÜR SERVER MANAGEMENT SYSTEM
// =============================================================================
// Erstellt: 2025-09-03
// Version: 2.0
// Beschreibung: MongoDB-Struktur für install.php ohne Standarddaten
// =============================================================================

// Datenbank erstellen
use server_management;

// =============================================================================
// COLLECTIONS ERSTELLEN UND KONFIGURIEREN
// =============================================================================

// 1. USERS COLLECTION (Admin-Benutzer)
db.createCollection("users", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["username", "email", "password_hash", "role", "active"],
            properties: {
                username: { bsonType: "string", minLength: 3, maxLength: 100 },
                email: { bsonType: "string", pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" },
                password_hash: { bsonType: "string" },
                full_name: { bsonType: "string" },
                role: { enum: ["admin", "user", "moderator"] },
                active: { enum: ["y", "n"] },
                last_login: { bsonType: "date" },
                failed_login_attempts: { bsonType: "int", minimum: 0 },
                locked_until: { bsonType: "date" },
                password_changed_at: { bsonType: "date" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 2. CUSTOMERS COLLECTION (Kunden-Benutzer)
db.createCollection("customers", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["first_name", "last_name", "email", "password_hash", "status"],
            properties: {
                first_name: { bsonType: "string", minLength: 1, maxLength: 100 },
                last_name: { bsonType: "string", minLength: 1, maxLength: 100 },
                full_name: { bsonType: "string" },
                email: { bsonType: "string", pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" },
                password_hash: { bsonType: "string" },
                company: { bsonType: "string" },
                phone: { bsonType: "string" },
                address: { bsonType: "string" },
                city: { bsonType: "string" },
                postal_code: { bsonType: "string" },
                country: { bsonType: "string" },
                status: { enum: ["pending", "active", "suspended", "deleted"] },
                email_verified_at: { bsonType: "date" },
                last_login: { bsonType: "date" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 3. USER_PERMISSIONS COLLECTION
db.createCollection("user_permissions", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["user_id", "permission_type"],
            properties: {
                user_id: { bsonType: "objectId" },
                permission_type: { enum: ["proxmox", "ispconfig", "ovh", "ogp", "admin", "readonly"] },
                resource_id: { bsonType: "string" },
                granted_by: { bsonType: "objectId" },
                created_at: { bsonType: "date" },
                expires_at: { bsonType: "date" }
            }
        }
    }
});

// 4. USER_SESSIONS COLLECTION
db.createCollection("user_sessions", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["user_id", "session_id", "expires_at", "is_active"],
            properties: {
                user_id: { bsonType: "objectId" },
                session_id: { bsonType: "string", minLength: 32, maxLength: 128 },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                created_at: { bsonType: "date" },
                last_activity: { bsonType: "date" },
                expires_at: { bsonType: "date" },
                is_active: { enum: ["y", "n"] },
                logout_reason: { enum: ["manual", "timeout", "forced"] }
            }
        }
    }
});

// 5. LOGIN_ATTEMPTS COLLECTION
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

// 6. CUSTOMER_LOGIN_LOGS COLLECTION
db.createCollection("customer_login_logs", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["customer_id", "success"],
            properties: {
                customer_id: { bsonType: "objectId" },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                success: { bsonType: "bool" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 7. VERIFICATION_TOKENS COLLECTION
db.createCollection("verification_tokens", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["customer_id", "token", "type", "expires_at"],
            properties: {
                customer_id: { bsonType: "objectId" },
                token: { bsonType: "string", minLength: 32 },
                type: { enum: ["email_verification", "password_reset"] },
                expires_at: { bsonType: "date" },
                used_at: { bsonType: "date" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 8. CUSTOMER_REMEMBER_TOKENS COLLECTION
db.createCollection("customer_remember_tokens", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["customer_id", "token", "expires_at"],
            properties: {
                customer_id: { bsonType: "objectId" },
                token: { bsonType: "string", minLength: 32, maxLength: 64 },
                expires_at: { bsonType: "date" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 9. ACTIVITY_LOG COLLECTION
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

// 10. USER_ACTIVITIES COLLECTION
db.createCollection("user_activities", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["user_id", "user_type", "activity_type", "description"],
            properties: {
                user_id: { bsonType: "objectId" },
                user_type: { enum: ["customer", "admin"] },
                activity_type: { bsonType: "string" },
                description: { bsonType: "string" },
                related_id: { bsonType: "objectId" },
                related_table: { bsonType: "string" },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 11. SUPPORT_TICKETS COLLECTION
db.createCollection("support_tickets", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["ticket_number", "subject", "message", "email", "priority", "status", "category"],
            properties: {
                customer_id: { bsonType: "objectId" },
                ticket_number: { bsonType: "string", minLength: 3, maxLength: 20 },
                subject: { bsonType: "string", minLength: 1, maxLength: 255 },
                message: { bsonType: "string" },
                email: { bsonType: "string", pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" },
                customer_name: { bsonType: "string" },
                phone: { bsonType: "string" },
                priority: { enum: ["low", "medium", "high", "urgent"] },
                status: { enum: ["open", "in_progress", "waiting_customer", "waiting_admin", "resolved", "closed"] },
                category: { enum: ["technical", "billing", "general", "feature_request", "bug_report", "account"] },
                assigned_to: { bsonType: "objectId" },
                department: { enum: ["support", "billing", "technical", "sales"] },
                source: { enum: ["web", "email", "phone", "chat"] },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                estimated_resolution_time: { bsonType: "int" },
                actual_resolution_time: { bsonType: "int" },
                customer_satisfaction: { bsonType: "int", minimum: 1, maximum: 5 },
                internal_notes: { bsonType: "string" },
                tags: { bsonType: "array", items: { bsonType: "string" } },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" },
                resolved_at: { bsonType: "date" },
                closed_at: { bsonType: "date" },
                due_date: { bsonType: "date" }
            }
        }
    }
});

// 12. TICKET_REPLIES COLLECTION
db.createCollection("ticket_replies", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["ticket_id", "message"],
            properties: {
                ticket_id: { bsonType: "objectId" },
                customer_id: { bsonType: "objectId" },
                admin_id: { bsonType: "objectId" },
                message: { bsonType: "string" },
                is_internal: { bsonType: "bool" },
                created_at: { bsonType: "date" }
            }
        }
    }
});

// 13. CONTACT_MESSAGES COLLECTION
db.createCollection("contact_messages", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["name", "email", "subject", "message"],
            properties: {
                name: { bsonType: "string" },
                email: { bsonType: "string", pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" },
                subject: { bsonType: "string" },
                message: { bsonType: "string" },
                customer_id: { bsonType: "objectId" },
                status: { enum: ["new", "read", "replied", "archived"] },
                ip_address: { bsonType: "string" },
                user_agent: { bsonType: "string" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 14. DOMAIN_REGISTRATIONS COLLECTION
db.createCollection("domain_registrations", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["user_id", "domain", "purpose", "status"],
            properties: {
                user_id: { bsonType: "objectId" },
                domain: { bsonType: "string" },
                purpose: { bsonType: "string" },
                notes: { bsonType: "string" },
                status: { enum: ["pending", "approved", "rejected", "cancelled"] },
                admin_notes: { bsonType: "string" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 15. DOMAINS COLLECTION
db.createCollection("domains", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["domain_name"],
            properties: {
                domain_name: { bsonType: "string" },
                registrar: { bsonType: "string" },
                registration_date: { bsonType: "date" },
                expiration_date: { bsonType: "date" },
                auto_renew: { enum: ["y", "n"] },
                nameservers: { bsonType: "array", items: { bsonType: "string" } },
                status: { bsonType: "string" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 16. VMS COLLECTION (Virtuelle Maschinen)
db.createCollection("vms", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["vm_id", "name", "node", "memory", "cores", "disk_size"],
            properties: {
                vm_id: { bsonType: "int" },
                name: { bsonType: "string" },
                node: { bsonType: "string" },
                status: { enum: ["running", "stopped", "suspended"] },
                memory: { bsonType: "int" },
                cores: { bsonType: "int" },
                disk_size: { bsonType: "int" },
                ip_address: { bsonType: "string" },
                mac_address: { bsonType: "string" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 17. WEBSITES COLLECTION
db.createCollection("websites", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["domain", "ip_address", "system_user", "system_group"],
            properties: {
                domain: { bsonType: "string" },
                ip_address: { bsonType: "string" },
                system_user: { bsonType: "string" },
                system_group: { bsonType: "string" },
                document_root: { bsonType: "string" },
                hd_quota: { bsonType: "int" },
                traffic_quota: { bsonType: "int" },
                active: { enum: ["y", "n"] },
                ssl_enabled: { enum: ["y", "n"] },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 18. SM_DATABASES COLLECTION
db.createCollection("sm_databases", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["database_name", "database_user", "database_type"],
            properties: {
                database_name: { bsonType: "string" },
                database_user: { bsonType: "string" },
                database_type: { enum: ["mysql", "postgresql"] },
                server_id: { bsonType: "int" },
                charset: { bsonType: "string" },
                remote_access: { enum: ["y", "n"] },
                active: { enum: ["y", "n"] },
                website_id: { bsonType: "objectId" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 19. EMAIL_ACCOUNTS COLLECTION
db.createCollection("email_accounts", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["email_address", "login_name", "domain"],
            properties: {
                email_address: { bsonType: "string" },
                login_name: { bsonType: "string" },
                password_hash: { bsonType: "string" },
                full_name: { bsonType: "string" },
                domain: { bsonType: "string" },
                quota_mb: { bsonType: "int" },
                active: { enum: ["y", "n"] },
                autoresponder: { enum: ["y", "n"] },
                autoresponder_text: { bsonType: "string" },
                forward_to: { bsonType: "string" },
                spam_filter: { enum: ["y", "n"] },
                website_id: { bsonType: "objectId" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 20. SSL_CERTIFICATES COLLECTION
db.createCollection("ssl_certificates", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["domain", "status"],
            properties: {
                domain: { bsonType: "string" },
                certificate_path: { bsonType: "string" },
                private_key_path: { bsonType: "string" },
                certificate_authority: { bsonType: "string" },
                issue_date: { bsonType: "date" },
                expiration_date: { bsonType: "date" },
                auto_renew: { enum: ["y", "n"] },
                status: { enum: ["valid", "expired", "revoked", "pending"] },
                website_id: { bsonType: "objectId" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 21. IPS COLLECTION
db.createCollection("ips", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["subnet", "ip_reverse"],
            properties: {
                subnet: { bsonType: "string" },
                ip_reverse: { bsonType: "string" },
                reverse: { bsonType: "string" },
                ttl: { bsonType: "int" },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 22. API_CREDENTIALS COLLECTION
db.createCollection("api_credentials", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["service_name"],
            properties: {
                service_name: { bsonType: "string" },
                endpoint: { bsonType: "string" },
                username: { bsonType: "string" },
                password_encrypted: { bsonType: "string" },
                api_key_encrypted: { bsonType: "string" },
                additional_config: { bsonType: "object" },
                active: { enum: ["y", "n"] },
                created_at: { bsonType: "date" },
                updated_at: { bsonType: "date" }
            }
        }
    }
});

// 23. MODULES COLLECTION
db.createCollection("modules", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["name", "is_active"],
            properties: {
                name: { bsonType: "string" },
                is_active: { bsonType: "bool" }
            }
        }
    }
});

// 24. SYSTEM_SETTINGS COLLECTION
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

// 25. SETTINGS COLLECTION
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

// Customers Collection Indizes
db.customers.createIndex({ "email": 1 }, { unique: true });
db.customers.createIndex({ "status": 1 });
db.customers.createIndex({ "email_verified_at": 1 });

// User Permissions Collection Indizes
db.user_permissions.createIndex({ "user_id": 1, "permission_type": 1, "resource_id": 1 }, { unique: true });
db.user_permissions.createIndex({ "user_id": 1 });
db.user_permissions.createIndex({ "permission_type": 1 });

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

// Support Tickets Collection Indizes
db.support_tickets.createIndex({ "ticket_number": 1 }, { unique: true });
db.support_tickets.createIndex({ "email": 1 });
db.support_tickets.createIndex({ "status": 1 });
db.support_tickets.createIndex({ "priority": 1 });
db.support_tickets.createIndex({ "category": 1 });
db.support_tickets.createIndex({ "assigned_to": 1 });
db.support_tickets.createIndex({ "created_at": 1 });
db.support_tickets.createIndex({ "status": 1, "priority": 1 });

// Domains Collection Indizes
db.domains.createIndex({ "domain_name": 1 }, { unique: true });
db.domains.createIndex({ "status": 1 });
db.domains.createIndex({ "expiration_date": 1 });

// VMs Collection Indizes
db.vms.createIndex({ "vm_id": 1 }, { unique: true });
db.vms.createIndex({ "name": 1 });
db.vms.createIndex({ "status": 1 });
db.vms.createIndex({ "ip_address": 1 });

// Websites Collection Indizes
db.websites.createIndex({ "domain": 1 }, { unique: true });
db.websites.createIndex({ "ip_address": 1 });
db.websites.createIndex({ "active": 1 });

// System Settings Collection Indizes
db.system_settings.createIndex({ "setting_key": 1 }, { unique: true });

print("MongoDB Installations-Struktur erfolgreich erstellt!");
print("Verwenden Sie 'mongosh server_management < database-structure-install-mongodb.js' zum Ausführen.");

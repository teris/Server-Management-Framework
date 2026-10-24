-- Generated from database/schema.json by bin/export-schema.php. No accounts or operator data.
CREATE TABLE IF NOT EXISTS "users" (
  "id" SERIAL PRIMARY KEY,
  "username" VARCHAR(100) NOT NULL UNIQUE,
  "email" VARCHAR(255) NOT NULL UNIQUE,
  "password_hash" VARCHAR(255) NOT NULL,
  "full_name" VARCHAR(255),
  "role" VARCHAR(20) NOT NULL DEFAULT 'user',
  "active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "last_login" TIMESTAMP,
  "failed_login_attempts" INTEGER NOT NULL DEFAULT 0,
  "locked_until" TIMESTAMP,
  "password_changed_at" TIMESTAMP,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "group_id" INTEGER
);
CREATE INDEX "idx_users_role" ON "users" (role);
CREATE INDEX "idx_users_active" ON "users" (active);
CREATE INDEX "idx_users_last_login" ON "users" (last_login);
CREATE TABLE IF NOT EXISTS "customers" (
  "id" SERIAL PRIMARY KEY,
  "first_name" VARCHAR(100) NOT NULL,
  "last_name" VARCHAR(100) NOT NULL,
  "full_name" VARCHAR(255),
  "email" VARCHAR(255) NOT NULL UNIQUE,
  "password_hash" VARCHAR(255) NOT NULL,
  "company" VARCHAR(255),
  "phone" VARCHAR(50),
  "address" TEXT,
  "city" VARCHAR(100),
  "postal_code" VARCHAR(20),
  "country" VARCHAR(100),
  "status" VARCHAR(20) NOT NULL DEFAULT 'pending',
  "email_verified_at" TIMESTAMP,
  "last_login" TIMESTAMP,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "activated_at" TIMESTAMP
);
CREATE INDEX "idx_customers_status" ON "customers" (status);
CREATE INDEX "idx_customers_email_verified_at" ON "customers" (email_verified_at);
CREATE TABLE IF NOT EXISTS "user_permissions" (
  "id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "permission_type" VARCHAR(20) NOT NULL,
  "resource_id" VARCHAR(255),
  "granted_by" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "expires_at" TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES "users"(id) ON DELETE CASCADE,
  FOREIGN KEY (granted_by) REFERENCES "users"(id) ON DELETE SET NULL,
  UNIQUE(user_id, permission_type, resource_id)
);
CREATE INDEX "idx_user_permissions_user_id" ON "user_permissions" (user_id);
CREATE INDEX "idx_user_permissions_permission_type" ON "user_permissions" (permission_type);
CREATE TABLE IF NOT EXISTS "user_sessions" (
  "id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "session_id" VARCHAR(128) NOT NULL UNIQUE,
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "last_activity" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "expires_at" TIMESTAMP NOT NULL,
  "is_active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "logout_reason" VARCHAR(20),
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES "users"(id) ON DELETE CASCADE
);
CREATE INDEX "idx_user_sessions_user_id" ON "user_sessions" (user_id);
CREATE INDEX "idx_user_sessions_is_active" ON "user_sessions" (is_active);
CREATE INDEX "idx_user_sessions_expires_at" ON "user_sessions" (expires_at);
CREATE TABLE IF NOT EXISTS "login_attempts" (
  "id" SERIAL PRIMARY KEY,
  "username" VARCHAR(100) NOT NULL,
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "success" VARCHAR(1) NOT NULL,
  "failure_reason" VARCHAR(255),
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX "idx_login_attempts_username" ON "login_attempts" (username);
CREATE INDEX "idx_login_attempts_ip_address" ON "login_attempts" (ip_address);
CREATE INDEX "idx_login_attempts_success" ON "login_attempts" (success);
CREATE INDEX "idx_login_attempts_created_at" ON "login_attempts" (created_at);
CREATE TABLE IF NOT EXISTS "customer_login_logs" (
  "id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "success" INTEGER NOT NULL,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "verification_tokens" (
  "id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "token" VARCHAR(255) NOT NULL,
  "type" VARCHAR(20) NOT NULL,
  "expires_at" TIMESTAMP NOT NULL,
  "used_at" TIMESTAMP,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "customer_remember_tokens" (
  "id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "token" VARCHAR(64) NOT NULL,
  "expires_at" TIMESTAMP NOT NULL,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "activity_log" (
  "id" SERIAL PRIMARY KEY,
  "action" VARCHAR(255) NOT NULL,
  "details" TEXT,
  "status" VARCHAR(20) NOT NULL DEFAULT 'info',
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "user_activities" (
  "id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "user_type" VARCHAR(20) NOT NULL,
  "activity_type" VARCHAR(100) NOT NULL,
  "description" TEXT NOT NULL,
  "related_id" INTEGER,
  "related_table" VARCHAR(100),
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "support_tickets" (
  "id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER,
  "ticket_number" VARCHAR(20) NOT NULL UNIQUE,
  "subject" VARCHAR(255) NOT NULL,
  "message" TEXT NOT NULL,
  "email" VARCHAR(255) NOT NULL,
  "customer_name" VARCHAR(255),
  "phone" VARCHAR(50),
  "priority" VARCHAR(20) NOT NULL DEFAULT 'medium',
  "status" VARCHAR(20) NOT NULL DEFAULT 'open',
  "category" VARCHAR(20) NOT NULL DEFAULT 'general',
  "assigned_to" INTEGER,
  "department" VARCHAR(20) DEFAULT 'support',
  "source" VARCHAR(20) NOT NULL DEFAULT 'web',
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "estimated_resolution_time" INTEGER,
  "actual_resolution_time" INTEGER,
  "customer_satisfaction" INTEGER,
  "internal_notes" TEXT,
  "tags" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "resolved_at" TIMESTAMP,
  "closed_at" TIMESTAMP,
  "due_date" TIMESTAMP,
  "name" VARCHAR(255),
  "reply_count" INTEGER NOT NULL DEFAULT 0,
  "last_reply_at" TIMESTAMP,
  "last_reply_by" VARCHAR(24),
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES "users"(id) ON DELETE SET NULL
);
CREATE INDEX "idx_support_tickets_email" ON "support_tickets" (email);
CREATE INDEX "idx_support_tickets_status" ON "support_tickets" (status);
CREATE INDEX "idx_support_tickets_priority" ON "support_tickets" (priority);
CREATE INDEX "idx_support_tickets_category" ON "support_tickets" (category);
CREATE INDEX "idx_support_tickets_assigned_to" ON "support_tickets" (assigned_to);
CREATE INDEX "idx_support_tickets_created_at" ON "support_tickets" (created_at);
CREATE INDEX "idx_support_tickets_status_priority" ON "support_tickets" (status, priority);
CREATE TABLE IF NOT EXISTS "ticket_replies" (
  "id" SERIAL PRIMARY KEY,
  "ticket_id" INTEGER NOT NULL,
  "customer_id" INTEGER,
  "admin_id" INTEGER,
  "message" TEXT NOT NULL,
  "is_internal" INTEGER NOT NULL DEFAULT 0,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "user_id" INTEGER,
  "user_type" VARCHAR(24),
  "author_name" VARCHAR(255),
  "author_email" VARCHAR(255),
  FOREIGN KEY (ticket_id) REFERENCES "support_tickets"(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE SET NULL,
  FOREIGN KEY (admin_id) REFERENCES "users"(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS "contact_messages" (
  "id" SERIAL PRIMARY KEY,
  "name" VARCHAR(255) NOT NULL,
  "email" VARCHAR(255) NOT NULL,
  "subject" VARCHAR(255) NOT NULL,
  "message" TEXT NOT NULL,
  "customer_id" INTEGER,
  "status" VARCHAR(20) NOT NULL DEFAULT 'new',
  "ip_address" VARCHAR(45),
  "user_agent" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "phone" VARCHAR(50),
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS "domain_registrations" (
  "id" SERIAL PRIMARY KEY,
  "user_id" INTEGER,
  "domain" VARCHAR(255) NOT NULL,
  "purpose" TEXT NOT NULL,
  "notes" TEXT,
  "status" VARCHAR(20) NOT NULL DEFAULT 'pending',
  "admin_notes" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "customer_id" INTEGER,
  "domain_name" VARCHAR(255),
  "registration_type" VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES "users"(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "domains" (
  "id" SERIAL PRIMARY KEY,
  "domain_name" VARCHAR(255) NOT NULL UNIQUE,
  "registrar" VARCHAR(100),
  "registration_date" DATE,
  "expiration_date" DATE,
  "auto_renew" VARCHAR(1) NOT NULL DEFAULT 'y',
  "nameservers" TEXT,
  "status" VARCHAR(100),
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX "idx_domains_status" ON "domains" (status);
CREATE INDEX "idx_domains_expiration_date" ON "domains" (expiration_date);
CREATE TABLE IF NOT EXISTS "vms" (
  "id" SERIAL PRIMARY KEY,
  "vm_id" INTEGER NOT NULL UNIQUE,
  "name" VARCHAR(255) NOT NULL,
  "node" VARCHAR(100) NOT NULL,
  "status" VARCHAR(20) NOT NULL DEFAULT 'stopped',
  "memory" INTEGER NOT NULL,
  "cores" INTEGER NOT NULL,
  "disk_size" INTEGER NOT NULL,
  "ip_address" VARCHAR(45),
  "mac_address" VARCHAR(17),
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "vm_type" VARCHAR(16) NOT NULL DEFAULT 'qemu',
  "uptime" BIGINT,
  "cpu_usage" REAL,
  "memory_usage" BIGINT
);
CREATE INDEX "idx_vms_name" ON "vms" (name);
CREATE INDEX "idx_vms_status" ON "vms" (status);
CREATE INDEX "idx_vms_ip_address" ON "vms" (ip_address);
CREATE TABLE IF NOT EXISTS "websites" (
  "id" SERIAL PRIMARY KEY,
  "domain" VARCHAR(255) NOT NULL UNIQUE,
  "ip_address" VARCHAR(45) NOT NULL,
  "system_user" VARCHAR(100) NOT NULL,
  "system_group" VARCHAR(100) NOT NULL,
  "document_root" VARCHAR(255),
  "hd_quota" INTEGER,
  "traffic_quota" INTEGER,
  "active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "ssl_enabled" VARCHAR(1) NOT NULL DEFAULT 'n',
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX "idx_websites_ip_address" ON "websites" (ip_address);
CREATE INDEX "idx_websites_active" ON "websites" (active);
CREATE TABLE IF NOT EXISTS "sm_databases" (
  "id" SERIAL PRIMARY KEY,
  "database_name" VARCHAR(100) NOT NULL,
  "database_user" VARCHAR(100) NOT NULL,
  "database_type" VARCHAR(20) NOT NULL DEFAULT 'mysql',
  "server_id" INTEGER,
  "charset" VARCHAR(50) DEFAULT 'utf8mb4',
  "remote_access" VARCHAR(1) NOT NULL DEFAULT 'n',
  "active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "website_id" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (website_id) REFERENCES "websites"(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS "email_accounts" (
  "id" SERIAL PRIMARY KEY,
  "email_address" VARCHAR(255) NOT NULL,
  "login_name" VARCHAR(100) NOT NULL,
  "password_hash" VARCHAR(255),
  "full_name" VARCHAR(255),
  "domain" VARCHAR(255) NOT NULL,
  "quota_mb" INTEGER,
  "active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "autoresponder" VARCHAR(1) NOT NULL DEFAULT 'n',
  "autoresponder_text" TEXT,
  "forward_to" VARCHAR(255),
  "spam_filter" VARCHAR(1) NOT NULL DEFAULT 'y',
  "website_id" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (website_id) REFERENCES "websites"(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS "ssl_certificates" (
  "id" SERIAL PRIMARY KEY,
  "domain" VARCHAR(255) NOT NULL,
  "certificate_path" VARCHAR(255),
  "private_key_path" VARCHAR(255),
  "certificate_authority" VARCHAR(100),
  "issue_date" DATE,
  "expiration_date" DATE,
  "auto_renew" VARCHAR(1) NOT NULL DEFAULT 'y',
  "status" VARCHAR(20) NOT NULL DEFAULT 'pending',
  "website_id" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (website_id) REFERENCES "websites"(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS "ips" (
  "id" SERIAL PRIMARY KEY,
  "subnet" VARCHAR(18) NOT NULL,
  "ip_reverse" VARCHAR(255) NOT NULL,
  "reverse" VARCHAR(255),
  "ttl" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "api_credentials" (
  "id" SERIAL PRIMARY KEY,
  "service_name" VARCHAR(100) NOT NULL,
  "endpoint" VARCHAR(255),
  "username" VARCHAR(100),
  "password_encrypted" TEXT,
  "api_key_encrypted" TEXT,
  "additional_config" TEXT,
  "active" VARCHAR(1) NOT NULL DEFAULT 'y',
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "modules" (
  "id" SERIAL PRIMARY KEY,
  "name" VARCHAR(100) NOT NULL UNIQUE,
  "is_active" INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS "system_settings" (
  "id" SERIAL PRIMARY KEY,
  "setting_key" VARCHAR(100) NOT NULL UNIQUE,
  "setting_value" TEXT NOT NULL,
  "setting_type" VARCHAR(20) NOT NULL DEFAULT 'string',
  "description" TEXT,
  "is_public" VARCHAR(1) NOT NULL DEFAULT 'n',
  "updated_by" INTEGER,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES "users"(id) ON DELETE SET NULL
);
CREATE INDEX "idx_system_settings_setting_key" ON "system_settings" (setting_key);
CREATE TABLE IF NOT EXISTS "settings" (
  "id" SERIAL PRIMARY KEY,
  "site_title" VARCHAR(255) NOT NULL,
  "logo_path" VARCHAR(255),
  "favicon_path" VARCHAR(255),
  "mode" VARCHAR(20) NOT NULL DEFAULT 'live'
);
CREATE TABLE IF NOT EXISTS "groups" (
  "id" SERIAL PRIMARY KEY,
  "name" VARCHAR(100) NOT NULL UNIQUE,
  "description" TEXT
);
CREATE TABLE IF NOT EXISTS "module_configs" (
  "id" SERIAL PRIMARY KEY,
  "module_name" VARCHAR(100) NOT NULL,
  "config" TEXT,
  "enabled" INTEGER NOT NULL DEFAULT 0,
  "config_key" VARCHAR(100) NOT NULL,
  "config_value" TEXT,
  UNIQUE (module_name, config_key)
);
CREATE TABLE IF NOT EXISTS "module_permissions" (
  "id" SERIAL PRIMARY KEY,
  "module_name" VARCHAR(100) NOT NULL,
  "required_role" VARCHAR(24) NOT NULL,
  UNIQUE (module_name, required_role)
);
CREATE TABLE IF NOT EXISTS "active_modules" (
  "id" SERIAL PRIMARY KEY,
  "module_id" INTEGER,
  "module_name" VARCHAR(100) UNIQUE,
  "enabled" INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS "module_dependencies" (
  "id" SERIAL PRIMARY KEY,
  "module_id" INTEGER NOT NULL,
  "dependency_id" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "group_module_permissions" (
  "id" SERIAL PRIMARY KEY,
  "group_id" INTEGER NOT NULL,
  "module_id" INTEGER NOT NULL,
  "can_access" INTEGER NOT NULL DEFAULT 0,
  UNIQUE (group_id, module_id)
);
CREATE TABLE IF NOT EXISTS "backup_jobs" (
  "id" SERIAL PRIMARY KEY,
  "type" VARCHAR(50) NOT NULL,
  "status" VARCHAR(32) NOT NULL DEFAULT 'pending',
  "config" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "network_config" (
  "id" SERIAL PRIMARY KEY,
  "vm_id" INTEGER,
  "node" VARCHAR(100),
  "ip_address" VARCHAR(45),
  "mac_address" VARCHAR(17),
  "config" TEXT
);
CREATE TABLE IF NOT EXISTS "server_resources" (
  "id" SERIAL PRIMARY KEY,
  "vm_id" INTEGER,
  "node" VARCHAR(100),
  "cpu_usage" REAL,
  "memory_usage" BIGINT,
  "timestamp" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "domain_extensions" (
  "id" SERIAL PRIMARY KEY,
  "tld" VARCHAR(100) NOT NULL UNIQUE,
  "name" VARCHAR(255) NOT NULL,
  "active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "email_templates" (
  "id" SERIAL PRIMARY KEY,
  "template_name" VARCHAR(255) NOT NULL UNIQUE,
  "subject" TEXT NOT NULL,
  "html_content" TEXT,
  "raw_content" TEXT,
  "variables" TEXT,
  "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "account_tokens" (
  "token_hash" VARCHAR(64) PRIMARY KEY,
  "realm" VARCHAR(16) NOT NULL,
  "subject_id" INTEGER NOT NULL,
  "purpose" VARCHAR(32) NOT NULL,
  "expires_at" BIGINT NOT NULL,
  "used_at" BIGINT,
  "created_at" BIGINT NOT NULL,
  "credential" VARCHAR(64) NOT NULL,
  UNIQUE (realm, subject_id, purpose)
);
CREATE INDEX "idx_account_tokens_subject" ON "account_tokens" (realm, subject_id, purpose);
CREATE TABLE IF NOT EXISTS "schema_migrations" (
  "version" VARCHAR(80) PRIMARY KEY,
  "checksum" VARCHAR(64) NOT NULL,
  "applied_at" VARCHAR(32) NOT NULL
);
CREATE TABLE IF NOT EXISTS "security_migrations" (
  "version" VARCHAR(80) PRIMARY KEY,
  "applied_at" VARCHAR(32) NOT NULL
);
CREATE TABLE IF NOT EXISTS "security_remember_tokens" (
  "selector" VARCHAR(24) PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "token_hash" VARCHAR(64) NOT NULL,
  "credential" VARCHAR(64) NOT NULL,
  "expires_at" BIGINT NOT NULL,
  FOREIGN KEY (customer_id) REFERENCES "customers"(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "security_resource_assignments" (
  "id" VARCHAR(32) PRIMARY KEY,
  "subject_realm" VARCHAR(16) NOT NULL,
  "subject_id" INTEGER NOT NULL,
  "provider" VARCHAR(32) NOT NULL,
  "connection_id" VARCHAR(64) NOT NULL,
  "resource_type" VARCHAR(32) NOT NULL,
  "external_id" VARCHAR(255) NOT NULL,
  "metadata" TEXT NOT NULL,
  "can_write" INTEGER NOT NULL DEFAULT 0,
  "expires_at" BIGINT,
  UNIQUE (subject_realm, subject_id, provider, connection_id, resource_type, external_id)
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(255),
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    last_login DATETIME,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until DATETIME,
    password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'suspended', 'deleted')),
    email_verified_at DATETIME,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    ticket_number VARCHAR(20) NOT NULL DEFAULT 'TEMP' UNIQUE,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    customer_name VARCHAR(255),
    phone VARCHAR(50),
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed')),
    category VARCHAR(20) DEFAULT 'general' CHECK (category IN ('technical', 'billing', 'general', 'feature_request', 'bug_report', 'account')),
    assigned_to INTEGER,
    department VARCHAR(20) DEFAULT 'support' CHECK (department IN ('support', 'billing', 'technical', 'sales')),
    source VARCHAR(10) DEFAULT 'web' CHECK (source IN ('web', 'email', 'phone', 'chat')),
    ip_address VARCHAR(45),
    user_agent TEXT,
    estimated_resolution_time INTEGER, -- in hours
    actual_resolution_time INTEGER, -- in hours
    customer_satisfaction INTEGER, -- 1-5 rating
    internal_notes TEXT,
    tags VARCHAR(500), -- comma-separated tags
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    closed_at DATETIME,
    due_date DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    customer_id INTEGER,
    admin_id INTEGER,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);
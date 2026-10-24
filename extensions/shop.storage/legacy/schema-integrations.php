<?php
function shop_migrate_integrations(PDO $db, string $id, bool $sqlite): void {
    $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $tables = [
        "shop_integrations (provider VARCHAR(30) PRIMARY KEY, enabled INT NOT NULL DEFAULT 0, secret_config TEXT NOT NULL, tested_at DATETIME, test_result VARCHAR(500), updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        "shop_resource_links (service_id INT PRIMARY KEY, provider VARCHAR(30) NOT NULL, external_id VARCHAR(255) NOT NULL, metadata TEXT NOT NULL, credentials TEXT, verified_at DATETIME, UNIQUE(provider,external_id), FOREIGN KEY(service_id) REFERENCES shop_services(id))",
        "shop_provider_accounts (customer_id INT NOT NULL, provider VARCHAR(30) NOT NULL, external_id VARCHAR(100) NOT NULL, PRIMARY KEY(customer_id,provider), UNIQUE(provider,external_id), FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_product_automation (product_id INT PRIMARY KEY, provider VARCHAR(30) NOT NULL, enabled INT NOT NULL DEFAULT 0, profile TEXT NOT NULL, FOREIGN KEY(product_id) REFERENCES shop_products(id))",
        "shop_orders (id $id, reference VARCHAR(40) NOT NULL UNIQUE, customer_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(120) NOT NULL, amount_cents INT NOT NULL, currency VARCHAR(3) NOT NULL DEFAULT 'EUR', billing VARCHAR(20) NOT NULL, domain VARCHAR(253), configuration TEXT NOT NULL, provider VARCHAR(30) NOT NULL, profile TEXT NOT NULL, paypal_mode VARCHAR(10) NOT NULL, payment_kind VARCHAR(20) NOT NULL, payment_id VARCHAR(100), plan_id VARCHAR(100), approval_url TEXT, payment_status VARCHAR(30) NOT NULL DEFAULT 'pending', fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'not_paid', renewal_status VARCHAR(30) DEFAULT '', next_billing_at VARCHAR(40), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(paypal_mode,payment_id), FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_payments (id $id, order_id INT NOT NULL, paypal_mode VARCHAR(10) NOT NULL, transaction_id VARCHAR(100) NOT NULL, amount_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(30) NOT NULL, received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(paypal_mode,transaction_id), FOREIGN KEY(order_id) REFERENCES shop_orders(id))",
        "shop_webhook_events (event_key VARCHAR(180) PRIMARY KEY, event_type VARCHAR(100) NOT NULL, received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        "shop_paypal_plans (fingerprint VARCHAR(64) PRIMARY KEY, product_id INT NOT NULL, paypal_mode VARCHAR(10) NOT NULL, plan_id VARCHAR(100) NOT NULL)",
        "shop_jobs (id $id, order_id INT NOT NULL UNIQUE, state VARCHAR(30) NOT NULL DEFAULT 'queued', phase VARCHAR(50) NOT NULL DEFAULT 'start', payload TEXT NOT NULL, lock_token VARCHAR(64), locked_until INT NOT NULL DEFAULT 0, attempts INT NOT NULL DEFAULT 0, last_error VARCHAR(500), updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES shop_orders(id))",
        "shop_audit (id $id, actor_type VARCHAR(20) NOT NULL, actor_id INT, action VARCHAR(80) NOT NULL, resource VARCHAR(150), result VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        "shop_hosting_children (id $id, service_id INT NOT NULL, kind VARCHAR(20) NOT NULL, external_id VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, metadata TEXT NOT NULL, UNIQUE(kind,external_id), FOREIGN KEY(service_id) REFERENCES shop_services(id))",
        "shop_resource_locks (service_id INT PRIMARY KEY, lock_token VARCHAR(64), locked_until INT NOT NULL DEFAULT 0)",
    ];
    foreach ($tables as $table) $db->exec('CREATE TABLE IF NOT EXISTS '.$table.$suffix);
}

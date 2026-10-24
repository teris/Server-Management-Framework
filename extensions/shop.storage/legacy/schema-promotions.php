<?php
function shop_migrate_promotions(PDO $db,string $id,bool $sqlite): void {
    $suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    foreach([
        "shop_coupons (id $id,code VARCHAR(64) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,kind VARCHAR(20) NOT NULL,rules TEXT NOT NULL,active INT NOT NULL DEFAULT 0,revision INT NOT NULL DEFAULT 1,created_ts INT NOT NULL,updated_ts INT NOT NULL)",
        "shop_coupon_uses (order_id INT PRIMARY KEY,coupon_id INT NOT NULL,customer_id INT NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'reserved',snapshot TEXT NOT NULL,created_ts INT NOT NULL,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(coupon_id) REFERENCES shop_coupons(id),FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_prepayment_options (product_id INT NOT NULL,months INT NOT NULL,discount_bps INT NOT NULL DEFAULT 0,enabled INT NOT NULL DEFAULT 0,PRIMARY KEY(product_id,months),FOREIGN KEY(product_id) REFERENCES shop_products(id))",
        "shop_coupon_rewards (id $id,source_order_id INT NOT NULL,customer_id INT NOT NULL,category VARCHAR(40) NOT NULL,cap_net_cents INT NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'available',order_id INT UNIQUE,created_ts INT NOT NULL,UNIQUE(source_order_id,category),FOREIGN KEY(source_order_id) REFERENCES shop_orders(id),FOREIGN KEY(customer_id) REFERENCES customers(id),FOREIGN KEY(order_id) REFERENCES shop_orders(id))",
        "shop_free_entitlements (order_id INT PRIMARY KEY,customer_id INT NOT NULL,snapshot_hash VARCHAR(64) NOT NULL,reason VARCHAR(30) NOT NULL,subscription_id VARCHAR(100) NOT NULL DEFAULT '',granted_ts INT NOT NULL,until_ts INT NOT NULL DEFAULT 0,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(customer_id) REFERENCES customers(id))",
    ] as $table)$db->exec('CREATE TABLE IF NOT EXISTS '.$table.$suffix);
}

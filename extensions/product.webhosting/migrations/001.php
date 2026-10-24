<?php
return static function(PDO $db):void {
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';$suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    foreach([
        "shop_hosting_children (id $id, service_id INT NOT NULL, kind VARCHAR(20) NOT NULL, external_id VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, metadata TEXT NOT NULL, UNIQUE(kind,external_id), FOREIGN KEY(service_id) REFERENCES shop_services(id))",
        "shop_child_credentials (child_id INT PRIMARY KEY,secret TEXT NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(child_id) REFERENCES shop_hosting_children(id) ON DELETE CASCADE)",
        "shop_hosting_domain_verifications (order_id INT PRIMARY KEY,customer_id INT NOT NULL,domain VARCHAR(253) NOT NULL,token VARCHAR(64) NOT NULL,state VARCHAR(30) NOT NULL DEFAULT 'pending',confirmed_ts INT NOT NULL DEFAULT 0,verified_ts INT NOT NULL DEFAULT 0,activated_ts INT NOT NULL DEFAULT 0,expires_ts INT NOT NULL,next_check INT NOT NULL DEFAULT 0,last_error VARCHAR(500),locked_until INT NOT NULL DEFAULT 0,lock_token VARCHAR(64),FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_hosting_mail_dns (domain VARCHAR(253) PRIMARY KEY,customer_id INT NOT NULL,mail_domain_id INT NOT NULL,ispconfig_connection_id VARCHAR(64) NOT NULL,ovh_connection_id VARCHAR(64) NOT NULL DEFAULT '',record TEXT NOT NULL,dns_mode VARCHAR(30) NOT NULL,state VARCHAR(30) NOT NULL,last_error VARCHAR(500),updated_ts INT NOT NULL,locked_until INT NOT NULL DEFAULT 0,lock_token VARCHAR(64),FOREIGN KEY(customer_id) REFERENCES customers(id))"
    ]as$table)$db->exec('CREATE TABLE IF NOT EXISTS '.$table.$suffix);
};

<?php
return static function(PDO $db):void {
    $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_storage_gates (node VARCHAR(80) PRIMARY KEY)".$suffix);
    $db->exec("CREATE TABLE IF NOT EXISTS shop_storage_reservations (job_id INT PRIMARY KEY,node VARCHAR(80) NOT NULL,storage VARCHAR(80) NOT NULL,bytes BIGINT NOT NULL,created_ts INT NOT NULL,FOREIGN KEY(job_id) REFERENCES shop_jobs(id))".$suffix);
    $db->exec("CREATE TABLE IF NOT EXISTS shop_vps_exports (order_id INT PRIMARY KEY,service_id INT NOT NULL,customer_id INT NOT NULL,node VARCHAR(80) NOT NULL,vmid INT NOT NULL,guest_type VARCHAR(8) NOT NULL,connection_id VARCHAR(64) NOT NULL,claim VARCHAR(48) NOT NULL,state VARCHAR(24) NOT NULL DEFAULT 'queued',payload TEXT NOT NULL,archive VARCHAR(200) NOT NULL DEFAULT '',size_bytes BIGINT NOT NULL DEFAULT 0,sha256 VARCHAR(64) NOT NULL DEFAULT '',ready_ts INT NOT NULL DEFAULT 0,expires_ts INT NOT NULL DEFAULT 0,updated_ts INT NOT NULL,last_error VARCHAR(500),FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(service_id) REFERENCES shop_services(id),FOREIGN KEY(customer_id) REFERENCES customers(id))".$suffix);
};

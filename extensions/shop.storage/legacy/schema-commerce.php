<?php
function shop_migrate_commerce(PDO $db,string $id,bool $sqlite): void {
    $suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $tables=[
        "shop_product_options (product_id INT PRIMARY KEY,sale_mode VARCHAR(20) NOT NULL DEFAULT 'direct',net_cents INT,tax_bps INT NOT NULL DEFAULT 1900,provisioning_notice TEXT NOT NULL,dns_notice TEXT NOT NULL,FOREIGN KEY(product_id) REFERENCES shop_products(id))",
        "shop_quotes (id $id,request_id INT NOT NULL,customer_id INT NOT NULL,product_id INT NOT NULL,title VARCHAR(120) NOT NULL,net_cents INT NOT NULL,gross_cents INT NOT NULL,tax_bps INT NOT NULL DEFAULT 1900,billing VARCHAR(20) NOT NULL,domain VARCHAR(253),provider VARCHAR(30) NOT NULL,profile TEXT NOT NULL,provisioning_notice TEXT NOT NULL,dns_notice TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',expires_at VARCHAR(30) NOT NULL,order_id INT UNIQUE,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,published_at DATETIME,FOREIGN KEY(request_id) REFERENCES shop_requests(id),FOREIGN KEY(customer_id) REFERENCES customers(id),FOREIGN KEY(product_id) REFERENCES shop_products(id))",
        "shop_notifications (id $id,customer_id INT NOT NULL,title VARCHAR(200) NOT NULL,body TEXT NOT NULL,url VARCHAR(500) NOT NULL,event_key VARCHAR(180) NOT NULL UNIQUE,is_read INT NOT NULL DEFAULT 0,archived_ts INT NOT NULL DEFAULT 0,deleted_ts INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_mail_queue (id $id,event_key VARCHAR(180) NOT NULL UNIQUE,recipient VARCHAR(255) NOT NULL,subject VARCHAR(250) NOT NULL,payload TEXT NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'pending',attempts INT NOT NULL DEFAULT 0,available_at INT NOT NULL DEFAULT 0,locked_until INT NOT NULL DEFAULT 0,lock_token VARCHAR(64),last_error VARCHAR(500),sent_at DATETIME,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        "shop_commerce_events (event_key VARCHAR(180) PRIMARY KEY,event_type VARCHAR(40) NOT NULL,payload TEXT NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'pending',last_error VARCHAR(500),locked_until INT NOT NULL DEFAULT 0,lock_token VARCHAR(64),created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        "shop_invoice_counters (series VARCHAR(40) PRIMARY KEY,next_number INT NOT NULL)",
        "shop_invoices (id $id,payment_id INT UNIQUE,order_id INT NOT NULL,customer_id INT NOT NULL,number VARCHAR(60) NOT NULL UNIQUE,kind VARCHAR(20) NOT NULL DEFAULT 'invoice',original_id INT UNIQUE,snapshot TEXT NOT NULL,pdf_file VARCHAR(180),pdf_hash VARCHAR(64),issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(customer_id) REFERENCES customers(id))",
        "shop_faq (id $id,slug VARCHAR(100) NOT NULL UNIQUE,title VARCHAR(200) NOT NULL,category VARCHAR(30) NOT NULL,body TEXT NOT NULL,links TEXT NOT NULL,published INT NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
    ];
    foreach($tables as $table)$db->exec('CREATE TABLE IF NOT EXISTS '.$table.$suffix);
    if(!$db->query("SELECT meta_value FROM shop_meta WHERE meta_key='commerce_net_prices_v1'")->fetchColumn()){
        $db->beginTransaction();
        try{
            foreach($db->query('SELECT id,price_cents FROM shop_products')->fetchAll(PDO::FETCH_ASSOC) as $p){
                $net=$p['price_cents']===null?null:(int)$p['price_cents'];$gross=$net===null?null:intdiv($net*11900+5000,10000);
                $s=$db->prepare('INSERT INTO shop_product_options (product_id,sale_mode,net_cents,tax_bps,provisioning_notice,dns_notice) VALUES (?,?,?,?,?,?)');$s->execute([$p['id'],$net!==null && $net>0?'direct':'request',$net,1900,'','']);
                if($net!==null){$s=$db->prepare('UPDATE shop_products SET price_cents=? WHERE id=?');$s->execute([$gross,$p['id']]);}
            }
            $db->exec("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('commerce_net_prices_v1','1')");$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}

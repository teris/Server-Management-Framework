<?php
/** Reuses customer, user and support tables from the repository without demo data. */
function shop_migrate(PDO $db): void {
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_products (
        id $id, slug VARCHAR(80) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
        eyebrow VARCHAR(180) NOT NULL, category VARCHAR(40) NOT NULL, icon VARCHAR(30) NOT NULL,
        description TEXT NOT NULL, features TEXT NOT NULL, price_cents INT DEFAULT NULL,
        billing VARCHAR(20) NOT NULL DEFAULT 'month', featured INT NOT NULL DEFAULT 0,
        active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS shop_requests (
        id $id, reference VARCHAR(24) NOT NULL UNIQUE, customer_id INT NOT NULL,
        product_slug VARCHAR(80) NOT NULL, product_name VARCHAR(120) NOT NULL,
        price_cents INT DEFAULT NULL, billing VARCHAR(20) DEFAULT 'month',
        domain VARCHAR(253) DEFAULT '', configuration TEXT NOT NULL, notes TEXT NOT NULL,
        status VARCHAR(25) NOT NULL DEFAULT 'new', admin_note TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS shop_services (
        id $id, customer_id INT NOT NULL, name VARCHAR(180) NOT NULL,
        category VARCHAR(40) NOT NULL, identifier VARCHAR(253) NOT NULL,
        status VARCHAR(25) NOT NULL DEFAULT 'active', details TEXT NOT NULL,
        price_cents INT DEFAULT NULL, billing VARCHAR(20) DEFAULT 'month',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS shop_rate_limits (
        bucket VARCHAR(128) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0,
        window_start INT NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS shop_meta (meta_key VARCHAR(80) PRIMARY KEY, meta_value TEXT)");
    require_once __DIR__.'/appearance.php';
    shop_migrate_appearance($db);
    require_once __DIR__ . '/schema-integrations.php';
    shop_migrate_integrations($db, $id, $sqlite);
    require_once __DIR__.'/schema-commerce.php';
    shop_migrate_commerce($db,$id,$sqlite);
    require_once __DIR__.'/schema-domains.php';
    shop_migrate_domains($db,$id,$sqlite);
    require_once __DIR__.'/schema-contracts.php';
    shop_migrate_contracts($db,$id,$sqlite);
    require_once __DIR__.'/schema-shop-options.php';
    shop_migrate_options($db,$id,$sqlite);
    require_once __DIR__.'/schema-promotions.php';
    shop_migrate_promotions($db,$id,$sqlite);
    require_once __DIR__.'/account-deletion.php';
    shop_migrate_account_deletion($db);
    require_once __DIR__.'/notifications.php';
    shop_migrate_notifications($db);
    require_once __DIR__.'/order-admin.php';
    shop_migrate_order_admin($db);
    foreach(['product.webhosting','product.vps']as$component){$migration=dirname(__DIR__,2).'/'.$component.'/migrations/001.php';if(is_file($migration)){($apply=require $migration)($db);}}
    $seeded=$db->query("SELECT meta_value FROM shop_meta WHERE meta_key='catalog_seeded'")->fetchColumn();
    if(!$seeded){
        if((int)$db->query('SELECT COUNT(*) FROM shop_products')->fetchColumn()===0){
            $insert=$db->prepare('INSERT INTO shop_products (slug,name,eyebrow,category,icon,description,features,featured,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach(require __DIR__.'/catalog.php' as $i=>$p){if(($p['category']==='hosting' && !is_file(dirname(__DIR__,2).'/product.webhosting/module.json')) || ($p['category']==='server' && !is_file(dirname(__DIR__,2).'/product.vps/module.json')))continue;$insert->execute([$p['slug'],$p['name'],$p['eyebrow'],$p['category'],$p['icon'],$p['description'],json_encode($p['features'],JSON_UNESCAPED_UNICODE),$p['featured'],$i]);}
        }
        $db->exec("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('catalog_seeded','1')");
    }
}

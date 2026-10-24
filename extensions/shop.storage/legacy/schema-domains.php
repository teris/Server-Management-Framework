<?php
function shop_migrate_domains(PDO $db,string $id,bool $sqlite): void {
    $suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_domain_tlds (id $id,extension VARCHAR(100) NOT NULL UNIQUE,product_id INT NOT NULL,net_cents INT,sale_mode VARCHAR(20) NOT NULL DEFAULT 'request',enabled INT NOT NULL DEFAULT 0,max_purchase_cents INT NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES shop_products(id))".$suffix);
    // Import only the extensions and prices previously configured by the owner.
    if(!$db->query("SELECT meta_value FROM shop_meta WHERE meta_key='domain_catalog_import_v1'")->fetchColumn()){
        $db->beginTransaction();try{
            $rows=$db->query("SELECT p.*,a.profile,a.enabled AS automation_enabled FROM shop_products p LEFT JOIN shop_product_automation a ON a.product_id=p.id WHERE p.category='domain' ORDER BY p.active DESC,p.id")->fetchAll(PDO::FETCH_ASSOC);$i=0;
            foreach($rows as $p){$profile=json_decode($p['profile']??'{}',true)??[];$options=$db->prepare('SELECT * FROM shop_product_options WHERE product_id=?');$options->execute([$p['id']]);$o=$options->fetch(PDO::FETCH_ASSOC);$net=$o?$o['net_cents']:($p['price_cents']===null?null:intdiv((int)$p['price_cents']*10000+5950,11900));
                $configured=$profile['allowed_tlds']??'';if($configured==='' && preg_match('/(?:^|\s)\.([a-z]{2,63}(?:\.[a-z]{2,63})?)(?:$|\s)/i',$p['name'],$match))$configured=strtolower($match[1]);
                foreach(explode(',',$configured) as $extension){$extension=strtolower(trim($extension,'. '));if(!preg_match('/^[a-z]{2,63}(?:\.[a-z]{2,63})?$/D',$extension))continue;$exists=$db->prepare('SELECT id FROM shop_domain_tlds WHERE extension=?');$exists->execute([$extension]);if($exists->fetchColumn())continue;
                    $s=$db->prepare('INSERT INTO shop_domain_tlds (extension,product_id,net_cents,sale_mode,enabled,max_purchase_cents,sort_order) VALUES (?,?,?,?,?,?,?)');$s->execute([$extension,$p['id'],$net,$o['sale_mode']??($net>0?'direct':'request'),(int)$p['active'],(int)($profile['max_purchase_cents']??0),$i++]);
                }
            }
            $db->exec("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('domain_catalog_import_v1','1')");$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}

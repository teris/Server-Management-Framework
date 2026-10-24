<?php
function notification_admin_target(array $notification): string {
    $parts=parse_url($notification['url']);$path=$parts['path']??'';
    $routes=['/konto/bestellungen'=>'/admin/bestellungen','/konto/angebote'=>'/admin/angebote','/konto/rechnungen'=>'/admin/rechnungen','/konto/vertraege'=>'/admin/vertraege'];
    if($path==='/konto/vps-sicherungen')return '/admin/vps-sicherungen';
    if(isset($routes[$path]))return $routes[$path].(isset($parts['query'])?'?'.$parts['query']:'');
    if($path==='/konto/verwalten'){parse_str($parts['query']??'',$args);return '/admin/ressourcen?produkt='.(int)($args['id']??0);}
    return '/admin/kunden?id='.(int)$notification['customer_id'];
}
function notification_admin_copy(PDO $db,int $administrator,array $notification): void {
    $key='customer:'.hash('sha256',$notification['event_key']);
    try{
        $q=$db->prepare('INSERT INTO shop_admin_notifications (admin_id,title,body,url,event_key,created_at) VALUES (?,?,?,?,?,?)');
        $q->execute([$administrator,$notification['title'],'Kunde #'.(int)$notification['customer_id'].': '.$notification['body'],notification_admin_target($notification),$key,$notification['created_at']]);
    }catch(PDOException $e){$q=$db->prepare('SELECT id FROM shop_admin_notifications WHERE admin_id=? AND event_key=?');$q->execute([$administrator,$key]);if(!$q->fetchColumn())throw $e;}
}
function shop_migrate_notifications(PDO $db): void {
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(shop_notifications)')->fetchAll(PDO::FETCH_ASSOC),'name'):array_column($db->query('SHOW COLUMNS FROM shop_notifications')->fetchAll(PDO::FETCH_ASSOC),'Field');
    foreach(['archived_ts','deleted_ts'] as $column)if(!in_array($column,$columns,true))$db->exec('ALTER TABLE shop_notifications ADD COLUMN '.$column.' INT NOT NULL DEFAULT 0');
    $id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';$suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_admin_notifications (id $id,admin_id INT NOT NULL,title VARCHAR(200) NOT NULL,body TEXT NOT NULL,url VARCHAR(500) NOT NULL,event_key VARCHAR(180) NOT NULL,is_read INT NOT NULL DEFAULT 0,archived_ts INT NOT NULL DEFAULT 0,deleted_ts INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(admin_id,event_key),FOREIGN KEY(admin_id) REFERENCES users(id))".$suffix);
    if(!$db->query("SELECT meta_value FROM shop_meta WHERE meta_key='admin_notification_history_v1'")->fetchColumn()){
        $owns=!$db->inTransaction();if($owns)$db->beginTransaction();try{
            $admins=$db->query("SELECT id FROM users WHERE role='admin' AND active='y'")->fetchAll(PDO::FETCH_COLUMN);
            $rows=$db->query('SELECT * FROM shop_notifications WHERE deleted_ts=0 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            foreach($admins as $admin)foreach($rows as $row)notification_admin_copy($db,(int)$admin,$row);
            $db->exec("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('admin_notification_history_v1','1')");if($owns)$db->commit();
        }catch(Throwable $e){if($owns && $db->inTransaction())$db->rollBack();throw $e;}
    }
}
function notification_broadcast_admins(array $notification): void {
    if((int)$notification['deleted_ts']>0)return;
    foreach(all("SELECT id FROM users WHERE role='admin' AND active='y'") as $admin)notification_admin_copy(db(),(int)$admin['id'],$notification);
}
function notification_storage(string $scope): array {
    return match($scope){'customer'=>['shop_notifications','customer_id'],'admin'=>['shop_admin_notifications','admin_id'],default=>throw new RuntimeException('Ungültiger Benachrichtigungsbereich.')};
}
function notification_actor(string $scope): array {
    return match($scope){'customer'=>require_user(),'admin'=>require_admin(),default=>throw new RuntimeException('Ungültiger Benachrichtigungsbereich.')};
}
function notification_unread_count(string $scope,int $actor): int {
    [$table,$owner]=notification_storage($scope);
    return (int)query("SELECT COUNT(*) FROM $table WHERE $owner=? AND is_read=0 AND archived_ts=0 AND deleted_ts=0",[$actor])->fetchColumn();
}
function notification_folder(mixed $value): string {return $value==='archive'?'archive':'inbox';}
function notification_overview(string $scope,int $actor,string $folder,int $page=1): array {
    [$table,$owner]=notification_storage($scope);$folder=notification_folder($folder);
    $counts=one("SELECT SUM(CASE WHEN archived_ts=0 THEN 1 ELSE 0 END) AS inbox,SUM(CASE WHEN archived_ts>0 THEN 1 ELSE 0 END) AS archive FROM $table WHERE $owner=? AND deleted_ts=0",[$actor]);
    $counts=array_map('intval',$counts);$pages=max(1,(int)ceil($counts[$folder]/25));$page=max(1,min($pages,$page));$offset=($page-1)*25;$condition=$folder==='archive'?'archived_ts>0':'archived_ts=0';
    $rows=all("SELECT * FROM $table WHERE $owner=? AND deleted_ts=0 AND $condition ORDER BY id DESC LIMIT 25 OFFSET $offset",[$actor]);
    return compact('rows','counts','page','pages','folder');
}
function notification_change(string $scope,int $actor,int $id,string $operation): void {
    [$table,$owner]=notification_storage($scope);
    if(!in_array($operation,['archive','restore','read','delete'],true))throw new RuntimeException('Ungültige Benachrichtigungsaktion.');
    if($id<1 || !one("SELECT id FROM $table WHERE id=? AND $owner=? AND deleted_ts=0",[$id,$actor]))throw new RuntimeException('Diese Benachrichtigung ist nicht verfügbar.');
    $now=max(1,shop_now());$set=match($operation){'archive'=>'archived_ts='.$now.',is_read=1','restore'=>'archived_ts=0','read'=>'is_read=1','delete'=>"deleted_ts=$now,is_read=1,title='',body='',url=''"};
    // Keep the event key as a tombstone so a provider retry cannot recreate a deleted alert.
    query("UPDATE $table SET $set WHERE id=? AND $owner=? AND deleted_ts=0",[$id,$actor]);
}
function notification_mark_all_read(string $scope,int $actor): void {
    [$table,$owner]=notification_storage($scope);query("UPDATE $table SET is_read=1 WHERE $owner=? AND archived_ts=0 AND deleted_ts=0",[$actor]);
}
function handle_notification_action(string $action): bool {
    $operations=['notification_archive'=>'archive','notification_restore'=>'restore','notification_read'=>'read','notification_delete'=>'delete'];
    if(!isset($operations[$action]) && $action!=='notifications_read')return false;
    $scope=(string)($_POST['scope']??($action==='notifications_read'?'customer':''));$actor=notification_actor($scope);
    if($action==='notifications_read'){notification_mark_all_read($scope,(int)$actor['id']);flash('Alle Benachrichtigungen im Posteingang wurden als gelesen markiert.');}
    else{
        $raw=$_POST['notification_id']??null;if(!is_string($raw) || !ctype_digit($raw) || strlen($raw)>10)throw new RuntimeException('Diese Benachrichtigung ist nicht verfügbar.');
        notification_change($scope,(int)$actor['id'],(int)$raw,$operations[$action]);
        flash(match($operations[$action]){'archive'=>'Die Benachrichtigung wurde archiviert.','restore'=>'Die Benachrichtigung wurde in den Posteingang verschoben.','read'=>'Die Benachrichtigung wurde als gelesen markiert.','delete'=>'Die Benachrichtigung wurde gelöscht.'});
    }
    $folder=notification_folder($_POST['folder']??'inbox');$page=max(1,min(100000,(int)($_POST['notification_page']??1)));
    redirect(($scope==='admin'?'/admin/nachrichten':'/konto/nachrichten').'?ordner='.$folder.'&seite='.$page);
}

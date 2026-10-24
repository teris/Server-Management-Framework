<?php
/** Maps the deployed name-based module schema without discarding original fields or rows. */
final class LegacyModuleSchema {
    public static function prepare(PDO $db): void {
        if(!SchemaManager::hasTable($db,'module_dependencies'))return;
        $columns=SchemaManager::columns($db,'module_dependencies');
        if(!isset($columns['module_name'],$columns['dependency_name']) || isset($columns['module_id'],$columns['dependency_id']))return;
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);$quote=fn($name)=>SchemaManager::identifier($name,$driver);
        $db->exec('CREATE TABLE IF NOT EXISTS '.$quote('modules').' (id '.SchemaManager::column('INTEGER PRIMARY KEY AUTOINCREMENT',$driver).',name VARCHAR(100) NOT NULL,is_active INTEGER NOT NULL DEFAULT 0)');
        $rows=$db->query('SELECT id,module_name,dependency_name FROM module_dependencies')->fetchAll(PDO::FETCH_ASSOC);$names=[];
        foreach($rows as$row){$names[$row['module_name']]=true;$names[$row['dependency_name']]=true;}
        if(SchemaManager::hasTable($db,'active_modules')&&isset(SchemaManager::columns($db,'active_modules')['is_active']))foreach($db->query('SELECT module_name,is_active FROM active_modules')->fetchAll(PDO::FETCH_ASSOC)as$row)$names[$row['module_name']]=(bool)$row['is_active'];
        $mapping=[];
        foreach($names as$name=>$active){
            $query=$db->prepare('SELECT id FROM modules WHERE name=?');$query->execute([$name]);$ids=$query->fetchAll(PDO::FETCH_COLUMN);$query->closeCursor();if(count($ids)>1)throw new RuntimeException('Ambiguous legacy module identity');
            if(!$ids){$db->prepare('INSERT INTO modules (name,is_active) VALUES (?,?)')->execute([$name,$active?1:0]);$mapping[$name]=(int)$db->lastInsertId();}else $mapping[$name]=(int)$ids[0];
        }
        foreach(['module_id','dependency_id']as$field)if(!isset($columns[$field]))$db->exec('ALTER TABLE module_dependencies ADD COLUMN '.$quote($field).' INTEGER');
        foreach($rows as$row)$db->prepare('UPDATE module_dependencies SET module_id=?,dependency_id=? WHERE id=?')->execute([$mapping[$row['module_name']],$mapping[$row['dependency_name']],$row['id']]);
        if(SchemaManager::hasTable($db,'active_modules')){
            $active=SchemaManager::columns($db,'active_modules');
            if(!isset($active['module_id']))$db->exec('ALTER TABLE active_modules ADD COLUMN module_id INTEGER');
            if(!isset($active['enabled']))$db->exec('ALTER TABLE active_modules ADD COLUMN enabled INTEGER NOT NULL DEFAULT 0');
            foreach($mapping as$name=>$id)$db->prepare('UPDATE active_modules SET module_id=?'.(isset($active['is_active'])?',enabled=is_active':'').' WHERE module_name=?')->execute([$id,$name]);
        }
    }
}

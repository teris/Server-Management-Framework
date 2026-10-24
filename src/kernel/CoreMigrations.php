<?php
namespace SMF\Core;
use PDO;
final class CoreMigrations {
    public static function migrate(PDO $db): void {
        $schema = json_decode(file_get_contents(dirname(__DIR__,2) . '/database/kernel-schema.json'), true, 64, JSON_THROW_ON_ERROR);
        if(\SchemaManager::hasTable($db,'smf_core_migrations')) {
            $q=$db->prepare('SELECT checksum FROM smf_core_migrations WHERE version=?');$q->execute([$schema['version']]);$checksum=$q->fetchColumn();
            if($checksum){if(!hash_equals($checksum,hash_file('sha256',dirname(__DIR__,2).'/database/kernel-schema.json')))throw new \RuntimeException('Applied core migration changed');return;}
        }
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($schema['tables'] as $name => $table) {
            $db->exec(\SchemaManager::createTable($name,$table,$driver));
            foreach ($table['indexes'] as $index => $columns) {
                if ($driver === 'mysql') {
                    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$name,$index]);if($q->fetchColumn())continue;
                }
                $db->exec('CREATE INDEX ' . ($driver==='mysql'?'':'IF NOT EXISTS ') . \SchemaManager::identifier($index,$driver) . ' ON ' . \SchemaManager::identifier($name,$driver) . ' (' . implode(',',$columns) . ')');
            }
        }
        $db->prepare('INSERT INTO smf_core_migrations (version,checksum,applied_at) VALUES (?,?,?)')->execute([$schema['version'],hash_file('sha256',dirname(__DIR__,2).'/database/kernel-schema.json'),time()]);
    }
}

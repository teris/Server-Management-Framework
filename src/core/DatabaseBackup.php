<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/SchemaManager.php';

/** Engine-native, private snapshots. Credentials are passed in the child environment, never argv or logs. */
final class DatabaseBackup {
    private static function objects(PDO $db): array {
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);$result=['tables'=>[],'views'=>[],'sequences'=>[]];
        if($driver==='mysql'){
            foreach($db->query('SELECT table_name,table_type FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_ASSOC)as$row){$row=array_change_key_case($row,CASE_LOWER);$result[$row['table_type']==='VIEW'?'views':'tables'][]=$row['table_name'];}
        }elseif($driver==='pgsql'){
            foreach($db->query("SELECT relname,relkind FROM pg_class WHERE relnamespace=current_schema()::regnamespace AND relkind IN ('r','p','v','S')")->fetchAll(PDO::FETCH_ASSOC)as$row)$result[$row['relkind']==='v'?'views':($row['relkind']==='S'?'sequences':'tables')][]=$row['relname'];
        }
        return $result;
    }
    private static function removeNewObjects(PDO $db,array $saved):void{
        $current=self::objects($db);$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if($driver==='mysql')$db->exec('SET FOREIGN_KEY_CHECKS=0');
        try{foreach(['views'=>'VIEW','tables'=>'TABLE','sequences'=>'SEQUENCE']as$kind=>$sql){
            $names=array_diff($current[$kind],$saved[$kind]);if(!$names)continue;
            $db->exec('DROP '.$sql.' IF EXISTS '.implode(',',array_map(fn($name)=>SchemaManager::identifier($name,$driver),$names)));
        }}finally{if($driver==='mysql')$db->exec('SET FOREIGN_KEY_CHECKS=1');}
    }
    public static function fingerprint(PDO $db): string {
        $context = hash_init('sha256'); $tables = SchemaManager::tables($db); sort($tables);
        foreach ($tables as $table) {
            $stmt = $db->query('SELECT * FROM ' . SchemaManager::identifier($table, $db->getAttribute(PDO::ATTR_DRIVER_NAME))); $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                ksort($row); foreach ($row as &$value) if (is_resource($value)) $value = stream_get_contents($value); unset($value);
                $rows[] = hash('sha256', serialize($row));
            }
            $stmt->closeCursor(); sort($rows); hash_update($context, $table . "\0" . implode('', $rows));
        }
        return hash_final($context);
    }
    public static function command(array $arguments, array $environment = [], ?string $inputFile = null): void {
        $pipes = []; $process = proc_open($arguments, [0=>$inputFile === null ? ['pipe','r'] : ['file',$inputFile,'r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, array_merge(getenv(), $environment));
        if (!is_resource($process)) throw new RuntimeException('Backup/migration process could not start');
        if (isset($pipes[0])) fclose($pipes[0]);
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false); $started = time(); $exit = null;
        do {
            // Drain both streams; errors can contain credentials and are deliberately not retained.
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); $status = proc_get_status($process);
            if (!$status['running']) { $exit = $status['exitcode']; break; }
            if (time() - $started > 300) { proc_terminate($process); $exit = 124; break; }
            usleep(20000);
        } while (true);
        fclose($pipes[1]); fclose($pipes[2]); $closed = proc_close($process);
        if (($exit ?? $closed) !== 0) throw new RuntimeException('Backup/migration process failed; active deployment requires recovery');
    }
    private static function mysqlArguments(): array {
        $args = ['--user=' . Config::DB_USER];
        if (getenv('SMF_MYSQL_PLUGIN_DIR')) $args[] = '--plugin-dir=' . getenv('SMF_MYSQL_PLUGIN_DIR');
        if (defined('Config::DB_SOCKET') && Config::DB_SOCKET !== '') $args[] = '--socket=' . Config::DB_SOCKET;
        else { $args[] = '--host=' . Config::DB_HOST; $args[] = '--port=' . Config::DB_PORT; }
        return $args;
    }
    private static function pgArguments(): array { return ['--host=' . Config::DB_PGSQL_HOST,'--port=' . Config::DB_PGSQL_PORT,'--username=' . Config::DB_PGSQL_USER]; }
    public static function capture(PDO $db, string $directory): array {
        if (!is_dir($directory) || is_link($directory)) throw new RuntimeException('Private backup directory required');
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME); $file = $directory . '/database-' . bin2hex(random_bytes(12)) . '.backup'; $path = null;
        if ($driver === 'sqlite') {
            if (!class_exists('SQLite3')) throw new RuntimeException('SQLite backup requires ext-sqlite3');
            foreach ($db->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $database) if ($database['name'] === 'main') $path = $database['file'];
            if (!$path || !is_file($path) || is_link($path)) throw new RuntimeException('Updater requires a regular SQLite database file');
            $stmt = $db->prepare('VACUUM main INTO ?'); $stmt->execute([$file]);
        } elseif ($driver === 'mysql') {
            if ($db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND engine<>'InnoDB'")->fetchColumn()) throw new RuntimeException('Automatic update backup requires exclusively InnoDB tables');
            self::command([getenv('SMF_MYSQL_DUMP_BIN') ?: 'mariadb-dump','--no-defaults',...self::mysqlArguments(),'--single-transaction','--skip-lock-tables','--routines','--triggers','--no-tablespaces','--result-file=' . $file, Config::DB_NAME], ['MYSQL_PWD'=>Config::DB_PASS]);
        } elseif ($driver === 'pgsql') {
            self::command([getenv('SMF_PG_DUMP_BIN') ?: 'pg_dump',...self::pgArguments(),'--format=custom','--no-owner','--file=' . $file,Config::DB_PGSQL_NAME], ['PGPASSWORD'=>Config::DB_PGSQL_PASS]);
        } else throw new RuntimeException('Unsupported backup engine');
        if (!is_file($file) || is_link($file) || filesize($file) < 1) throw new RuntimeException('Database backup missing');
        chmod($file, 0600);
        return ['driver'=>$driver,'file'=>$file,'sha256'=>hash_file('sha256',$file),'sqlite_path'=>$path,'objects'=>self::objects($db)];
    }
    public static function restore(array $snapshot): void {
        $file = $snapshot['file'];
        if (is_link($file) || !is_file($file) || !hash_equals($snapshot['sha256'],hash_file('sha256',$file))) throw new RuntimeException('Backup integrity check failed');
        if ($snapshot['driver'] === 'sqlite') {
            $source = new SQLite3($file, SQLITE3_OPEN_READONLY); $destination = new SQLite3($snapshot['sqlite_path']); $destination->busyTimeout(10000);
            try { if (!$source->backup($destination)) throw new RuntimeException('SQLite restore failed'); }
            finally { $source->close(); $destination->close(); }
        } elseif ($snapshot['driver'] === 'mysql') {
            self::removeNewObjects(HttpSecurity::connection(),$snapshot['objects']);
            self::command([getenv('SMF_MYSQL_BIN') ?: 'mariadb','--no-defaults',...self::mysqlArguments(),Config::DB_NAME], ['MYSQL_PWD'=>Config::DB_PASS], $file);
        } elseif ($snapshot['driver'] === 'pgsql') {
            self::removeNewObjects(HttpSecurity::connection(),$snapshot['objects']);
            self::command([getenv('SMF_PG_RESTORE_BIN') ?: 'pg_restore',...self::pgArguments(),'--clean','--if-exists','--no-owner','--exit-on-error','--dbname=' . Config::DB_PGSQL_NAME,$file], ['PGPASSWORD'=>Config::DB_PGSQL_PASS]);
        } else throw new RuntimeException('Unsupported restore engine');
    }
}

<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$testRoot = sys_get_temp_dir() . '/smf-tests-' . bin2hex(random_bytes(8));
mkdir($testRoot, 0700, true);
putenv('SMF_PRIVATE_DIR=' . $testRoot . '/private');
$config = file_get_contents(dirname(__DIR__) . '/config/config.example.php');
$config = preg_replace("/const DB_TYPE\s*=\s*'[^']+';/", "const DB_TYPE = 'sqlite';", $config);
$config = preg_replace('/const DB_SQLITE_USEING\s*=\s*false;/i', 'const DB_SQLITE_USEING = true;', $config);
$config = preg_replace("/const DB_SQLITE_PATH\s*=\s*'[^']+';/", 'const DB_SQLITE_PATH = ' . var_export($testRoot . '/test.sqlite', true) . ';', $config);
file_put_contents($testRoot . '/config.php', $config);
putenv('SMF_CONFIG_FILE=' . $testRoot . '/config.php');
require_once dirname(__DIR__) . '/src/sys.conf.php';
require_once dirname(__DIR__) . '/framework.php';
require_once dirname(__DIR__) . '/src/auth_handler.php';
require_once dirname(__DIR__) . '/src/core/SecuritySchema.php';
require_once dirname(__DIR__) . '/src/core/ResourceAccess.php';
require_once dirname(__DIR__) . '/src/core/PrivateFiles.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec(file_get_contents(dirname(__DIR__) . '/install/database-structure-install-sqlite.sql'));
HttpSecurity::useConnection($db);
SecuritySchema::migrate($db);
foreach ([['admin','admin','y'],['user-a','user','y'],['user-b','user','y'],['disabled','admin','n']] as $i => $user) {
    $stmt=$db->prepare('INSERT INTO users (id,username,email,password_hash,full_name,role,active) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$i+1,$user[0],$user[0].'@example.invalid',password_hash('Test password number 1', PASSWORD_DEFAULT),$user[0],$user[1],$user[2]]);
}
foreach (['a','b'] as $i=>$name) {
    $stmt=$db->prepare("INSERT INTO customers (id,first_name,last_name,email,password_hash,status) VALUES (?,?,?,?,?,'active')");
    $stmt->execute([$i+1,$name,'Customer',$name.'@example.invalid',password_hash('Customer password 1', PASSWORD_DEFAULT)]);
}
foreach ($plugins as &$plugin) $plugin['enabled'] = true;
unset($plugin);
HttpSecurity::session();
function testLogin($realm, $id) {
    $db=HttpSecurity::connection();$stmt=$db->prepare('SELECT * FROM '.($realm==='backend'?'users':'customers').' WHERE id=?');$stmt->execute([$id]);
    return Identity::login($realm, $stmt->fetch(PDO::FETCH_ASSOC));
}
register_shutdown_function(function () use ($testRoot) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $p) $p->isDir() && !$p->isLink() ? rmdir($p) : unlink($p);
    rmdir($testRoot);
});

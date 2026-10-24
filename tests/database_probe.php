<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/framework.php';
require_once dirname(__DIR__) . '/src/sys.conf.php';
require_once dirname(__DIR__) . '/src/auth_handler.php';
require_once dirname(__DIR__) . '/src/core/SchemaManager.php';
require_once dirname(__DIR__) . '/src/core/SecuritySchema.php';
require_once dirname(__DIR__) . '/src/core/RegistrationService.php';
require_once dirname(__DIR__) . '/src/core/UserAdministration.php';
require_once dirname(__DIR__) . '/src/core/ProviderUserActions.php';
$checks = 0;
function verifyCase($condition, string $name): void { global $checks; $checks++; if (!$condition) throw new RuntimeException('Failed: ' . $name); }
$db = DatabaseManager::getInstance()->getConnection(); HttpSecurity::useConnection($db);
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME); $legacy = in_array($argv[1] ?? '', ['legacy','legacy_full'], true);
if ($driver !== 'sqlite' && !str_starts_with(Config::DB_TYPE === 'mysql' ? Config::DB_NAME : Config::DB_PGSQL_NAME, 'smf_test_')) throw new RuntimeException('Use an isolated smf_test_ database');
if ($legacy) {
    $engine = $driver === 'pgsql' ? 'postgresql' : $driver;
    $legacySql=file_get_contents(__DIR__ . '/fixtures/' . (($argv[1] ?? '') === 'legacy_full' ? 'schema-full-3.2.7-' : 'schema-3.2.7-') . $engine . '.sql');
    $legacySql=preg_replace('/^(?:CREATE DATABASE[^;]*;|USE [^;]*;)\s*$/mi','',$legacySql);
    $legacySql=preg_replace('/^\\\\[^\n]*$/m','',$legacySql);
    // These two aggregate views in the original PostgreSQL full script cannot be created (missing GROUP BY fields).
    if ($driver === 'pgsql' && ($argv[1] ?? '') === 'legacy_full') $legacySql=preg_replace('/CREATE VIEW (?:vm_overview|website_overview) AS[\s\S]*?;/i','',$legacySql);
    $db->exec($legacySql);
    if ($db->inTransaction()) $db->commit();
    if ($driver === 'mysql') $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $db->exec('ALTER TABLE users ADD COLUMN operator_note VARCHAR(100)');
    $db->exec('CREATE INDEX custom_operator_note ON users(operator_note)');
    $db->exec('CREATE TABLE operator_data (value VARCHAR(100))'); $db->exec("INSERT INTO operator_data (value) VALUES ('keep-custom-table')");
    $db->exec('CREATE TABLE email_templates (id ' . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : ($driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTO_INCREMENT')) . ', template_name VARCHAR(255) NOT NULL UNIQUE, subject TEXT NOT NULL, html_content TEXT, raw_content TEXT, variables TEXT)');
    $db->exec("INSERT INTO email_templates (template_name,subject,html_content) VALUES ('Kunden-Verifikations-E-Mail','Custom subject','Custom {verificationLink} {password}')");
    $stmt = $db->prepare("INSERT INTO users (username,email,password_hash,role,active,operator_note) VALUES ('original','original@example.invalid',?,'user','y','keep-extra-column')"); $originalHash = password_hash('Original test password', PASSWORD_DEFAULT); $stmt->execute([$originalHash]);
    $db->exec('CREATE VIEW operator_names AS SELECT username,operator_note FROM users');
}
$first = SchemaManager::migrate($db); SecuritySchema::migrate($db); \SMF\Core\CoreMigrations::migrate($db);
require_once __DIR__ . '/sql_queries.php'; $staticQueries = checkApplicationQueries($db);
verifyCase($first['changed'], 'first explicit migration applies');
verifyCase(!SchemaManager::migrate($db)['changed'], 'second migration is idempotent');
foreach (SchemaManager::definition()['tables'] as $name => $table) verifyCase(!array_diff(array_keys($table['columns']), array_keys(SchemaManager::columns($db, $name))), 'schema columns: ' . $name);
if ($legacy) {
    verifyCase($db->query("SELECT password_hash FROM users WHERE username='original'")->fetchColumn() === $originalHash, 'password hash preserved');
    verifyCase($db->query("SELECT operator_note FROM users WHERE username='original'")->fetchColumn() === 'keep-extra-column', 'custom field preserved');
    verifyCase($db->query("SELECT operator_note FROM operator_names WHERE username='original'")->fetchColumn() === 'keep-extra-column', 'custom view preserved');
    verifyCase($db->query('SELECT value FROM operator_data')->fetchColumn() === 'keep-custom-table', 'custom table preserved');
    verifyCase($db->query("SELECT html_content FROM email_templates WHERE template_name='Kunden-Verifikations-E-Mail'")->fetchColumn() === 'Custom {verificationLink} {password}', 'custom template preserved');
} else verifyCase((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'fresh install has no default accounts');
$stmt = $db->prepare("INSERT INTO users (username,email,password_hash,full_name,role,active) VALUES ('test-admin','admin@example.invalid',?,'Test admin','admin','y')"); $stmt->execute([password_hash('Admin initial test password', PASSWORD_DEFAULT)]); $admin = (int) $db->lastInsertId();
$record = $db->query('SELECT * FROM users WHERE id=' . $admin)->fetch(PDO::FETCH_ASSOC); Identity::login('backend', $record);
$messages = []; $mail = new EmailTemplateManager($db, function ($email, $subject, $body) use (&$messages) { $messages[] = [$email,$subject,$body]; return true; }); $mail->createDefaultTemplates();
if ($legacy) verifyCase($db->query("SELECT subject FROM email_templates WHERE template_name='Kunden-Verifikations-E-Mail'")->fetchColumn() === 'Custom subject', 'seeding retains customized template');
$service = new RegistrationService($db, $mail);
$input = ['first_name'=>'A <script>alert(1)</script>','last_name'=>'Customer','email'=>'New@example.invalid','password'=>'Very secret registration password','confirm_password'=>'Very secret registration password','accept_terms'=>'1'];
$result = $service->register($input); verifyCase($result['success'] && $result['sent'], 'registration delivers verification');
verifyCase(strpos(end($messages)[2], $input['password']) === false && strpos(end($messages)[2], '<script>') === false, 'no password or HTML injection in email');
preg_match('/token=([a-f0-9]{64})/', end($messages)[2], $match); $token = $match[1];
verifyCase((int) $db->query('SELECT COUNT(*) FROM account_tokens')->fetchColumn() === 1, 'one active token model');
verifyCase($db->query('SELECT token_hash FROM account_tokens')->fetchColumn() !== $token, 'only token hash persisted');
verifyCase(!CustomerAuthentication::login('new@example.invalid', $input['password']), 'pending account cannot login');
verifyCase(!$service->register($input)['success'], 'registration repeat creates no duplicate');
verifyCase($service->verify($token), 'one-time activation succeeds'); verifyCase(!$service->verify($token), 'activation replay denied');
verifyCase(CustomerAuthentication::login('NEW@example.invalid', $input['password']), 'verified customer can login case-insensitively');
verifyCase(!$service->register($input + [])['success'], 'active account is not replaced');
$failedMail = new EmailTemplateManager($db, function () { throw new RuntimeException('SMTP fixture unavailable'); });
$failedInput = $input; $failedInput['email']='retry@example.invalid'; $failed = (new RegistrationService($db,$failedMail))->register($failedInput);
verifyCase($failed['success'] && !$failed['sent'], 'SMTP failure retains pending account and reports undelivered');
verifyCase($service->resend('retry@example.invalid'), 'resend can recover after SMTP outage'); preg_match('/token=([a-f0-9]{64})/',end($messages)[2],$retry); $oldRetry=$retry[1];
verifyCase($service->resend('retry@example.invalid'), 'explicit resend replaces prior token'); verifyCase(!$service->verify($oldRetry), 'prior resend token revoked');
preg_match('/token=([a-f0-9]{64})/',end($messages)[2],$retry); $db->prepare('UPDATE account_tokens SET expires_at=? WHERE token_hash=?')->execute([time()-1,hash('sha256',$retry[1])]); verifyCase(!$service->verify($retry[1]), 'expired verification denied');
$reset = new PasswordReset($db,$mail); verifyCase($reset->request('customer','new@example.invalid'), 'reset link delivery'); preg_match('/token=([a-f0-9]{64})/',end($messages)[2],$resetToken);
verifyCase(!$reset->complete($resetToken[1],'short'), 'short reset password rejected'); verifyCase($reset->complete($resetToken[1],'Updated customer test password'), 'reset changes local password'); verifyCase(!$reset->complete($resetToken[1],'Another customer test password'), 'reset replay rejected'); verifyCase(!CustomerAuthentication::login('new@example.invalid',$input['password']), 'old password invalid after reset');
Identity::login('backend',$record); $users = new UserAdministration($db, null, $mail);
$created = $users->save('backend',['username'=>'test-user','email'=>'user@example.invalid','password'=>'Backend test user password','role'=>'readonly','active'=>'y']); verifyCase($created['success'], 'create readonly backend user');
verifyCase(!isset($users->listing('backend')[0]['password_hash']), 'listing never exposes password hash');
$blocked=false; try { $users->delete('backend',$admin); } catch (InvalidArgumentException $e) { $blocked=true; } verifyCase($blocked,'last administrator preserved');
verifyCase($users->delete('backend',$created['id'])['success'],'unlinked local user deletion succeeds');
verifyCase(Validation::errors(['quota'=>'1024'],['quota'=>'required|numeric|min:0|max:2048']) === [],'numeric limits compare numbers');
verifyCase(isset(Validation::errors(['value'=>'   '],['value'=>'required'])['value']),'blank required input rejected');
verifyCase(Validation::errors(['value'=>'0'],['value'=>'required|numeric|min:0']) === [],'zero is a valid required value');
$language = LanguageManager::getInstance(); verifyCase(is_array($language->getAvailableLanguages()) && $language->setLanguage('en'),'comma-separated language configuration normalized'); verifyCase(!$language->setLanguage('../private'),'invalid language rejected');
DatabaseManager::getInstance()->logAction('Fixture','No credentials','success'); verifyCase(count(DatabaseManager::getInstance()->getActivityLog(1)) === 1,'portable activity log'); DatabaseManager::getInstance()->clearActivityLogs(); verifyCase((int) $db->query('SELECT COUNT(*) FROM activity_log')->fetchColumn() === 0,'portable log deletion');
if (getenv('SMF_TEST_NATIVE_BACKUP') === '1') {
    require_once dirname(__DIR__) . '/src/core/DatabaseBackup.php';
    $db->exec('CREATE TABLE backup_probe (id INTEGER PRIMARY KEY, value VARCHAR(80))'); $db->exec("INSERT INTO backup_probe VALUES (1, 'before-backup')");
    $before = DatabaseBackup::fingerprint($db); $snapshot = DatabaseBackup::capture($db, HttpSecurity::privateDirectory('backups'));
    $db->exec("UPDATE backup_probe SET value='after-backup'");
    $db->exec('CREATE TABLE backup_extra_probe (value VARCHAR(80))');$db->exec('CREATE VIEW backup_extra_view AS SELECT value FROM backup_extra_probe');
    DatabaseBackup::restore($snapshot);
    verifyCase($db->query('SELECT value FROM backup_probe')->fetchColumn() === 'before-backup', 'engine-native database restore');
    verifyCase(hash_equals($before, DatabaseBackup::fingerprint($db)), 'native restore retains all rows and values');
    verifyCase(!SchemaManager::hasTable($db,'backup_extra_probe'), 'native restore removes migration-created tables and dependent views');
    file_put_contents($snapshot['file'], 'corrupted fixture', FILE_APPEND); $blocked=false; try { DatabaseBackup::restore($snapshot); } catch (RuntimeException $e) { $blocked=true; }
    verifyCase($blocked, 'tampered native backup refused before restore');
}
echo json_encode(['driver'=>$driver,'server_version'=>$db->getAttribute(PDO::ATTR_SERVER_VERSION),'legacy'=>$legacy,'fixture'=>$argv[1] ?? 'fresh','static_queries_checked'=>$staticQueries,'legacy_ddl_adjustments'=>($driver==='pgsql'&&($argv[1]??'')==='legacy_full')?['psql routing removed','reserved identifier quoted by adapter','two invalid aggregate views excluded from fixture import']:[],'checks'=>$checks,'failed'=>0]) . "\n";

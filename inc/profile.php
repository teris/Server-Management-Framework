<?php
require_once dirname(__DIR__) . '/sys.conf.php';
require_once dirname(__DIR__) . '/config/config.inc.php';

// Prüfe, ob der Nutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = new PDO('mysql:host=' . Config::DB_HOST . ';dbname=' . Config::DB_NAME, Config::DB_USER, Config::DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('<div class="alert alert-danger">Fehler bei der Datenbankverbindung: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

$message = '';

// Profildaten aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_update'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old_password = $_POST['old_password'] ?? '';
    try {
        if (!empty($password) && !empty($old_password)) {
            // Prüfe altes Passwort
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($old_password, $row['password_hash'])) {
                throw new Exception('Das alte Passwort ist nicht korrekt.');
            }
            $pw_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, password_hash=?, password_changed_at=NOW(), updated_at=NOW() WHERE id=?');
            $stmt->execute([$full_name, $email, $pw_hash, $user_id]);
        } elseif (empty($password) && empty($old_password)) {
            // Nur Name und E-Mail ändern, updated_at aktualisieren
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$full_name, $email, $user_id]);
        } else {
            // Ein Passwortfeld ist leer, das ist nicht erlaubt
            throw new Exception('Bitte beide Passwortfelder ausfüllen, um das Passwort zu ändern.');
        }
        $message = '<div class="alert alert-success">' . tcore('profil_gespeichert') . '</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Fehler beim Speichern: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Profildaten laden (inkl. Timestamps)
$stmt = $pdo->prepare('SELECT username, full_name, email, last_login, password_changed_at, created_at, updated_at FROM users WHERE id=?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('<div class="alert alert-danger">Benutzer nicht gefunden.</div>');
}

// Datumsformat aus Konfiguration
$date_format = $system_config['date_format'] ?? 'd.m.Y H:i:s';
function formatTimestamp($ts, $format) {
    if (empty($ts) || $ts === '0000-00-00 00:00:00' || $ts === null) return '-';
    return date($format, strtotime($ts));
}

// LanguageManager ist global verfügbar (index.php)
$lm = isset($lang) ? $lang : (function_exists('getLanguageManager') ? getLanguageManager() : null);
function tcore($key, $default = null) {
    global $lm;
    return $lm ? $lm->translateCore($key, $default) : ($default ?? $key);
}
?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-person"></i> <?= tcore('mein_profil') ?></h2>
            </div>
            <div class="card-body">
                <?= $message ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label for="username" class="form-label"><?= tcore('benutzername') ?></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label"><?= tcore('vollstaendiger_name') ?></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?= tcore('email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="old_password" class="form-label"><?= tcore('altes_passwort_erforderlich') ?></label>
                        <input type="password" class="form-control" id="old_password" name="old_password" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><?= tcore('neues_passwort') ?></label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                        <div class="form-text text-muted"><?= tcore('passwort_nicht_angezeigt') ?></div>
                    </div>
                    <button type="submit" name="profile_update" class="btn btn-primary"><?= tcore('profil_speichern') ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-clock"></i> <?= tcore('zeitstempel') ?></h2>
            </div>
            <div class="card-body">
                <p><strong><?= tcore('letzter_login') ?>:</strong> <?= formatTimestamp($user['last_login'], $date_format) ?></p>  
                <p><strong><?= tcore('passwort_zuletzt_geaendert') ?>:</strong> <?= formatTimestamp($user['password_changed_at'], $date_format) ?></p>
                <p><strong><?= tcore('profil_erstellt') ?>:</strong> <?= formatTimestamp($user['created_at'], $date_format) ?></p>
                <p><strong><?= tcore('letztes_update') ?>:</strong> <?= formatTimestamp($user['updated_at'], $date_format) ?></p>
            </div>
        </div>
    </div>
</div>

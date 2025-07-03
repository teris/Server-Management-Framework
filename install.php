<?php
// install.php
// Installationsskript für das Framework

session_start();

$step = $_SESSION['install_step'] ?? 1;
$error_message = '';
$success_message = '';

// Konfigurationspfad
// Pr�fen, ob Konstanten bereits definiert sind, um Fehler zu vermeiden, falls die Datei mehrmals eingebunden wird.
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', __DIR__ . '/config/config.inc.php');
}
if (!defined('SQL_PATH')) {
    define('SQL_PATH', __DIR__ . '/database-structure.sql');
} // Fehlende schließende Klammer hinzugefügt

// Prüfen, ob die Konfigurationsdatei bereits existiert und ausgefüllt ist
function is_configured() {
    if (!file_exists(CONFIG_PATH)) {
        return false;
    }
    $content = file_get_contents(CONFIG_PATH);
    return strpos($content, "const DB_USER 				= '';") === false;
}

if (is_configured() && !isset($_GET['force_install'])) {
    $error_message = "Das System scheint bereits konfiguriert zu sein. Um die Installation erneut auszuführen, fügen Sie '?force_install=true' zur URL hinzu. ACHTUNG: Dies kann bestehende Daten überschreiben.";
    // Optional: Weiterleitung zum Login oder Dashboard
    // header('Location: login.php');
    // exit;
}

// Log-Funktion (vereinfacht)
function log_install_action($message, $is_error = false) {
    error_log(($is_error ? "INSTALL ERROR: " : "INSTALL INFO: ") . $message);
}

function execute_write_config() {
    global $error_message, $success_message, $step; // Globale Variablen für Nachrichten und Schrittsteuerung
    log_install_action("Versuche Konfigurationsdatei zu schreiben.");
    if (!isset($_SESSION['db_host'], $_SESSION['db_name'], $_SESSION['db_user'], $_SESSION['db_pass'])) {
        $error_message = "Datenbankdetails nicht in Session gefunden. Bitte starten Sie Schritt 1 erneut.";
        $_SESSION['install_step'] = 1;
        $step = 1;
        log_install_action($error_message, true);
        return false;
    }
    try {
        if (!file_exists(CONFIG_PATH)) {
            if (!is_dir(dirname(CONFIG_PATH))) {
                mkdir(dirname(CONFIG_PATH), 0755, true);
            }
            $initial_config_content = "<?php\n// =============================================================================\n// CONFIG CLASS\n// =============================================================================\nclass Config {\n    const DB_HOST \t\t\t\t= '';\n    const DB_NAME \t\t\t\t= '';\n    const DB_USER \t\t\t\t= '';\n    const DB_PASS \t\t\t\t= '';\n    \n    const PROXMOX_HOST \t\t\t= 'https://your-server:8006';\n    const PROXMOX_USER \t\t\t= '@pve';\n    const PROXMOX_PASSWORD \t\t= '';\n    \n    const ISPCONFIG_HOST \t\t= 'https://your-server:8080';\n    const ISPCONFIG_USER \t\t= '';\n    const ISPCONFIG_PASSWORD \t= '';\n    \n    const OVH_APPLICATION_KEY \t= '';\n    const OVH_APPLICATION_SECRET = '';\n    const OVH_CONSUMER_KEY \t\t= '';\n    const OVH_ENDPOINT \t\t\t= 'ovh-eu';\n}\n";
            if (file_put_contents(CONFIG_PATH, $initial_config_content) === false) {
                 throw new Exception("Konfigurationsdatei konnte nicht erstellt werden unter: " . CONFIG_PATH);
            }
            log_install_action("Minimale Konfigurationsdatei erstellt, da sie nicht vorhanden war.");
        }

        $config_content = file_get_contents(CONFIG_PATH);
        if ($config_content === false) {
            throw new Exception("Konfigurationsdatei konnte nicht gelesen werden: " . CONFIG_PATH);
        }

        $db_host_sess = $_SESSION['db_host']; // Verwende separate Variablen für Session-Werte
        $db_name_sess = $_SESSION['db_name'];
        $db_user_sess = $_SESSION['db_user'];
        $db_pass_sess = $_SESSION['db_pass'];

        $config_content = preg_replace("/(const DB_HOST\s*=\s*').*?(';)/", "$1" . addslashes($db_host_sess) . "$2", $config_content);
        $config_content = preg_replace("/(const DB_NAME\s*=\s*').*?(';)/", "$1" . addslashes($db_name_sess) . "$2", $config_content);
        $config_content = preg_replace("/(const DB_USER\s*=\s*').*?(';)/", "$1" . addslashes($db_user_sess) . "$2", $config_content);
        $config_content = preg_replace("/(const DB_PASS\s*=\s*').*?(';)/", "$1" . addslashes($db_pass_sess) . "$2", $config_content);

        if (file_put_contents(CONFIG_PATH, $config_content) === false) {
            throw new Exception("Konfigurationsdatei konnte nicht geschrieben werden. Überprüfen Sie die Dateiberechtigungen für " . CONFIG_PATH);
        }

        $success_message = ($success_message ? $success_message . "<br>" : "") . "Konfigurationsdatei erfolgreich aktualisiert.";
        log_install_action("Konfigurationsdatei erfolgreich aktualisiert.");
        $_SESSION['install_step'] = 2; // Nächster logischer Schritt ist Admin-Erstellung (intern als Schritt 2 nach DB/Konfig)
        $step = 2;
        return true;

    } catch (Exception $e) {
        $error_message = "Fehler beim Schreiben der Konfiguration: " . $e->getMessage();
        log_install_action($error_message, true);
        $_SESSION['install_step'] = 1; // Zurück zu DB-Eingabe, da Konfig fehlgeschlagen
        $step = 1;
        return false;
    }
}


// Verarbeitung der Formulareingaben
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'setup_db') {
            log_install_action("Versuche Datenbank-Setup.");
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? '';
            $db_user = $_POST['db_user'] ?? '';
            $db_pass = $_POST['db_pass'] ?? '';

            if (empty($db_name) || empty($db_user)) {
                $error_message = "Datenbankname und Benutzername sind erforderlich.";
                log_install_action($error_message, true);
            } else {
                // Verbindung testen
                try {
                    $conn = new mysqli($db_host, $db_user, $db_pass);
                    if ($conn->connect_error) {
                        throw new Exception("Verbindungsfehler: " . $conn->connect_error);
                    }
                    $success_message = "Datenbankverbindung erfolgreich hergestellt!";
                    log_install_action($success_message);

                    // Datenbank erstellen, falls nicht vorhanden
                    $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    if ($conn->error) {
                        throw new Exception("Fehler beim Erstellen der Datenbank '$db_name': " . $conn->error);
                    }
                    log_install_action("Datenbank '$db_name' sichergestellt/erstellt.");
                    $conn->select_db($db_name);
                    if ($conn->error) {
                        throw new Exception("Fehler beim Auswählen der Datenbank '$db_name': " . $conn->error);
                    }

                    $_SESSION['db_host'] = $db_host;
                    $_SESSION['db_name'] = $db_name;
                    $_SESSION['db_user'] = $db_user;
                    $_SESSION['db_pass'] = $db_pass; // In Session speichern, später für Config verwenden

                    // Nächster Schritt: Tabellen erstellen (Teil von Schritt 2 oder separater Aufruf)
                    // Für jetzt gehen wir zu Schritt 2 (Konfiguration schreiben)
                    $_SESSION['install_step'] = 2; // Vormals direkt zu Schritt 2
                    // $step = 2; // Wird unten neu gesetzt
                    // $success_message .= " Datenbank '$db_name' ausgewählt/erstellt."; // Wird unten angepasst

                    // Jetzt Tabellen erstellen
                    if (!file_exists(SQL_PATH)) {
                        throw new Exception("SQL-Datei nicht gefunden: " . SQL_PATH);
                    }
                    $sql_content = file_get_contents(SQL_PATH);
                    // Entferne Kommentare und leere Zeilen, teile in einzelne Statements
                    $sql_content = preg_replace('/(--.*)|(#.*)|(\/\*[\s\S]*?\*\/)/', '', $sql_content);
                    $sql_statements = array_filter(array_map('trim', explode(';', $sql_content)));

                    if (empty($sql_statements)) {
                        throw new Exception("Keine gültigen SQL-Anweisungen in " . SQL_PATH . " gefunden.");
                    }

                    $conn->begin_transaction();
                    foreach ($sql_statements as $statement) {
                        if (!empty($statement)) {
                            if (!$conn->query($statement)) {
                                throw new Exception("Fehler beim Ausführen von SQL: " . $conn->error . " | Statement: " . substr($statement, 0, 100) . "...");
                            }
                        }
                    }
                    $conn->commit();
                    log_install_action("Datenbanktabellen erfolgreich erstellt/aktualisiert.");
                    $success_message_db = "Datenbank '$db_name' erfolgreich eingerichtet und Tabellen importiert.";

                    // Direkt zur Konfigurationsschreibung übergehen
                    $_POST['action'] = 'write_config'; // Simuliere den nächsten Action Call
                    // $action = 'write_config'; // Setze $action direkt, um den elseif Block unten auszulösen
                                        // Dies wird nicht benötigt, da der Code ohnehin sequentiell durchlaufen wird
                                        // und der nächste Block `elseif ($action === 'write_config')` geprüft wird.
                                        // Wichtig ist, dass die Session-Variablen für DB Details gesetzt sind.

                    // Setze Step auf 2 für den Fall, dass write_config fehlschlägt, damit der Benutzer nicht zurück zu DB springt
                    // sondern bei einem Konfigurationsproblem bleibt (obwohl es keinen sichtbaren Schritt 2 mehr gibt).
                    // Logischer wäre es, bei einem Fehler in write_config ggf. auf 1 zurückzufallen oder eine generische Fehlermeldung anzuzeigen.
                    // Für jetzt: Wenn write_config erfolgreich ist, geht es zu Step 3.
                    // $step = 2; // Wird durch write_config Logik dann auf 3 gesetzt oder bleibt bei Fehler.

                    // Nach erfolgreichem DB-Setup direkt versuchen, die Konfiguration zu schreiben
                    if(empty($error_message)) { // Nur wenn DB-Setup erfolgreich war
                        $success_message = $success_message_db; // Behalte die DB Erfolgsmeldung
                        if (!execute_write_config()) {
                            // Fehler beim Schreiben der Konfig wurde in der Funktion behandelt (error_message, step gesetzt)
                        } else {
                             // $success_message wurde in execute_write_config erweitert.
                             // $step wurde in execute_write_config auf 2 gesetzt (für Admin-Formular).
                        }
                    }

                } catch (Exception $e) {
                    if (isset($conn) && $conn->ping()) { // Ping prüft, ob die Verbindung noch aktiv ist
                         $conn->rollback(); // Nur rollbacken, wenn Transaktion gestartet wurde und Verbindung besteht
                    }
                    $error_message = "Datenbank-Setup Fehler: " . $e->getMessage();
                    log_install_action($error_message, true);
                    // Bleibe bei Schritt 1, wenn ein Fehler auftritt
                    $_SESSION['install_step'] = 1;
                    $step = 1;
                }
                if (isset($conn) && !$conn->connect_error) {
                    $conn->close();
                }
            } // <--- Schließt den else-Block von if(empty($db_name) ...)
        } // <--- HIER DIE FEHLENDE KLAMMER für if ($action === 'setup_db')
        // Der 'write_config' Action-Block ist nicht mehr hier, da er von setup_db aufgerufen wird (Funktion execute_write_config).
        elseif ($action === 'create_admin') {
            // Stelle sicher, dass die Konfiguration geschrieben wurde, bevor Admin erstellt wird
            // Dies sollte durch die Schrittsteuerung gewährleistet sein, aber eine zusätzliche Prüfung schadet nicht.
            if (!is_configured()) {
                 $error_message = "Die Konfigurationsdatei ist noch nicht geschrieben. Bitte führen Sie zuerst Schritt 1 aus.";
                 log_install_action($error_message, true);
                 $_SESSION['install_step'] = 1;
                 $step = 1;
            } else {
                log_install_action("Versuche Admin-Benutzer zu erstellen.");
                $admin_user = $_POST['admin_user'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            $admin_pass = $_POST['admin_pass'] ?? '';
            $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

            if (empty($admin_user) || empty($admin_email) || empty($admin_pass)) {
                $error_message = "Alle Felder für den Admin-Benutzer sind erforderlich.";
                log_install_action($error_message, true);
                $step = 3; // Bleibe bei Schritt 3
            } elseif ($admin_pass !== $admin_pass_confirm) {
                $error_message = "Die Passwörter stimmen nicht überein.";
                log_install_action($error_message, true);
                $step = 3;
            } elseif (strlen($admin_pass) < 6) {
                $error_message = "Das Passwort muss mindestens 6 Zeichen lang sein.";
                log_install_action($error_message, true);
                $step = 3;
            } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Ungültige E-Mail-Adresse.";
                log_install_action($error_message, true);
                $step = 3;
            }else {
                try {
                    // Datenbankverbindung herstellen (Konfiguration sollte jetzt geschrieben sein)
                    require_once __DIR__ . '/framework.php'; // Lädt Config und Database Klasse

                    if (!class_exists('Database')) {
                        throw new Exception("Database Klasse nicht gefunden. Stellen Sie sicher, dass framework.php korrekt eingebunden ist.");
                    }
                     if (!class_exists('AuthenticationHandler')) {
                        require_once __DIR__ . '/auth_handler.php';
                        if (!class_exists('AuthenticationHandler')) {
                             throw new Exception("AuthenticationHandler Klasse nicht gefunden.");
                        }
                    }


                    $db_conn = Database::getInstance()->getConnection(); // Stellt sicher, dass config.inc.php geladen wird

                    // Prüfen ob Benutzer bereits existiert (sollte nicht der Fall sein bei Erstinstallation)
                    $stmt = $db_conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->bind_param("ss", $admin_user, $admin_email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        throw new Exception("Ein Benutzer mit diesem Benutzernamen oder E-Mail existiert bereits.");
                    }
                    $stmt->close();

                    // AuthenticationHandler verwenden, um Benutzer zu erstellen
                    $authHandler = new AuthenticationHandler();
                    // Das "Full Name" Feld ist hier nicht vorhanden, kann aber optional hinzugefügt werden.
                    // Für createUser wird ein Standardwert oder der Benutzername verwendet, falls erforderlich.
                    $creation_result = $authHandler->createUser($admin_user, $admin_email, $admin_pass, $admin_user, 'admin');

                    if (!$creation_result['success']) {
                        throw new Exception($creation_result['message']);
                    }

                    $success_message = "Admin-Benutzer '$admin_user' erfolgreich erstellt.";
                    log_install_action($success_message);
                    $_SESSION['install_step'] = 4;
                    $step = 4;

                } catch (Exception $e) {
                    $error_message = "Fehler beim Erstellen des Admin-Benutzers: " . $e->getMessage();
                    // Versuchen, die DB-Verbindung zu loggen, falls sie das Problem ist
                    if (class_exists('Config')) {
                        log_install_action("DB Config: Host=" . Config::DB_HOST . ", Name=" . Config::DB_NAME . ", User=" . Config::DB_USER, true);
                    }
                    log_install_action($error_message, true);
                    $step = 2; // Bleibe bei sichtbarem Schritt 2 (Admin-Formular)
                    $_SESSION['install_step'] = 2;
                }
            }
            // $_SESSION['install_step'] = $step; // Diese Zeile wird nicht mehr benötigt, da oben explizit gesetzt.
            }
            } elseif ($action === 'delete_installer') {
            log_install_action("Versuche Installationsdatei zu löschen.");
            if (unlink(__FILE__)) {
                $success_message = "Installationsdatei erfolgreich gelöscht. Sie werden weitergeleitet...";
                log_install_action($success_message);
                // Weiterleitung nach kurzer Verzögerung, damit die Nachricht gelesen werden kann.
                header("Refresh:3; url=login.php");
                exit;
            } else {
                $error_message = "Fehler beim Löschen der Installationsdatei. Bitte löschen Sie install.php manuell.";
                log_install_action($error_message, true);
                $step = 4; // Bleibe bei Schritt 4, um die Nachricht anzuzeigen
                $_SESSION['install_step'] = $step;
            }
        } elseif ($action === 'rename_installer') {
            log_install_action("Versuche Installationsdatei umzubenennen.");
            $new_name = __FILE__ . '.installed';
            if (rename(__FILE__, $new_name)) {
                $success_message = "Installationsdatei erfolgreich zu install.php.installed umbenannt. Sie werden weitergeleitet...";
                log_install_action($success_message);
                header("Refresh:3; url=login.php");
                exit;
            } else {
                $error_message = "Fehler beim Umbenennen der Installationsdatei. Bitte benennen Sie install.php manuell um oder löschen Sie sie.";
                log_install_action($error_message, true);
                $step = 4; // Bleibe bei Schritt 4
                $_SESSION['install_step'] = $step;
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Framework Installation</title>
    <!-- <link rel="stylesheet" type="text/css" href="assets/main.css"> --><!-- Entfernt, da install.css spezifischer ist -->
    <link rel="stylesheet" type="text/css" href="assets/install.css">
    <!-- <style>
        /* Zusätzliche Stile für das Installationsformular, können in install.css verschoben werden */
        /* Inline-Stile wurden in install.css verschoben oder sind dort bereits abgedeckt */
        .form-group label { display: block; margin-bottom: .5rem; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="password"] { width: 100%; padding: .75rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: .75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .hidden { display: none; }
        .steps { margin-bottom: 2rem; display: flex; justify-content: space-around; }
        .step { padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 4px; }
        .step.active { background-color: #007bff; color: white; border-color: #007bff; }
        .progress-bar { width: 100%; background-color: #e9ecef; border-radius: .25rem; margin-bottom: 1rem; }
        .progress-bar-inner { height: 20px; width: 0%; background-color: #007bff; border-radius: .25rem; text-align: center; color: white; line-height: 20px; } */
    /* </style> */
</head>
<body>
    <div class="container">
        <h1>Framework Installation</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1. Datenbank & Konfig.</div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2. Admin-Konto</div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3. Abschluss</div>
        </div>

        <div class="progress-bar">
            <?php
                // Anpassung der Progress-Bar-Logik an 3 sichtbare Schritte
                $display_step = $step;
                if ($step === 1) $progress_percentage = 0; // Am Anfang von Schritt 1
                elseif ($step === 2) $progress_percentage = 33; // Am Anfang von Schritt 2 (Admin)
                elseif ($step === 3) $progress_percentage = 66; // Am Anfang von Schritt 3 (Abschluss)
                elseif ($step === 4) { // $step wird intern auf 4 gesetzt nach Admin-Erstellung, aber wir zeigen es als Ende von Schritt 3 an
                    $progress_percentage = 100;
                    $display_step = 3; // Zeige "Schritt 3 von 3" an, wenn die Installation komplett ist
                } else {
                    $progress_percentage = 0;
                }
                // Korrigiere $display_step für die Textausgabe, wenn $step = 4 (interner Abschluss vor Anzeige)
                // Die Variable $step in der PHP Logik geht bis 4 (intern für "fertig")
                // Die sichtbaren Schritte sind aber nur 3.
                // $session_step_for_display ist der aktuelle logische Schritt, den der Benutzer sieht.
                $session_step_for_display = $_SESSION['install_step'] ?? 1;
                if ($session_step_for_display > 3) $session_step_for_display = 3;


            ?>
            <div class="progress-bar-inner" style="width: <?php echo $progress_percentage; ?>%;">
                Schritt <?php echo $session_step_for_display; ?> von 3
            </div>
        </div>

        <?php if (!is_configured() || isset($_GET['force_install'])): ?>
            <!-- Schritt 1: Datenbankdetails -->
            <form id="dbForm" method="POST" action="install.php" class="<?php echo ($_SESSION['install_step'] ?? 1) == 1 ? '' : 'hidden'; ?>">
                <input type="hidden" name="action" value="setup_db">
                <h2>Schritt 1: Datenbank & Konfiguration</h2>
                <p>Bitte geben Sie die Zugangsdaten für Ihre MySQL-Datenbank ein. Die Datenbank wird erstellt/importiert und die Konfigurationsdatei <code>config/config.inc.php</code> wird geschrieben.</p>
                <div class="form-group">
                    <label for="db_host">Datenbank-Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? $_SESSION['db_host'] ?? 'localhost'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Datenbankname</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? $_SESSION['db_name'] ?? 'server_management'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Benutzername</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? $_SESSION['db_user'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Passwort</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                <button type="submit" class="btn btn-primary">Datenbank einrichten & Admin-Konto erstellen</button>
            </form>

            <!-- Schritt 2 (ehemals 3): Admin-Konto Erstellung -->
            <form id="adminForm" method="POST" action="install.php" class="<?php echo ($_SESSION['install_step'] ?? 1) == 2 ? '' : 'hidden'; ?>">
                 <input type="hidden" name="action" value="create_admin">
                 <h2>Schritt 2: Administrator-Konto erstellen</h2>
                 <p>Erstellen Sie das erste Administrator-Konto für das System.</p>
                 <div class="form-group">
                    <label for="admin_user">Admin-Benutzername</label>
                    <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required>
                 </div>
                 <div class="form-group">
                    <label for="admin_email">Admin-E-Mail</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                 </div>
                 <div class="form-group">
                    <label for="admin_pass">Passwort (min. 6 Zeichen)</label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                 </div>
                 <div class="form-group">
                    <label for="admin_pass_confirm">Passwort bestätigen</label>
                    <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required>
                 </div>
                 <button type="submit" class="btn btn-primary">Admin-Konto erstellen & Weiter</button>
                 <button type="button" onclick="showStep(1);" class="btn btn-secondary">Zurück zu Datenbank</button> <!-- Zurück zu Schritt 1 -->
            </form>

            <!-- Schritt 3 (ehemals 4): Abschluss -->
            <div id="summary" class="<?php echo ($_SESSION['install_step'] ?? 1) >= 3 ? '' : 'hidden'; ?>">
                <h2>Schritt 3: Installation abgeschlossen! 🎉</h2>
                <?php if (empty($error_message)): // Nur anzeigen, wenn kein Fehler in diesem Schritt passiert ist ?>
                    <p>Herzlichen Glückwunsch! Das Framework wurde erfolgreich installiert und konfiguriert.</p>
                    <p><strong>Wichtige nächste Schritte:</strong></p>
                    <ul>
                        <li>Aus Sicherheitsgründen sollten Sie diese Datei (<code>install.php</code>) jetzt von Ihrem Server <strong>löschen oder umbenennen</strong>.</li>
                        <li>Sie können sich nun mit dem erstellten Administrator-Konto anmelden.</li>
                    </ul>
                    <a href="login.php" class="btn btn-success">Zur Login-Seite</a>
                    <br><br>
                    <form method="POST" action="install.php" style="display: inline-block;">
                        <input type="hidden" name="action" value="delete_installer">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie die Installationsdatei löschen möchten? Sie müssen dies manuell tun, falls es fehlschlägt.');">Installationsdatei jetzt löschen</button>
                    </form>
                     <form method="POST" action="install.php" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="action" value="rename_installer">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Sind Sie sicher, dass Sie die Installationsdatei umbenennen möchten? Sie wird zu install.php.installed');">Installationsdatei umbenennen</button>
                    </form>
                <?php else: ?>
                    <p>Es gab einen Fehler im letzten Schritt. Bitte überprüfen Sie die Meldungen.</p>
                <?php endif; ?>
            </div>

        <?php endif; // Ende der if-Bedingung für Neuinstallation ?>

    </div>

    <script>
        // Einfaches Script für die Schrittanzeige
        function showStep(sessionStepValue) {
            document.getElementById('dbForm').classList.add('hidden');
            document.getElementById('adminForm').classList.add('hidden');
            document.getElementById('summary').classList.add('hidden');

            let currentFormId = '';
            let visibleStepForDisplay = sessionStepValue; // Dies wird der Text in der Progressbar sein (1, 2, oder 3)

            if (sessionStepValue === 1) {
                currentFormId = 'dbForm';
            } else if (sessionStepValue === 2) {
                currentFormId = 'adminForm';
            } else if (sessionStepValue >= 3) { // sessionStepValue kann 3 oder 4 (intern für "complete") sein
                currentFormId = 'summary';
                visibleStepForDisplay = 3; // Für Text und .active-Klassen immer max. 3 anzeigen
            }

            if (currentFormId && document.getElementById(currentFormId)) {
                document.getElementById(currentFormId).classList.remove('hidden');
            }

            // Update .step.active classes
            document.querySelectorAll('.steps .step').forEach((el, index) => {
                if ((index + 1) <= visibleStepForDisplay) {
                    el.classList.add('active');
                } else {
                    el.classList.remove('active');
                }
            });

            // Update progress bar
            const progressBar = document.querySelector('.progress-bar-inner');
            let progressPercentage = 0;
            // Die PHP Variable $step hält den *aktuellen Zustand* der PHP Logik.
            // $_SESSION['install_step'] hält den *nächsten anzuzeigenden Schritt* oder den *finalen Zustand*.
            const internalPhpLogicStep = <?php echo $step; ?>; // Dies ist der $step aus der PHP Verarbeitung auf dem Server

            if (internalPhpLogicStep === 1) progressPercentage = 0;    // DB Formular wird angezeigt
            else if (internalPhpLogicStep === 2) progressPercentage = 33; // Admin Formular wird angezeigt
            else if (internalPhpLogicStep === 3) progressPercentage = 66; // Abschlussseite wird angezeigt (Admin war erfolgreich, $step wurde zu 4, aber Session war noch 3)
                                                                         // oder Admin schlug fehl, $step ist 3 (intern), Session ist 2.
                                                                         // Diese Logik muss präziser sein.

            // Logik für Progress-Bar:
            // sessionStepValue ist der Schritt, der dem Benutzer angezeigt werden soll.
            // internalPhpLogicStep ist der tatsächliche Verarbeitungsstand auf dem Server.
            if (internalPhpLogicStep === 4) { // Alles abgeschlossen
                progressPercentage = 100;
                visibleStepForDisplay = 3; // Zeige "Schritt 3 von 3"
            } else if (sessionStepValue === 1) {
                progressPercentage = 0;
            } else if (sessionStepValue === 2) {
                progressPercentage = 33;
            } else if (sessionStepValue === 3) { // Wird angezeigt, wenn Admin-Erstellung erfolgreich war, bevor PHP $step auf 4 setzt für den nächsten Reload
                progressPercentage = 66;
            } else { // Fallback
                progressPercentage = 0;
            }

            progressBar.style.width = `${progressPercentage}%`;
            progressBar.textContent = `Schritt ${visibleStepForDisplay} von 3`; // visibleStepForDisplay wurde oben korrekt gesetzt
        }

        // Aktuellen Schritt basierend auf PHP-Session-Variable anzeigen
        const sessionInstallStep = <?php echo ($_SESSION['install_step'] ?? 1); ?>;
        showStep(sessionInstallStep);

        // Hier könnte AJAX für die Formularübermittlung implementiert werden,
        // um die Seite nicht bei jedem Schritt neu laden zu müssen.
        // Für dieses Beispiel wird ein serverseitiger Ansatz mit Neuladen verwendet.
    </script>
</body>
</html>

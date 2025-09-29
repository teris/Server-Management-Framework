<?php
require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class ContentModule extends ModuleBase {

    public function init() {
        // Optional: Initialisierung
    }

    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="alert alert-danger">' . $this->t('access_denied', 'Zugriff verweigert') . '</div>';
        }

        // Prüfen ob Installation erforderlich ist
        $needsInstallation = $this->checkInstallationStatus();
        
        if ($needsInstallation) {
            return $this->render('install', []);
        }

        return $this->render('main', []);
    }

    private function checkInstallationStatus() {
        try {
            $db = DatabaseManager::getInstance()->getDriver();
            // DB-agnostische Prüfung: versuche auf Tabelle zuzugreifen
            $stmt = $db->prepare("SELECT 1 FROM cms_pages LIMIT 1");
            $db->execute($stmt, []);
            return false; // Tabelle existiert
        } catch (Exception $e) {
            return true; // Tabelle existiert nicht oder Fehler → Installation nötig
        }
    }

    public function needsInstallation() {
        return $this->checkInstallationStatus();
    }

    public function handleAjaxRequest($action, $data) {
        try {
            switch ($action) {
                case 'install_database':
                    return $this->installDatabase();
                case 'list_pages':
                    return $this->listPages();
                case 'save_page':
                    return $this->savePage($data);
                case 'delete_page':
                    return $this->deletePage($data);
                case 'get_page_content':
                    return $this->getPageContent($data);
                default:
                    return [ 'success' => false, 'error' => 'Unknown action' ];
            }
        } catch (Exception $e) {
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }

    private function installDatabase() {
        try {
            $db = DatabaseManager::getInstance()->getDriver();
            
            // Tabelle für CMS-Seiten erstellen
            $db->query("CREATE TABLE IF NOT EXISTS cms_pages (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                slug VARCHAR(190) UNIQUE,
                title VARCHAR(255),
                content MEDIUMTEXT,
                status VARCHAR(32) DEFAULT 'published',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            // Beispiel-Seiten einfügen
            $db->query("INSERT IGNORE INTO cms_pages (slug, title, content, status) VALUES 
                ('impressum', 'Impressum', '<h1>Impressum</h1><p>Hier steht Ihr Impressum.</p>', 'published'),
                ('agb', 'AGB', '<h1>Allgemeine Geschäftsbedingungen</h1><p>Hier stehen Ihre AGB.</p>', 'published'),
                ('datenschutz', 'Datenschutz', '<h1>Datenschutzerklärung</h1><p>Hier steht Ihre Datenschutzerklärung.</p>', 'published')
            ");
            
            // Frontend-Datei erstellen
            $this->createFrontendPage();
            
            return [ 'success' => true, 'message' => 'Datenbank und Frontend-Datei erfolgreich installiert' ];
        } catch (Exception $e) {
            return [ 'success' => false, 'error' => 'Installation fehlgeschlagen: ' . $e->getMessage() ];
        }
    }

    private function createFrontendPage() {
        $pageContent = '<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Dynamische Seiten - Frontend
 * Zeigt CMS-Seiten mit gleichem Design wie die Hauptseite
 */

require_once \'../src/sys.conf.php\';
require_once \'../framework.php\';
require_once \'../src/core/LanguageManager.php\';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

$slug = isset($_GET[\'slug\']) ? preg_replace(\'/[^a-z0-9\\-]/i\',\'\', $_GET[\'slug\']) : \'\';
if ($slug === \'\') {
    http_response_code(404);
    echo \'Seite nicht gefunden\';
    exit;
}

try {
    $db = DatabaseManager::getInstance()->getDriver();
    $stmt = $db->prepare("SELECT title, content, status FROM cms_pages WHERE slug = ? LIMIT 1");
    $db->execute($stmt, [ $slug ]);
    $page = $db->fetch($stmt);
} catch (Exception $e) {
    $page = null;
}

if (!$page || ($page[\'status\'] ?? \'\') !== \'published\') {
    http_response_code(404);
    echo \'Seite nicht gefunden\';
    exit;
}

// ServiceManager für Navigation (optional)
$serviceManager = new ServiceManager();

// Hilfsfunktion für sichere Array-Anzeige
function safeDisplay($value, $default = \'N/A\') {
    if (is_array($value)) {
        if (isset($value[\'1min\'])) {
            return htmlspecialchars($value[\'1min\']);
        } elseif (isset($value[\'total\'])) {
            return htmlspecialchars($value[\'total\']);
        } elseif (isset($value[\'used\'])) {
            return htmlspecialchars($value[\'used\']);
        } else {
            return $default;
        }
    }
    return htmlspecialchars($value ?? $default);
}

// Server-Status abrufen (für Navigation)
try {
    $proxmoxVMs = $serviceManager->getProxmoxVMs();
    $proxmoxLXCs = $serviceManager->getProxmoxLXCs();
    $gameServers = $serviceManager->getOGPGameServers();
    $systemInfo = $serviceManager->getSystemInfo();
} catch (Exception $e) {
    $proxmoxVMs = [];
    $proxmoxLXCs = [];
    $gameServers = [];
    $systemInfo = [];
    error_log("Frontpanel Error: " . $e->getMessage());
}

// Übersetzungsfunktion wird von sys.conf.php bereitgestellt
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page[\'title\']) ?> - <?= Config::FRONTPANEL_SITE_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> <?= Config::FRONTPANEL_SITE_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#server-status"><?= t(\'server_status\') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#game-servers"><?= t(\'game_servers\') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#support"><?= t(\'support\') ?></a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-person-circle"></i> <?= t(\'customer_login\') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus"></i> <?= t(\'register\') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="bg-light py-2">
        <div class="container">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="index.php">
                        <i class="bi bi-house"></i> <?= t(\'home\') ?>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= htmlspecialchars($page[\'title\']) ?>
                </li>
            </ol>
        </div>
    </nav>

    <!-- Hauptinhalt -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <article class="card">
                    <div class="card-header">
                        <h1 class="card-title mb-0"><?= htmlspecialchars($page[\'title\']) ?></h1>
                    </div>
                    <div class="card-body">
                        <div class="content">
                            <?= $page[\'content\'] ?>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        <small>
                            <i class="bi bi-clock"></i> 
                            Zuletzt aktualisiert: <?= date(\'d.m.Y H:i\') ?>
                        </small>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; <?= date(\'Y\') ?> <?= Config::FRONTPANEL_SITE_NAME ?>. <?= t(\'all_rights_reserved\') ?></p>
            <div class="mt-2">
                <a href="index.php" class="text-white-50 me-3">
                    <i class="bi bi-house"></i> <?= t(\'home\') ?>
                </a>
                <a href="index.php#server-status" class="text-white-50 me-3">
                    <i class="bi bi-activity"></i> <?= t(\'server_status\') ?>
                </a>
                <a href="index.php#support" class="text-white-50 me-3">
                    <i class="bi bi-headset"></i> <?= t(\'support\') ?>
                </a>
                <a href="shop.php" class="text-white-50 me-3">
                    <i class="bi bi-cart"></i> Shop
                </a>
                <a href="../src/index.php" class="text-white-50">
                    <i class="bi bi-shield-lock"></i> <?= t(\'admin_panel\') ?>
                </a>
            </div>
        </div>
    </footer>

    <!-- Toast Container für Benachrichtigungen -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <!-- Toasts werden hier dynamisch eingefügt -->
    </div>
    
    <script src="assets/frontpanel.js"></script>
</body>
</html>';

        // Zielpfad korrekt relativ zu src/module/content/ → Projektwurzel/public
        $filePath = __DIR__ . '/../../../public/page.php';
        $dirPath = dirname($filePath);

        // Ordner sicherstellen
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0775, true);
        }

        // Bestehende Datei nach <Dateiname>.<Erweiterung>.bnk sichern (ohne Nachfrage überschreiben)
        if (file_exists($filePath)) {
            $backupPath = $filePath . '.bnk';
            @copy($filePath, $backupPath);
        }

        // Datei schreiben
        file_put_contents($filePath, $pageContent);
    }

    private function getPageContent($data) {
        $errors = $this->validate($data, [ 'slug' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->prepare("SELECT * FROM cms_pages WHERE slug = ? LIMIT 1");
        $db->execute($stmt, [ $data['slug'] ]);
        $page = $db->fetch($stmt);
        
        if (!$page) {
            return [ 'success' => false, 'error' => 'Seite nicht gefunden' ];
        }
        
        return [ 'success' => true, 'data' => $page ];
    }

    private function listPages() {
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->query("SELECT id, slug, title, status, created_at, updated_at FROM cms_pages ORDER BY updated_at DESC");
        $rows = $db->fetchAll($stmt);
        return [ 'success' => true, 'data' => $rows ];
    }

    private function savePage($data) {
        $errors = $this->validate($data, [
            'slug' => 'required|min:1',
            'title' => 'required|min:1',
        ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        $existsStmt = $db->prepare("SELECT id FROM cms_pages WHERE slug = ?");
        $db->execute($existsStmt, [ $data['slug'] ]);
        $exists = $db->fetch($existsStmt);
        if ($exists) {
            $stmt = $db->prepare("UPDATE cms_pages SET title = ?, content = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?");
            $db->execute($stmt, [ $data['title'], $data['content'] ?? '', $data['status'] ?? 'published', $data['slug'] ]);
        } else {
            $stmt = $db->prepare("INSERT INTO cms_pages (slug, title, content, status) VALUES (?, ?, ?, ?)");
            $db->execute($stmt, [ $data['slug'], $data['title'], $data['content'] ?? '', $data['status'] ?? 'published' ]);
        }
        return [ 'success' => true ];
    }

    private function deletePage($data) {
        $errors = $this->validate($data, [ 'slug' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->prepare("DELETE FROM cms_pages WHERE slug = ?");
        $db->execute($stmt, [ $data['slug'] ]);
        return [ 'success' => true ];
    }
}



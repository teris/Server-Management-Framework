<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Datenschutzseite
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Session starten
session_start();

$isLoggedIn = isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
$customerName = $_SESSION['customer_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('privacy_policy') ?> - Server Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    <link rel="stylesheet" type="text/css" href="assets/login.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="dashboard-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> Server Management
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($isLoggedIn): ?>
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> <?= t('dashboard') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person-circle"></i> <?= t('profile') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="support.php">
                                <i class="bi bi-headset"></i> <?= t('support_tickets') ?>
                            </a>
                        </li>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customerName) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person"></i> <?= t('profile') ?>
                                </a></li>
                                <li><a class="dropdown-item" href="change-password.php">
                                    <i class="bi bi-key"></i> <?= t('change_password') ?>
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="dashboard.php?logout=1">
                                    <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="bi bi-person-plus"></i> <?= t('register') ?>
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Privacy Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h1 class="card-title mb-0">
                            <i class="bi bi-shield-check text-primary"></i> 
                            <?= t('privacy_policy') ?>
                        </h1>
                        <p class="text-muted mb-0 mt-2">
                            <small>Zuletzt aktualisiert: <?= date('d.m.Y') ?></small>
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Wichtig:</strong> Diese Datenschutzerklärung informiert Sie über die Erhebung, Verarbeitung und Nutzung Ihrer personenbezogenen Daten durch Server Management.
                        </div>

                        <h2>1. Verantwortlicher</h2>
                        <p>
                            <strong>Server Management GmbH</strong><br>
                            Musterstraße 123<br>
                            12345 Musterstadt<br>
                            Deutschland<br><br>
                            
                            <strong>Kontakt:</strong><br>
                            E-Mail: datenschutz@servermanagement.de<br>
                            Telefon: +49 123 456789
                        </p>

                        <h2>2. Erhebung und Verarbeitung personenbezogener Daten</h2>
                        <h3>2.1 Automatisch erfasste Daten</h3>
                        <p>Bei Ihrem Besuch auf unserer Website werden automatisch folgende Informationen erfasst:</p>
                        <ul>
                            <li>IP-Adresse</li>
                            <li>Browsertyp und -version</li>
                            <li>Betriebssystem</li>
                            <li>Zugriffszeitpunkt</li>
                            <li>Besuchte Seiten</li>
                            <li>Referrer-URL</li>
                        </ul>

                        <h3>2.2 Daten bei Registrierung und Nutzung</h3>
                        <p>Bei der Registrierung und Nutzung unserer Dienste erheben wir:</p>
                        <ul>
                            <li>Vollständiger Name</li>
                            <li>E-Mail-Adresse</li>
                            <li>Telefonnummer (optional)</li>
                            <li>Firmenname (optional)</li>
                            <li>Adressdaten (optional)</li>
                            <li>Passwort (verschlüsselt gespeichert)</li>
                        </ul>

                        <h2>3. Zweck der Datenverarbeitung</h2>
                        <p>Wir verarbeiten Ihre personenbezogenen Daten für folgende Zwecke:</p>
                        <ul>
                            <li>Bereitstellung unserer Dienste</li>
                            <li>Kundenbetreuung und Support</li>
                            <li>Rechnungsstellung und Zahlungsabwicklung</li>
                            <li>Kommunikation mit Ihnen</li>
                            <li>Verbesserung unserer Dienste</li>
                            <li>Erfüllung gesetzlicher Verpflichtungen</li>
                        </ul>

                        <h2>4. Rechtsgrundlagen</h2>
                        <p>Die Verarbeitung Ihrer Daten erfolgt auf folgenden Rechtsgrundlagen:</p>
                        <ul>
                            <li><strong>Vertragserfüllung:</strong> Für die Bereitstellung unserer Dienste</li>
                            <li><strong>Berechtigtes Interesse:</strong> Für die Verbesserung unserer Dienste und Sicherheit</li>
                            <li><strong>Einwilligung:</strong> Für Marketing-Kommunikation (wenn erteilt)</li>
                            <li><strong>Gesetzliche Verpflichtung:</strong> Für Buchhaltung und Steuerzwecke</li>
                        </ul>

                        <h2>5. Datenweitergabe</h2>
                        <p>Wir geben Ihre personenbezogenen Daten nur in folgenden Fällen weiter:</p>
                        <ul>
                            <li>An Dienstleister, die uns bei der Bereitstellung unserer Dienste unterstützen</li>
                            <li>An Behörden, wenn gesetzlich vorgeschrieben</li>
                            <li>An Dritte nur mit Ihrer ausdrücklichen Einwilligung</li>
                        </ul>

                        <h2>6. Datensicherheit</h2>
                        <p>Wir setzen technische und organisatorische Sicherheitsmaßnahmen ein, um Ihre Daten zu schützen:</p>
                        <ul>
                            <li>Verschlüsselte Datenübertragung (HTTPS/TLS)</li>
                            <li>Verschlüsselte Datenspeicherung</li>
                            <li>Regelmäßige Sicherheitsupdates</li>
                            <li>Zugriffskontrollen und -protokollierung</li>
                            <li>Regelmäßige Backups</li>
                        </ul>

                        <h2>7. Cookies</h2>
                        <p>Wir verwenden Cookies für folgende Zwecke:</p>
                        <ul>
                            <li><strong>Notwendige Cookies:</strong> Für die Grundfunktionen der Website</li>
                            <li><strong>Session-Cookies:</strong> Für die Anmeldung und Session-Verwaltung</li>
                            <li><strong>Analyse-Cookies:</strong> Für die Verbesserung unserer Dienste (nur mit Einwilligung)</li>
                        </ul>

                        <h2>8. Ihre Rechte</h2>
                        <p>Sie haben folgende Rechte bezüglich Ihrer personenbezogenen Daten:</p>
                        <ul>
                            <li><strong>Auskunftsrecht:</strong> Sie können Auskunft über Ihre gespeicherten Daten verlangen</li>
                            <li><strong>Berichtigungsrecht:</strong> Sie können falsche Daten berichtigen lassen</li>
                            <li><strong>Löschungsrecht:</strong> Sie können die Löschung Ihrer Daten verlangen</li>
                            <li><strong>Einschränkungsrecht:</strong> Sie können die Verarbeitung einschränken lassen</li>
                            <li><strong>Datenübertragbarkeit:</strong> Sie können Ihre Daten in einem strukturierten Format erhalten</li>
                            <li><strong>Widerspruchsrecht:</strong> Sie können der Verarbeitung widersprechen</li>
                        </ul>

                        <h2>9. Speicherdauer</h2>
                        <p>Wir speichern Ihre Daten nur so lange wie notwendig:</p>
                        <ul>
                            <li><strong>Kundendaten:</strong> Bis zur Kündigung des Vertrags + 10 Jahre (gesetzliche Aufbewahrungspflicht)</li>
                            <li><strong>Log-Daten:</strong> 90 Tage</li>
                            <li><strong>Support-Tickets:</strong> 5 Jahre nach Abschluss</li>
                            <li><strong>Kontaktanfragen:</strong> 2 Jahre</li>
                        </ul>

                        <h2>10. Kontakt zum Datenschutz</h2>
                        <p>Bei Fragen zum Datenschutz können Sie sich an uns wenden:</p>
                        <p>
                            <strong>Datenschutzbeauftragter:</strong><br>
                            Server Management GmbH<br>
                            Datenschutz<br>
                            Musterstraße 123<br>
                            12345 Musterstadt<br>
                            Deutschland<br><br>
                            
                            E-Mail: datenschutz@servermanagement.de<br>
                            Telefon: +49 123 456789
                        </p>

                        <h2>11. Beschwerderecht</h2>
                        <p>Sie haben das Recht, sich bei einer Aufsichtsbehörde zu beschweren, wenn Sie der Ansicht sind, dass die Verarbeitung Ihrer personenbezogenen Daten rechtswidrig erfolgt.</p>

                        <h2>12. Änderungen der Datenschutzerklärung</h2>
                        <p>Wir behalten uns vor, diese Datenschutzerklärung bei Bedarf zu aktualisieren. Änderungen werden auf dieser Seite veröffentlicht und sind ab dem Datum der Veröffentlichung wirksam.</p>

                        <div class="alert alert-warning mt-4">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Hinweis:</strong> Diese Datenschutzerklärung ist eine allgemeine Vorlage und sollte an die spezifischen Bedürfnisse und rechtlichen Anforderungen Ihres Unternehmens angepasst werden.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= Config::FRONTPANEL_SITE_NAME ?>. <?= t('all_rights_reserved') ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="privacy.php" class="text-decoration-none me-3"><?= t('privacy_policy') ?></a>
                    <a href="terms.php" class="text-decoration-none me-3"><?= t('terms_of_service') ?></a>
                    <a href="contact.php" class="text-decoration-none"><?= t('contact') ?></a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

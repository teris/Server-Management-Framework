<?php
/**
 * AGB-Seite
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
    <title><?= t('terms_of_service') ?> - Server Management</title>
    
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

    <!-- Terms Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h1 class="card-title mb-0">
                            <i class="bi bi-file-text text-primary"></i> 
                            <?= t('terms_of_service') ?>
                        </h1>
                        <p class="text-muted mb-0 mt-2">
                            <small>Zuletzt aktualisiert: <?= date('d.m.Y') ?></small>
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Wichtig:</strong> Diese Allgemeinen Geschäftsbedingungen regeln die Nutzung unserer Server Management Dienste.
                        </div>

                        <h2>§1 Geltungsbereich</h2>
                        <p>Diese Allgemeinen Geschäftsbedingungen (AGB) gelten für alle Verträge zwischen der Server Management GmbH (nachfolgend "Anbieter") und den Kunden (nachfolgend "Kunde") über die Nutzung der Server Management Dienste.</p>

                        <h2>§2 Vertragsschluss</h2>
                        <p>Der Vertrag kommt durch die Registrierung des Kunden auf der Website des Anbieters und die Bestätigung der Registrierung zustande. Der Kunde bestätigt mit der Registrierung, dass er diese AGB gelesen und akzeptiert hat.</p>

                        <h2>§3 Leistungsbeschreibung</h2>
                        <p>Der Anbieter stellt folgende Dienste zur Verfügung:</p>
                        <ul>
                            <li>Server-Management-Plattform</li>
                            <li>Virtual Machine Management</li>
                            <li>Website-Hosting</li>
                            <li>E-Mail-Hosting</li>
                            <li>DNS-Management</li>
                            <li>Support-System</li>
                            <li>Monitoring und Backup-Dienste</li>
                        </ul>

                        <h2>§4 Verfügbarkeit</h2>
                        <p>Der Anbieter bemüht sich um eine Verfügbarkeit von 99,9% der Dienste. Geplante Wartungsarbeiten werden mindestens 24 Stunden vorher angekündigt. Der Anbieter haftet nicht für Ausfälle, die durch höhere Gewalt oder technische Probleme Dritter verursacht werden.</p>

                        <h2>§5 Pflichten des Kunden</h2>
                        <p>Der Kunde verpflichtet sich:</p>
                        <ul>
                            <li>Die Dienste nur für rechtmäßige Zwecke zu nutzen</li>
                            <li>Keine illegalen Inhalte zu verbreiten</li>
                            <li>Die Sicherheit seiner Zugangsdaten zu gewährleisten</li>
                            <li>Den Anbieter über Sicherheitsvorfälle zu informieren</li>
                            <li>Die geltenden Gesetze und Vorschriften einzuhalten</li>
                            <li>Backups seiner Daten zu erstellen</li>
                        </ul>

                        <h2>§6 Verbotene Nutzung</h2>
                        <p>Folgende Nutzungen sind untersagt:</p>
                        <ul>
                            <li>Verbreitung von Malware oder Viren</li>
                            <li>Spam-Versand oder andere unerwünschte Massenkommunikation</li>
                            <li>DDoS-Angriffe oder andere Angriffe auf Systeme</li>
                            <li>Verbreitung von illegalen oder schädlichen Inhalten</li>
                            <li>Verletzung von Urheberrechten oder anderen Rechten Dritter</li>
                            <li>Missbrauch der Systemressourcen</li>
                        </ul>

                        <h2>§7 Datenschutz</h2>
                        <p>Die Erhebung, Verarbeitung und Nutzung personenbezogener Daten erfolgt gemäß unserer Datenschutzerklärung, die Bestandteil dieser AGB ist.</p>

                        <h2>§8 Haftung</h2>
                        <p>Der Anbieter haftet für Vorsatz und grobe Fahrlässigkeit. Für leichte Fahrlässigkeit haftet der Anbieter nur bei Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit sowie bei Schäden aus der Verletzung wesentlicher Vertragspflichten. Die Haftung ist auf den vorhersehbaren Schaden begrenzt.</p>

                        <h2>§9 Preise und Zahlung</h2>
                        <p>Die Preise ergeben sich aus der aktuellen Preisliste des Anbieters. Rechnungen sind innerhalb von 14 Tagen nach Rechnungsstellung zur Zahlung fällig. Bei Zahlungsverzug kann der Anbieter die Dienste nach Mahnung einschränken oder beenden.</p>

                        <h2>§10 Kündigung</h2>
                        <p>Der Vertrag kann von beiden Parteien mit einer Frist von 30 Tagen zum Monatsende gekündigt werden. Der Anbieter kann den Vertrag bei schwerwiegenden Verstößen gegen diese AGB sofort kündigen. Nach Vertragsende werden die Daten des Kunden für 30 Tage aufbewahrt und dann gelöscht.</p>

                        <h2>§11 Änderungen der AGB</h2>
                        <p>Der Anbieter behält sich vor, diese AGB bei Bedarf zu ändern. Änderungen werden dem Kunden mindestens 30 Tage vor Inkrafttreten mitgeteilt. Widerspricht der Kunde nicht innerhalb von 14 Tagen, gelten die geänderten AGB als akzeptiert.</p>

                        <h2>§12 Support</h2>
                        <p>Der Anbieter stellt Support über das Support-System zur Verfügung. Support-Anfragen werden während der Geschäftszeiten (Mo-Fr 9:00-18:00) bearbeitet. Für dringende Probleme steht ein Notfall-Support zur Verfügung.</p>

                        <h2>§13 Backup und Datensicherung</h2>
                        <p>Der Anbieter erstellt regelmäßige Backups der Systeme. Der Kunde ist jedoch verpflichtet, eigene Backups seiner Daten zu erstellen. Der Anbieter übernimmt keine Garantie für die Wiederherstellung von Kundendaten.</p>

                        <h2>§14 Geistiges Eigentum</h2>
                        <p>Alle Rechte an der Server Management Plattform und den damit verbundenen Technologien verbleiben beim Anbieter. Der Kunde erhält nur ein einfaches Nutzungsrecht für die Dauer des Vertrags.</p>

                        <h2>§15 Schlussbestimmungen</h2>
                        <p>Für diesen Vertrag gilt deutsches Recht. Gerichtsstand ist der Sitz des Anbieters. Sollten einzelne Bestimmungen unwirksam sein, bleibt der Vertrag im Übrigen wirksam. Unwirksame Bestimmungen werden durch wirksame Bestimmungen ersetzt, die dem wirtschaftlichen Zweck am nächsten kommen.</p>

                        <h2>§16 Kontakt</h2>
                        <p>Bei Fragen zu diesen AGB können Sie sich an uns wenden:</p>
                        <p>
                            <strong>Server Management GmbH</strong><br>
                            Musterstraße 123<br>
                            12345 Musterstadt<br>
                            Deutschland<br><br>
                            
                            E-Mail: recht@servermanagement.de<br>
                            Telefon: +49 123 456789
                        </p>

                        <div class="alert alert-warning mt-4">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Hinweis:</strong> Diese AGB sind eine allgemeine Vorlage und sollten von einem Rechtsanwalt an die spezifischen Bedürfnisse und rechtlichen Anforderungen Ihres Unternehmens angepasst werden.
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

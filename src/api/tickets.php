<?php
/**
 * Ticket API - Endpunkt für Support-Tickets
 */

require_once '../sys.conf.php';
require_once '../../framework.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS Request für CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur POST Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // JSON Input lesen
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Pflichtfelder prüfen
    $requiredFields = ['subject', 'email', 'priority', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // E-Mail validieren
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Priorität validieren
    $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($input['priority'], $allowedPriorities)) {
        throw new Exception('Invalid priority level');
    }
    
    // Daten bereinigen
    $ticketData = [
        'subject' => htmlspecialchars(trim($input['subject']), ENT_QUOTES, 'UTF-8'),
        'email' => filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL),
        'priority' => $input['priority'],
        'message' => htmlspecialchars(trim($input['message']), ENT_QUOTES, 'UTF-8'),

        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'status' => 'open'
    ];
    
    // Datenbankverbindung
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Ticket in Datenbank speichern
    $stmt = $pdo->prepare("
        INSERT INTO support_tickets (
            ticket_number, subject, message, email, customer_name, 
            priority, status, category, source, ip_address, user_agent, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Ticket-Nummer generieren
    $ticketNumber = 'T-' . str_pad(time(), 10, '0', STR_PAD_LEFT);
    
    // Kundenname aus E-Mail extrahieren (falls verfügbar)
    $customerName = $input['customer_name'] ?? '';
    
    // Kategorie bestimmen (einfache Logik)
    $category = 'general';
    if (stripos($ticketData['subject'], 'rechnung') !== false || stripos($ticketData['subject'], 'billing') !== false) {
        $category = 'billing';
    } elseif (stripos($ticketData['subject'], 'bug') !== false || stripos($ticketData['subject'], 'fehler') !== false) {
        $category = 'bug_report';
    } elseif (stripos($ticketData['subject'], 'feature') !== false || stripos($ticketData['subject'], 'wunsch') !== false) {
        $category = 'feature_request';
    }
    
    $result = $stmt->execute([
        $ticketNumber,
        $ticketData['subject'],
        $ticketData['message'],
        $ticketData['email'],
        $customerName,
        $ticketData['priority'],
        $ticketData['status'],
        $category,
        'web', // Quelle ist immer Web-Formular
        $ticketData['ip_address'],
        $ticketData['user_agent']
    ]);
    
    if (!$result) {
        throw new Exception('Failed to save ticket to database');
    }
    
    $ticketId = $pdo->lastInsertId();
    
    // E-Mail-Bestätigung senden
    sendTicketConfirmation($ticketData, $ticketId);
    
    // Admin-Benachrichtigung senden
    sendAdminNotification($ticketData, $ticketId);
    
    // Erfolgsantwort
    echo json_encode([
        'success' => true,
        'message' => 'Ticket erfolgreich erstellt',
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber
    ]);
    
} catch (Exception $e) {
    error_log("Ticket API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * E-Mail-Bestätigung an Kunden senden
 */
function sendTicketConfirmation($ticketData, $ticketId) {
    try {
        $to = $ticketData['email'];
        $subject = "Ticket bestätigt: {$ticketData['subject']}";
        
        $message = "
        <html>
        <head>
            <title>Ticket Bestätigung</title>
        </head>
        <body>
            <h2>Ihr Support-Ticket wurde erfolgreich erstellt</h2>
            <p><strong>Ticket-Nummer:</strong> T-" . str_pad($ticketId, 6, '0', STR_PAD_LEFT) . "</p>
            <p><strong>Betreff:</strong> {$ticketData['subject']}</p>
            <p><strong>Priorität:</strong> " . ucfirst($ticketData['priority']) . "</p>
            <p><strong>Erstellt am:</strong> " . date('d.m.Y H:i:s') . "</p>
            
            <h3>Ihre Nachricht:</h3>
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 15px 0;'>
                " . nl2br($ticketData['message']) . "
            </div>
            
            <p>Wir werden Ihr Anliegen schnellstmöglich bearbeiten. Sie erhalten eine Antwort per E-Mail.</p>
            
            <p>Mit freundlichen Grüßen<br>
            Ihr " . Config::FRONTPANEL_SITE_NAME . " Support-Team</p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
            'Reply-To: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Failed to send ticket confirmation: " . $e->getMessage());
    }
}

/**
 * Admin-Benachrichtigung senden
 */
function sendAdminNotification($ticketData, $ticketId) {
    try {
        // Admin-E-Mail aus Konfiguration holen
        $adminEmail = Config::FRONTPANEL_ADMIN_EMAIL;
        
        $to = $adminEmail;
        $subject = "Neues Support-Ticket: {$ticketData['subject']}";
        
        $message = "
        <html>
        <head>
            <title>Neues Support-Ticket</title>
        </head>
        <body>
            <h2>Ein neues Support-Ticket wurde erstellt</h2>
            <p><strong>Ticket-Nummer:</strong> T-" . str_pad($ticketId, 6, '0', STR_PAD_LEFT) . "</p>
            <p><strong>Von:</strong> {$ticketData['email']}</p>
            <p><strong>Betreff:</strong> {$ticketData['subject']}</p>
            <p><strong>Priorität:</strong> " . ucfirst($ticketData['priority']) . "</p>
            <p><strong>Erstellt am:</strong> " . date('d.m.Y H:i:s') . "</p>
            <p><strong>IP-Adresse:</strong> {$ticketData['ip_address']}</p>
            
            <h3>Nachricht des Kunden:</h3>
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 15px 0;'>
                " . nl2br($ticketData['message']) . "
            </div>
            
            <p><a href='" . Config::FRONTPANEL_SITE_URL . "/admin/tickets/{$ticketId}'>Ticket im Admin-Bereich öffnen</a></p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . Config::FRONTPANEL_SYSTEM_EMAIL,
            'Reply-To: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Failed to send admin notification: " . $e->getMessage());
    }
}

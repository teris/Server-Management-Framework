<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Aktivitäten löschen - Löscht alle Aktivitäten des Benutzers
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';
require_once '../src/core/ActivityLogger.php';

// Session starten
session_start();

// Prüfen ob Kunde eingeloggt ist
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => t('not_logged_in')]);
    exit;
}

$customerId = $_SESSION['customer_id'] ?? 0;

if (!$customerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => t('customer_not_found')]);
    exit;
}

// JSON-Request verarbeiten
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action !== 'clear_all_activities') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => t('invalid_action')]);
    exit;
}

try {
    $db = Database::getInstance();
    $activityLogger = ActivityLogger::getInstance();
    
    // Transaction starten
    $db->beginTransaction();
    
    try {
        // Alle Aktivitäten des Benutzers löschen
        $stmt = $db->prepare("DELETE FROM user_activities WHERE user_id = ? AND user_type = 'customer'");
        $deletedCount = $stmt->execute([$customerId]);
        
        if ($deletedCount) {
            // Neue Aktivität erstellen, die dokumentiert, dass alle Aktivitäten gelöscht wurden
            $activityLogger->logCustomerActivity(
                $customerId,
                'activities_cleared',
                t('all_activities_were_successfully_deleted'),
                null,
                null
            );
            
            // Transaction bestätigen
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => t('all_activities_were_successfully_deleted'),
                'deleted_count' => $stmt->rowCount()
            ]);
        } else {
            // Keine Aktivitäten zum Löschen gefunden
            $db->rollback();
            echo json_encode([
                'success' => false, 
                'error' => t('no_activities_found_for_deletion')
            ]);
        }
        
    } catch (Exception $e) {
        // Bei Fehler Transaction rückgängig machen
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Clear Activities Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => t('error_deleting_activities') . ': ' . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Activity Logger - Speichert alle wichtigen Benutzeraktivitäten
 */

require_once dirname(__FILE__) . '/DatabaseManager.php';

class ActivityLogger {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Aktivität eines Kunden loggen
     */
    public function logCustomerActivity($customerId, $activityType, $description, $relatedId = null, $relatedTable = null) {
        return $this->logActivity($customerId, 'customer', $activityType, $description, $relatedId, $relatedTable);
    }
    
    /**
     * Aktivität eines Administrators loggen
     */
    public function logAdminActivity($adminId, $activityType, $description, $relatedId = null, $relatedTable = null) {
        return $this->logActivity($adminId, 'admin', $activityType, $description, $relatedId, $relatedTable);
    }
    
    /**
     * Aktivität in die Datenbank speichern
     */
    private function logActivity($userId, $userType, $activityType, $description, $relatedId = null, $relatedTable = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activities (user_id, user_type, activity_type, description, related_id, related_table, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            return $stmt->execute([
                $userId, 
                $userType, 
                $activityType, 
                $description, 
                $relatedId, 
                $relatedTable, 
                $ipAddress, 
                $userAgent
            ]);
            
        } catch (Exception $e) {
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Client IP-Adresse ermitteln
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Aktivitäten eines Benutzers abrufen
     */
    public function getUserActivities($userId, $userType = 'customer', $limit = 10, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_activities 
                WHERE user_id = ? AND user_type = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$userId, $userType, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("ActivityLogger getUserActivities Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aktivitäten nach Typ abrufen
     */
    public function getActivitiesByType($activityType, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_activities 
                WHERE activity_type = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$activityType, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("ActivityLogger getActivitiesByType Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Alle Aktivitäten eines Benutzers löschen (für Datenschutz)
     */
    public function deleteUserActivities($userId, $userType = 'customer') {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_activities 
                WHERE user_id = ? AND user_type = ?
            ");
            
            return $stmt->execute([$userId, $userType]);
            
        } catch (Exception $e) {
            error_log("ActivityLogger deleteUserActivities Error: " . $e->getMessage());
            return false;
        }
    }
}

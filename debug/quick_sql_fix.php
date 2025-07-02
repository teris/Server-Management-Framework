<?php
// =============================================================================
// SCHNELLER FIX für framework.php - Nur getActivityLog Methode ersetzen
// =============================================================================

// Suche in framework.php nach dieser Methode:
public function getActivityLog($limit = 50) {
    $stmt = $this->connection->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ERSETZE durch diese korrigierte Version:
public function getActivityLog($limit = 50) {
    try {
        // Limit als Integer validieren
        $limit = (int) $limit;
        if ($limit <= 0) $limit = 50;
        if ($limit > 1000) $limit = 1000;
        
        // LIMIT direkt in Query einbauen (nicht als Parameter)
        $sql = "SELECT id, action, details, status, created_at 
                FROM activity_log 
                ORDER BY created_at DESC 
                LIMIT " . $limit;
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Database getActivityLog error: " . $e->getMessage());
        
        // Fallback: Alle abrufen und manuell limitieren
        try {
            $stmt = $this->connection->prepare("SELECT id, action, details, status, created_at FROM activity_log ORDER BY created_at DESC");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_slice($results, 0, $limit);
        } catch (PDOException $e2) {
            return [];
        }
    }
}
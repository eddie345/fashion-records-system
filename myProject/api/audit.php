<?php
require_once dirname(__DIR__) . '/db.php';

// Internal utility to keep audit logs
function logAudit($userId, $action, $tableName, $recordId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $action, $tableName, $recordId]);
    } catch (\PDOException $e) {
        // Log errors ideally to a file; ignore failure to write audit for simplicity here
    }
}
?>

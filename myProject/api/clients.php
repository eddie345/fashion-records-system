<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';

// Check basic session existence (RBAC: All 3 roles can read client measurements)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $client = $stmt->fetch();
            if ($client) {
                $stmt = $pdo->prepare('SELECT * FROM measurements WHERE client_id = ? ORDER BY created_at DESC LIMIT 1');
                $stmt->execute([$_GET['id']]);
                $client['measurements'] = $stmt->fetch();
                echo json_encode($client);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Client not found']);
            }
        } else {
            $stmt = $pdo->query('SELECT * FROM clients ORDER BY created_at DESC');
            echo json_encode($stmt->fetchAll());
        }
    } elseif ($method === 'POST') {
        // Create new client & measurements
        $data = json_decode(file_get_contents('php://input'), true);
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('INSERT INTO clients (first_name, last_name, email, phone, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$data['first_name'], $data['last_name'], $data['email'] ?? null, $data['phone'] ?? null, getUserId()]);
        $clientId = $pdo->lastInsertId();
        
        logAudit(getUserId(), 'Created Client', 'clients', $clientId);

        if (!empty($data['measurements'])) {
            $m = $data['measurements'];
            // Simplify here to a few main measurements for demonstration
            $stmt = $pdo->prepare('INSERT INTO measurements (client_id, chest, waist, hips, inseam) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$clientId, $m['chest'] ?? null, $m['waist'] ?? null, $m['hips'] ?? null, $m['inseam'] ?? null]);
            logAudit(getUserId(), 'Added Measurements', 'measurements', $pdo->lastInsertId());
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $clientId]);
    } elseif ($method === 'PUT') {
        // Update measurements
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['client_id']) && isset($data['measurements'])) {
            $m = $data['measurements'];
            
            // For simplicity, we create a new measurement record whenever it's updated (so we maintain history)
            $stmt = $pdo->prepare('INSERT INTO measurements (client_id, chest, waist, hips, inseam) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$data['client_id'], $m['chest'] ?? null, $m['waist'] ?? null, $m['hips'] ?? null, $m['inseam'] ?? null]);
            
            logAudit(getUserId(), 'Updated Measurements', 'measurements', $pdo->lastInsertId());
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

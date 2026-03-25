<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM inventory ORDER BY fabric_name ASC');
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['reorder_alert'] = ($item['quantity'] <= $item['reorder_threshold']);
        }
        echo json_encode($items);
    } elseif ($method === 'POST') {
        if (getRole() !== 'Admin' && getRole() !== 'Designer') {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden']));
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('INSERT INTO inventory (fabric_name, origin, quantity, reorder_threshold) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['fabric_name'], $data['origin'] ?? '', $data['quantity'] ?? 0, $data['reorder_threshold'] ?? 10]);
        
        $id = $pdo->lastInsertId();
        logAudit(getUserId(), 'Added Inventory Item', 'inventory', $id);
        echo json_encode(['success' => true, 'id' => $id]);
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id'])) {
            $stmt = $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
            $stmt->execute([$data['quantity'], $data['id']]);
            logAudit(getUserId(), 'Updated Inventory Quantity', 'inventory', $data['id']);
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

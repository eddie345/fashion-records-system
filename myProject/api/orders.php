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
        $stmt = $pdo->query('SELECT o.*, c.first_name, c.last_name, d.title as design_title FROM orders o JOIN clients c ON o.client_id = c.id LEFT JOIN designs d ON o.design_id = d.id ORDER BY o.created_at DESC');
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('INSERT INTO orders (client_id, design_id, status) VALUES (?, ?, ?)');
        $stmt->execute([$data['client_id'], $data['design_id'] ?? null, $data['status'] ?? 'Creation']);
        $orderId = $pdo->lastInsertId();
        logAudit(getUserId(), 'Created Order', 'orders', $orderId);
        echo json_encode(['success' => true, 'id' => $orderId]);
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && isset($data['status'])) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$data['status'], $data['id']]);
            logAudit(getUserId(), "Updated Order Status to {$data['status']}", 'orders', $data['id']);
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT d.*, u.username as uploaded_by_name FROM designs d LEFT JOIN users u ON d.uploaded_by = u.id ORDER BY d.uploaded_at DESC');
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        if (getRole() !== 'Admin' && getRole() !== 'Designer') {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden']));
        }
        $title = $_POST['title'] ?? 'Untitled Design';
        $desc = $_POST['description'] ?? '';
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = time() . '_' . basename($_FILES['file']['name']);
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $stmt = $pdo->prepare('INSERT INTO designs (title, description, file_path, uploaded_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$title, $desc, 'uploads/' . $filename, getUserId()]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            } else {
                throw new Exception("Failed to move uploaded file");
            }
        } else {
            throw new Exception("No file uploaded or upload error");
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

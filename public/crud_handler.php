<?php
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://ibdx.github.io'];
$isLocalDevOrigin = $requestOrigin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#', $requestOrigin) === 1;

if ($requestOrigin !== '' && ($isLocalDevOrigin || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Simple CRUD handler for demo_items table
 * - Demonstrates usage of $_POST (form submissions)
 * - Supports JSON API when called with ?api=1
 */

require_once __DIR__ . '/../config/database.php';

header('X-Content-Type-Options: nosniff');



$useApi = isset($_GET['api']) && $_GET['api'] == '1';

if ($useApi) {
    header('Content-Type: application/json; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        if ($method === 'GET') {
            // list all or single by id
            if (isset($_GET['id'])) {
                $row = fetchOne('SELECT * FROM demo_items WHERE id = ?', [$_GET['id']]);
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                $rows = fetchAll('SELECT * FROM demo_items ORDER BY id DESC', []);
                echo json_encode(['success' => true, 'data' => $rows]);
            }
            exit;
        }

        // POST for create, PUT for update, DELETE for delete (simple mapping)
        $action = $input['action'] ?? ($input['method'] ?? '');

        switch ($action) {
            case 'insert':
                $name = $input['name'] ?? '';
                $email = $input['email'] ?? '';
                if (!$name || !$email) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Name and email required']);
                    exit;
                }
                $id = insert('INSERT INTO demo_items (name, email) VALUES (?, ?)', [$name, $email]);
                echo json_encode(['success' => true, 'id' => $id]);
                break;

            case 'update':
                $id = (int)($input['id'] ?? 0);
                $name = $input['name'] ?? '';
                $email = $input['email'] ?? '';
                if (!$id || !$name || !$email) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID, name and email required']);
                    exit;
                }
                $count = execute('UPDATE demo_items SET name = ?, email = ? WHERE id = ?', [$name, $email, $id]);
                echo json_encode(['success' => true, 'affected' => $count]);
                break;

            case 'delete':
                $id = (int)($input['id'] ?? 0);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID required']);
                    exit;
                }
                $count = execute('DELETE FROM demo_items WHERE id = ?', [$id]);
                echo json_encode(['success' => true, 'affected' => $count]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Non-API: handle simple form POST using $_POST to demonstrate superglobal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'insert';

    try {
        switch ($action) {
            case 'insert':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                if (!$name || !$email) {
                    $message = 'Name and email are required.';
                } else {
                    $id = insert('INSERT INTO demo_items (name, email) VALUES (?, ?)', [$name, $email]);
                    $message = "Inserted record with ID: $id";
                }
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                if (!$id || !$name || !$email) {
                    $message = 'ID, name and email are required for update.';
                } else {
                    $affected = execute('UPDATE demo_items SET name = ?, email = ? WHERE id = ?', [$name, $email, $id]);
                    $message = "Updated $affected row(s).";
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) {
                    $message = 'ID is required to delete.';
                } else {
                    $affected = execute('DELETE FROM demo_items WHERE id = ?', [$id]);
                    $message = "Deleted $affected row(s).";
                }
                break;

            default:
                $message = 'Unknown action.';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }

    // Simple HTML response so student can see result of $_POST handling
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"><title>CRUD Result</title></head>
    <body>
        <h1>CRUD Result</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <p><a href="/Webproject2/public/crud_demo.html">Back to demo</a></p>
    </body>
    </html>
    <?php
    exit;
}

// If reached without POST or API, show a small help page
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>CRUD Handler</title></head>
<body>
    <h1>CRUD Handler</h1>
    <p>This endpoint accepts form POSTs (demonstrates <code>$_POST</code>) and JSON API calls with <code>?api=1</code>.</p>
    <ul>
        <li>Visit <a href="/Webproject2/public/crud_demo.html">crud_demo.html</a> for an interactive demo.</li>
        <li>AJAX API example: <code>GET /Webproject2_DB/public/crud_handler.php?api=1</code></li>
    </ul>
</body>
</html>

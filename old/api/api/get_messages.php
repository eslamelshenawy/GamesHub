<?php
// Defensive wrapper for get_messages endpoint
ob_start();
// Use absolute paths so include works regardless of current working directory
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json; charset=utf-8');
ensure_session();

// Log request useful info early for debugging
error_log('[get_messages] enter; REMOTE_ADDR=' . ($_SERVER['REMOTE_ADDR'] ?? 'n/a') . ' QUERY=' . ($_SERVER['QUERY_STRING'] ?? '') . ' METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? 'n/a'));

// افترضنا أن المستخدم مسجل دخول ومعه user_id
$current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
if ($current_user <= 0) {
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    ob_end_flush();
    exit;
}
$chat_with = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($chat_with <= 0) {
    echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
    ob_end_flush();
    exit;
}

try {
        // Log detailed debug information for troubleshooting
        error_log('[get_messages] Debug: current_user=' . $current_user . ', chat_with=' . $chat_with);

        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, sender_id, receiver_id, message_text, created_at, is_read
            FROM messages
            WHERE 
                (sender_id = ? AND receiver_id = ?) OR
                (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        SQL
        );
        // Check database connection
        if (!$pdo) {
            error_log('[get_messages] Database connection is null.');
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            ob_end_flush();
            exit;
        }

    // Log query execution
    error_log('[get_messages] Executing query to fetch messages.');
    // Use positional parameters to avoid drivers that don't support repeating named params
    $stmt->execute([$current_user, $chat_with, $chat_with, $current_user]);
        $messages = $stmt->fetchAll();
        error_log("[get_messages] current_user={$current_user} chat_with={$chat_with} messages_count=".count($messages));

        // Fix syntax errors in UTF-8 conversion logic
        $utf8ize = function (&$item) use (&$utf8ize) {
            if (is_array($item)) {
                foreach ($item as &$v) {
                    $utf8ize($v);
                }
                unset($v);
            } else if (is_string($item)) {
                if (!mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'Windows-1252');
                }
            }
        };

        $utf8ize($messages);

        // Ensure all strings in the fetched messages are UTF-8 encoded
        array_walk_recursive($messages, function (&$value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            }
        });

        $payload = ['success' => true, 'messages' => $messages, 'me' => $current_user];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            error_log('[get_messages] json_encode failed: ' . json_last_error_msg());
            // Try a safer second pass converting problematic strings
            array_walk_recursive($messages, function (&$v) {
                if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
                    $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
                }
            });
            $payload['messages'] = $messages;
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                error_log('[get_messages] json_encode still failing: ' . json_last_error_msg());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'تعذر ترميز بيانات الرسائل']);
                ob_end_flush();
                exit;
            }
        }

        echo $json;
        ob_end_flush();
} catch (PDOException $e) {
    // Log detailed exception for server-side debugging (not shown to client)
    error_log('[get_messages] PDOException: ' . $e->getMessage());
    if (method_exists($e, 'getTraceAsString')) {
        error_log('[get_messages] Trace: ' . $e->getTraceAsString());
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '\u062e\u0637\u0623 \u0641\u064a \u0627\u0644\u062e\u0627\u062f\u0645']);
    ob_end_flush();
}

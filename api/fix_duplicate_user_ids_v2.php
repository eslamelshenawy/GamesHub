<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Security check
$allowExecution = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$allowExecution) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Not authorized. Add ?confirm=yes to execute this script.',
        'warning' => 'This will delete all users with id=0!'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $steps = [];

    // Step 1: Count users with id = 0
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE id = 0");
    $count = $stmt->fetchColumn();

    $steps[] = [
        'step' => 1,
        'action' => 'Count users with id=0',
        'count' => $count,
    ];

    if ($count == 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No users with id=0 found!',
            'steps' => $steps,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Step 2: Delete all users with id = 0
    // These are test/broken accounts anyway, so it's safe to delete them
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = 0");
    $result = $stmt->execute();
    $deletedCount = $stmt->rowCount();

    $steps[] = [
        'step' => 2,
        'action' => 'Delete users with id=0',
        'deleted_count' => $deletedCount,
        'success' => $result,
    ];

    // Step 3: Verify no more id=0 users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE id = 0");
    $remainingCount = $stmt->fetchColumn();

    $steps[] = [
        'step' => 3,
        'action' => 'Verify deletion',
        'remaining_count' => $remainingCount,
    ];

    // Step 4: Check for any other duplicate IDs
    $stmt = $pdo->query("SELECT id, COUNT(*) as count FROM users GROUP BY id HAVING count > 1");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 4,
        'action' => 'Check for remaining duplicates',
        'duplicate_count' => count($duplicates),
        'duplicates' => $duplicates,
    ];

    echo json_encode([
        'success' => true,
        'message' => "Deleted {$deletedCount} users with id=0",
        'steps' => $steps,
        'next_step' => $remainingCount == 0 && empty($duplicates)
            ? 'Now run fix_users_table_id.php?confirm=yes to add PRIMARY KEY and AUTO_INCREMENT'
            : 'There are still issues, check the steps above',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'steps' => $steps ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

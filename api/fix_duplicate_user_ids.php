<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Security check
$allowExecution = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$allowExecution) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Not authorized. Add ?confirm=yes to execute this script.',
        'warning' => 'This will fix duplicate user IDs in the database!'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $steps = [];

    // Step 1: Find all users with id = 0 or duplicate IDs
    $stmt = $pdo->query("SELECT id, COUNT(*) as count FROM users GROUP BY id HAVING count > 1 OR id = 0 ORDER BY count DESC");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 1,
        'action' => 'Find duplicate IDs',
        'duplicate_ids' => $duplicates,
    ];

    // Step 2: Find the maximum ID in the table
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM users WHERE id > 0");
    $maxId = (int)$stmt->fetchColumn();

    $steps[] = [
        'step' => 2,
        'action' => 'Find max ID',
        'max_id' => $maxId,
    ];

    // Step 3: Get all users that need fixing (id = 0 or duplicates)
    $stmt = $pdo->query("
        SELECT * FROM users
        WHERE id = 0
           OR id IN (
               SELECT id FROM (
                   SELECT id FROM users
                   GROUP BY id
                   HAVING COUNT(*) > 1
               ) as dups
           )
        ORDER BY id, name
    ");
    $problematicUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 3,
        'action' => 'Find problematic users',
        'count' => count($problematicUsers),
        'users' => array_map(function($u) {
            return [
                'id' => $u['id'],
                'name' => $u['name'],
                'email' => $u['email'],
            ];
        }, $problematicUsers),
    ];

    // Step 4: Reassign IDs to problematic users
    $newId = $maxId + 1;
    $reassignments = [];

    // First pass: identify which users to keep and which to reassign
    $seenIds = [];
    $usersToReassign = [];

    foreach ($problematicUsers as $user) {
        $currentId = (int)$user['id'];

        // If id is 0 or we've seen this ID before, reassign
        if ($currentId === 0 || isset($seenIds[$currentId])) {
            $usersToReassign[] = $user;
        } else {
            // Keep this user's ID (first occurrence)
            $seenIds[$currentId] = true;
        }
    }

    // Second pass: reassign IDs
    foreach ($usersToReassign as $user) {
        $oldId = $user['id'];

        // Update user with new ID
        // We need to identify the user uniquely by multiple fields
        $updateStmt = $pdo->prepare("
            UPDATE users
            SET id = ?
            WHERE name = ? AND email = ? AND phone = ?
            LIMIT 1
        ");

        $result = $updateStmt->execute([
            $newId,
            $user['name'],
            $user['email'],
            $user['phone']
        ]);

        $reassignments[] = [
            'old_id' => $oldId,
            'new_id' => $newId,
            'name' => $user['name'],
            'email' => $user['email'],
            'success' => $result,
        ];

        $newId++;
    }

    $steps[] = [
        'step' => 4,
        'action' => 'Reassign IDs',
        'reassignments' => $reassignments,
        'total_reassigned' => count($reassignments),
    ];

    // Step 5: Verify no more duplicates
    $stmt = $pdo->query("SELECT id, COUNT(*) as count FROM users GROUP BY id HAVING count > 1 OR id = 0");
    $remainingDuplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 5,
        'action' => 'Verify fix',
        'remaining_duplicates' => count($remainingDuplicates),
        'duplicates' => $remainingDuplicates,
    ];

    echo json_encode([
        'success' => true,
        'message' => 'User IDs fixed successfully!',
        'steps' => $steps,
        'next_step' => 'Now run fix_users_table_id.php?confirm=yes to add PRIMARY KEY and AUTO_INCREMENT',
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

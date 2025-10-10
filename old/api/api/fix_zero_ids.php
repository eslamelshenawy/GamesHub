<?php
// fix_zero_ids.php
// Usage: PUT this file in your api folder and call it (only on trusted environment).
// WARNING: Make a DB backup before running on production!

require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!$pdo) {
        throw new Exception('No DB connection (check db.php)');
    }

    // Start transaction to avoid races
    $pdo->beginTransaction();

    // Lock and fetch all existing ids (for update to prevent race conditions)
    $stmt = $pdo->query("SELECT id FROM users FOR UPDATE");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $usedIds = [];
    foreach ($rows as $r) {
        // normalize nulls/strings to int
        $intId = (int)$r;
        if ($intId > 0) {
            $usedIds[$intId] = true;
        }
    }

    // Fetch users that currently have id = 0 (also locked by transaction above due to FOR UPDATE)
    $stmtZero = $pdo->prepare("SELECT email, name FROM users WHERE id = 0");
    $stmtZero->execute();
    $zeroUsers = $stmtZero->fetchAll(PDO::FETCH_ASSOC);

    $countZero = count($zeroUsers);

    // Build list of available ids from 1..99 excluding used
    $allCandidate = range(1, 99);
    $available = [];
    foreach ($allCandidate as $cand) {
        if (!isset($usedIds[$cand])) {
            $available[] = $cand;
        }
    }

    if (count($available) < $countZero) {
        // Not enough available ids to assign
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Not enough available IDs (1..99) to assign to all users with id=0.',
            'needed' => $countZero,
            'available' => count($available)
        ]);
        exit;
    }

    // Shuffle available ids to assign randomly
    shuffle($available);

    // Prepare update statement (use email+id=0 in WHERE to be safer)
    $updateStmt = $pdo->prepare("UPDATE users SET id = :newid WHERE id = 0 AND email = :email");

    $assigned = [];
    for ($i = 0; $i < $countZero; $i++) {
        $user = $zeroUsers[$i];
        $newId = array_pop($available); // get one available id
        $updateStmt->execute([
            ':newid' => $newId,
            ':email' => $user['email']
        ]);

        // Check affected rows (optional)
        $affected = $updateStmt->rowCount();

        // If not updated (0 rows affected) then something changed; roll back and error
        if ($affected === 0) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update user (possible concurrent change).',
                'email' => $user['email'],
                'attempted_id' => $newId
            ]);
            exit;
        }

        $assigned[] = [
            'email' => $user['email'],
            'name' => $user['name'],
            'new_id' => $newId
        ];
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Assigned new IDs for users who had id = 0',
        'assigned_count' => count($assigned),
        'assigned' => $assigned
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}
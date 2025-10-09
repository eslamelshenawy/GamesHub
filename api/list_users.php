<?php
require_once 'db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT id, email, name, role FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();

    echo "<h2>Users Table</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th></tr>";

    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (Exception $e) {
    echo "Error fetching users: " . htmlspecialchars($e->getMessage());
}
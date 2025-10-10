<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: text/plain; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

try {
	// ensure `role` column exists in users table (simple, idempotent alter)
	try {
		$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(32) DEFAULT 'buyer'");
	} catch (Exception $e) {
		// some MySQL versions don't support IF NOT EXISTS for ADD COLUMN; fallback to checking INFORMATION_SCHEMA
		$colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
		$colCheck->execute();
		if (!$colCheck->fetch()) {
			$pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) DEFAULT 'buyer'");
		}
	}

	// create two test users if not exists
	$users = [
		['phone' => '1000000001', 'name' => 'Seller Test'],
		['phone' => '1000000002', 'name' => 'Buyer Test']
	];
	foreach ($users as $u) {
		$stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
		$stmt->execute([$u['phone']]);
		$row = $stmt->fetch();
		if (!$row) {
			$passHash = password_hash('password', PASSWORD_DEFAULT);
			// determine role by phone (first user -> seller, second -> buyer)
			$role = ($u['phone'] === '1000000001') ? 'seller' : 'buyer';
			$pdo->prepare('INSERT INTO users (phone, name, password, role) VALUES (?, ?, ?, ?)')
				->execute([$u['phone'], $u['name'], $passHash, $role]);
			echo "Inserted user: {$u['name']} ({$u['phone']})\n";
		} else {
			echo "User exists: {$u['name']} ({$u['phone']}) id={$row['id']}\n";
			// ensure existing user has role set
			$setRole = ($u['phone'] === '1000000001') ? 'seller' : 'buyer';
			$pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$setRole, $row['id']]);
		}
	}

	// create a sample message if both users exist
	$stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
	$stmt->execute(['1000000001']); $seller = $stmt->fetch();
	$stmt->execute(['1000000002']); $buyer = $stmt->fetch();
	if ($seller && $buyer) {
		// insert example message from buyer -> seller
			$stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, is_read) VALUES (?, ?, ?, 0)');
			$stmt->execute([$buyer['id'], $seller['id'], 'رسالة اختبار من المشتري إلى البائع']);
		echo "Inserted sample message from buyer({$buyer['id']}) to seller({$seller['id']})\n";
	} else {
		echo "Could not find both users to seed messages.\n";
	}

	echo "Done. You can log in with phone 1000000001 or 1000000002 and password 'password'\n";
} catch (Exception $e) {
	echo 'Error: ' . $e->getMessage();
}


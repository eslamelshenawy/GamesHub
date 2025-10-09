<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
	exit;
}
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT a.*, GROUP_CONCAT(ai.image_path) as images FROM favorites f JOIN accounts a ON f.account_id = a.id LEFT JOIN account_images ai ON a.id = ai.account_id WHERE f.user_id = ? GROUP BY a.id ORDER BY f.created_at DESC');
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();
foreach($accounts as &$acc){
	$acc['images'] = $acc['images'] ? explode(',', $acc['images']) : [];
}
echo json_encode($accounts);
exit;

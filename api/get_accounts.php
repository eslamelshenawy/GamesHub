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
// جلب جميع الحسابات الخاصة بالمستخدم الحالي مع الصور
$stmt = $pdo->prepare('SELECT a.id, a.game_name, a.description, a.price, GROUP_CONCAT(ai.image_path ORDER BY ai.id) as images FROM accounts a LEFT JOIN account_images ai ON a.id = ai.account_id WHERE a.user_id = ? GROUP BY a.id ORDER BY a.id DESC');
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
foreach($accounts as &$acc){
    $acc['images'] = $acc['images'] ? explode(',', $acc['images']) : [];
}
echo json_encode($accounts);
exit;

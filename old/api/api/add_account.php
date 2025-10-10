
<?php
// إظهار جميع الأخطاء مؤقتًا لتسهيل التصحيح
ini_set('display_errors', 1);
error_reporting(E_ALL);
// منع أي إخراج قبل الهيدر
ob_start();

require_once 'db.php';
require_once 'security.php';
ensure_session();

header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) {
	// For AJAX clients: return 401 with an optional redirect URL
	http_response_code(401);
	$redirect = '../login.html?return=add-account.html';
	echo json_encode(['error' => 'يجب تسجيل الدخول أولاً', 'redirect' => $redirect]);
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
	$description = isset($_POST['description']) ? trim($_POST['description']) : '';
	$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
	$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
	if (!validate_csrf_token($csrf) || empty($game_name) || empty($description) || $price <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'طلب غير صالح']);
		exit;
	}
	// حفظ الحساب مع created_at
	$stmt = $pdo->prepare('INSERT INTO accounts (user_id, game_name, description, price, created_at) VALUES (?, ?, ?, ?, NOW())');
	$stmt->execute([$_SESSION['user_id'], $game_name, $description, $price]);
	$account_id = $pdo->lastInsertId();

	// حفظ الصور إذا تم رفعها مع uploaded_at
	$image_paths = [];
	if (
		isset($_FILES['images']) &&
		isset($_FILES['images']['name']) &&
		is_array($_FILES['images']['name']) &&
		count($_FILES['images']['name']) > 0 &&
		!empty($_FILES['images']['name'][0])
	) {
		$max_images = 30;
		$total_images = count($_FILES['images']['name']);
		if ($total_images > $max_images) {
			http_response_code(400);
			echo json_encode(['error' => 'الحد الأقصى للصور هو 30 صورة فقط.']);
			exit;
		}
		$upload_dir = '../uploads/';
		$allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
		$max_size = 5*1024*1024; // 5MB
		foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
			if (empty($tmp_name) || !is_uploaded_file($tmp_name)) continue;
			$type = mime_content_type($tmp_name);
			$size = filesize($tmp_name);
			if (!in_array($type, $allowed_types)) continue;
			if ($size > $max_size) continue;
			$file_name = uniqid('img_') . '_' . basename($_FILES['images']['name'][$key]);
			$target = $upload_dir . $file_name;
			// ضغط الصورة إذا كانت JPEG أو PNG وامتداد GD متوفر
			if (($type === 'image/jpeg' || $type === 'image/png') && extension_loaded('gd')) {
				$img = ($type==='image/jpeg') ? @imagecreatefromjpeg($tmp_name) : @imagecreatefrompng($tmp_name);
				if ($img) {
					$w = imagesx($img); $h = imagesy($img);
					$maxDim = 1200;
					if ($w > $maxDim || $h > $maxDim) {
						$ratio = min($maxDim/$w, $maxDim/$h);
						$nw = intval($w*$ratio); $nh = intval($h*$ratio);
						$newImg = imagecreatetruecolor($nw, $nh);
						imagecopyresampled($newImg, $img, 0,0,0,0, $nw,$nh, $w,$h);
						$img = $newImg;
					}
					if ($type==='image/jpeg') {
						imagejpeg($img, $target, 80);
					} else {
						imagepng($img, $target, 7);
					}
					imagedestroy($img);
				} else {
					// إذا فشل إنشاء الصورة، انسخ الملف مباشرة
					move_uploaded_file($tmp_name, $target);
				}
			} else {
				// إذا لم يكن امتداد GD متوفراً، انسخ الملف مباشرة
				move_uploaded_file($tmp_name, $target);
			}
			if (file_exists($target)) {
				$image_paths[] = 'uploads/' . $file_name;
				$stmt_img = $pdo->prepare('INSERT INTO account_images (account_id, image_path, uploaded_at) VALUES (?, ?, NOW())');
				$stmt_img->execute([$account_id, 'uploads/' . $file_name]);
			}
		}
	}
	echo json_encode(['success' => true, 'images' => $image_paths, 'redirect' => 'myaccount.html']);
	ob_end_flush();
	exit;
}

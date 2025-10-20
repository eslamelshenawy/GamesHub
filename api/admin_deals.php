<?php
// api/admin_deals.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'غير مخول للوصول']);
    exit;
}

// Handle both GET and POST requests
$action = $_GET['action'] ?? '';

// If it's a POST request, try to get action from JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'get_pending_deals':
        getPendingDeals();
        break;
    case 'approve_deal':
    case 'approve':
        approveDeal();
        break;
    case 'reject_deal':
    case 'reject':
        rejectDeal();
        break;
    case 'fund_deal':
    case 'fund':
        fundDeal();
        break;
    case 'release_funds_to_seller':
        releaseFundsToSeller();
        break;
    case 'get_deal_conversation':
        getDealConversation();
        break;
    case 'get_deal_by_conversation':
        getDealByConversation();
        break;
    case 'details':
        getDealDetails();
        break;
    case 'reject_cancel_request':
        rejectCancelRequest();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'إجراء غير صحيح']);
}

function getPendingDeals() {
    global $pdo;

    try {
        $stmt = $pdo->prepare('
            SELECT d.*,
                   buyer.name as buyer_name, buyer.phone as buyer_phone,
                   seller.name as seller_name, seller.phone as seller_phone,
                   a.game_name, "" as account_description
            FROM deals d
            JOIN users buyer ON d.buyer_id = buyer.id
            JOIN users seller ON d.seller_id = seller.id
            LEFT JOIN accounts a ON d.account_id = a.id
            WHERE d.status IN ("FUNDED", "PENDING_CANCEL")
            ORDER BY d.updated_at DESC
        ');
        $stmt->execute();
        $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'deals' => $deals]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function approveDeal() {
    global $pdo;
    
    // Accept both GET and POST methods
    $deal_id = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['deal_id'])) {
            $deal_id = intval($input['deal_id']);
        }
    } else {
        // GET method - get deal_id from URL parameters
        if (isset($_GET['deal_id'])) {
            $deal_id = intval($_GET['deal_id']);
        }
    }
    
    if (!$deal_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    try {

        
        $pdo->beginTransaction();
        
        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        
        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو تم معالجتها مسبقاً']);
            return;
        }

        // التحقق من وجود محفظة للمشتري
        $stmt = $pdo->prepare('SELECT balance, pending_balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$deal['buyer_id']]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'لم يتم العثور على محفظة المشتري']);
            return;
        }
        
        // التحقق من كفاية الرصيد المعلق لتغطية مبلغ الصفقة
        $amount_to_transfer = $deal['amount'];
        if ($wallet['pending_balance'] < $amount_to_transfer) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'الرصيد المعلق غير كافي لإتمام الصفقة',
                'details' => [
                    'required' => $amount_to_transfer,
                    'available' => $wallet['pending_balance']
                ]
            ]);
            return;
        }
        
        // التحقق من وجود محفظة للبائع وإنشاؤها إذا لم تكن موجودة
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ?');
        $stmt->execute([$deal['seller_id']]);
        $seller_wallet = $stmt->fetch();
        
        if (!$seller_wallet) {
            // إنشاء محفظة للبائع
            $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)');
            $stmt->execute([$deal['seller_id']]);
        }
        
        // حساب الخصم (10% من المبلغ)
        $fee_percentage = 0.10; // 10%
        $fee_amount = $amount_to_transfer * $fee_percentage;
        $seller_amount = $amount_to_transfer - $fee_amount;
        
        // تحويل المبلغ من الرصيد المعلق للمشتري إلى رصيد البائع
        $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ? AND pending_balance >= ?');
        $stmt->execute([$amount_to_transfer, $deal['buyer_id'], $amount_to_transfer]);
        
        // التحقق من نجاح عملية السحب
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'فشل في سحب المبلغ من الرصيد المعلق للمشتري']);
            return;
        }
        
        // إضافة المبلغ للبائع (بعد خصم 10%)
        $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
        $stmt->execute([$seller_amount, $deal['seller_id']]);
        
        // التحقق من نجاح عملية إضافة المبلغ للبائع
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'فشل في إضافة المبلغ لرصيد البائع']);
            return;
        }
        
        // إضافة الرسوم إلى رصيد النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" OR role = "admin" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        
        if ($system_user) {
            $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
            $stmt->execute([$fee_amount, $system_user['id']]);
        }
        
        // تحديث حالة الصفقة مع حفظ تفاصيل الرسوم
        $stmt = $pdo->prepare('UPDATE deals SET status = "COMPLETED", escrow_status = "RELEASED", platform_fee = ?, seller_amount = ?, fee_percentage = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$fee_amount, $seller_amount, 10.00, $deal_id]);
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;
        
        // تسجيل المعاملة في سجل المعاملات المالية (المبلغ للبائع)
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, "deposit", ?, ?, ?, "تحويل مبلغ الصفقة للبائع بعد خصم رسوم المنصة")');
        $stmt->execute([$deal_id, $seller_amount, $system_user_id, $deal['seller_id']]);
        
        // تسجيل رسوم المنصة إذا كان هناك مستخدم نظام
        if ($system_user_id) {
            $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, "fee", ?, ?, ?, "رسوم المنصة 10%")');
            $stmt->execute([$deal_id, $fee_amount, $deal['seller_id'], $system_user_id]);
        }
        
        // تسجيل المعاملة في سجل المحفظة (المبلغ بعد خصم الرسوم)
        $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$deal['seller_id'], $seller_amount, 'deposit', "تحويل من صفقة رقم {$deal_id} (بعد خصم رسوم 10%)"]);
        
        // تسجيل معاملة الرسوم للنظام إذا كان موجود
        if ($system_user_id) {
            $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$system_user_id, $fee_amount, 'fee', "رسوم منصة من صفقة رقم {$deal_id}"]);
        }
        
        // إضافة رسالة في المحادثة
        $message = "تم اعتماد الصفقة من قبل الإدارة. تم تحويل المبلغ {$seller_amount} جنيه إلى البائع (بعد خصم رسوم المنصة 10% = {$fee_amount} جنيه).";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]); // system user
        
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'تم اعتماد الصفقة بنجاح']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'General error: ' . $e->getMessage()]);
    }
}

function rejectDeal() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['deal_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    $deal_id = intval($input['deal_id']);
    $reason = $input['reason'] ?? 'لم يتم تحديد السبب';
    
    try {
        $pdo->beginTransaction();
        
        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?  FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        
        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير قابلة للرفض']);
            return;
        }
        
        // التحقق من وجود محفظة للمشتري وإنشاؤها إذا لم تكن موجودة
        $stmt = $pdo->prepare('SELECT pending_balance FROM wallets WHERE user_id = ?');
        $stmt->execute([$deal['buyer_id']]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            // إنشاء محفظة للمشتري
            $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)');
            $stmt->execute([$deal['buyer_id']]);
            $wallet = ['pending_balance' => 0.00];
        }
        
        // إرجاع المبلغ من الرصيد المعلق إلى الإدارة أولاً - نستخدم مبلغ الصفقة الأصلي فقط
        $amount_to_refund = $deal['amount'];

        // الحصول على حساب الأدمن/النظام
        $admin_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" OR role = "admin" ORDER BY role DESC LIMIT 1');
        $admin_stmt->execute();
        $admin_user = $admin_stmt->fetch();

        if (!$admin_user) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'لم يتم العثور على حساب الإدارة']);
            return;
        }

        // التحقق من وجود محفظة للإدارة وإنشاؤها إذا لم تكن موجودة
        $admin_wallet_stmt = $pdo->prepare('SELECT pending_balance FROM wallets WHERE user_id = ?');
        $admin_wallet_stmt->execute([$admin_user['id']]);
        $admin_wallet = $admin_wallet_stmt->fetch();

        if (!$admin_wallet) {
            // إنشاء محفظة للإدارة
            $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)');
            $stmt->execute([$admin_user['id']]);
        }

        // تحديث المحفظة - نقل المبلغ من الرصيد المعلق للمشتري إلى الرصيد المعلق للإدارة
        $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?');
        $stmt->execute([$amount_to_refund, $deal['buyer_id']]);

        // إضافة المبلغ للرصيد المعلق للإدارة (ليس الرصيد الأساسي)
        $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?');
        $stmt->execute([$amount_to_refund, $admin_user['id']]);
        
        // تحديث حالة الصفقة
        $stmt = $pdo->prepare('UPDATE deals SET status = "CANCELLED", escrow_status = "REFUNDED", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$deal_id]);
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;
        
        // تسجيل المعاملة المالية - إرجاع للإدارة
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, "REFUND_TO_ADMIN", ?, ?, ?)');
        $stmt->execute([$deal_id, $amount_to_refund, $deal['buyer_id'], $admin_user['id']]);

        // تسجيل معاملة في wallet_transactions للمشتري
        $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, "refund_to_admin", ?)');
        $stmt->execute([$deal['buyer_id'], -$amount_to_refund, "إرجاع مبلغ صفقة رقم {$deal_id} للإدارة - {$reason}"]);

        // تسجيل معاملة في wallet_transactions للإدارة
        $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, "refund_received", ?)');
        $stmt->execute([$admin_user['id'], $amount_to_refund, "استلام مبلغ مسترد من صفقة رقم {$deal_id} - {$reason}"]);

        // إضافة رسالة في المحادثة
        $message = "⚠️ تم رفض الصفقة من قبل الإدارة.\n\nالسبب: {$reason}\n\nتم إرجاع المبلغ {$amount_to_refund} جنيه إلى الإدارة في الرصيد المعلق. للحصول على استرداد كامل، يرجى التواصل مع الدعم الفني.";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]);

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);
        
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'تم رفض الصفقة وإرجاع المبلغ']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error in rejectDeal: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم', 'debug' => $e->getMessage()]);
    }
}

function fundDeal() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['deal_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    $deal_id = intval($input['deal_id']);
    
    try {
        $pdo->beginTransaction();
        
        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        
        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
            return;
        }
        
        // تحديث حالة الصفقة إلى FUNDED
        $stmt = $pdo->prepare('UPDATE deals SET status = "FUNDED", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$deal_id]);
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;
        
        // إضافة رسالة في المحادثة
        $message = "تم تمويل الصفقة من قبل الإدارة. الصفقة الآن في حالة FUNDED.";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]);
        
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'تم تمويل الصفقة بنجاح']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function getDealConversation() {
    global $pdo;
    
    $deal_id = $_GET['deal_id'] ?? '';
    if (!$deal_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    try {
        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('
            SELECT d.*, 
                   buyer.name as buyer_name,
                   seller.name as seller_name,
                   a.game_name, "" as account_description
            FROM deals d
            JOIN users buyer ON d.buyer_id = buyer.id
            JOIN users seller ON d.seller_id = seller.id
            LEFT JOIN accounts a ON d.account_id = a.id
            WHERE d.id = ?
        ');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        
        if (!$deal) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
            return;
        }
        
        // الحصول على رسائل المحادثة المتعلقة بالصفقة
        $stmt = $pdo->prepare('
            SELECT m.*, u.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.deal_id = ? OR (m.sender_id IN (?, ?) AND m.receiver_id IN (?, ?))
            ORDER BY m.created_at ASC
        ');
        $stmt->execute([$deal_id, $deal['buyer_id'], $deal['seller_id'], $deal['buyer_id'], $deal['seller_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'deal' => $deal,
            'messages' => $messages
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function getDealDetails() {
    global $pdo;
    
    $deal_id = $_GET['id'] ?? '';
    if (!$deal_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare('
            SELECT d.*, 
                   buyer.name as buyer_name, buyer.phone as buyer_phone,
                   seller.name as seller_name, seller.phone as seller_phone,
                   a.game_name, "" as account_description
            FROM deals d
            JOIN users buyer ON d.buyer_id = buyer.id
            JOIN users seller ON d.seller_id = seller.id
            LEFT JOIN accounts a ON d.account_id = a.id
            WHERE d.id = ?
        ');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deal) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
            return;
        }
        
        echo json_encode(['success' => true, 'deal' => $deal]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function releaseFundsToSeller() {
    global $pdo;
    
    // Accept both GET and POST methods
    $deal_id = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['deal_id'])) {
            $deal_id = intval($input['deal_id']);
        }
    } else {
        // GET method - get deal_id from URL parameters
        if (isset($_GET['deal_id'])) {
            $deal_id = intval($_GET['deal_id']);
        }
    }
    
    if (!$deal_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // جلب بيانات الصفقة والتحقق من صحتها
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
            return;
        }
        
        // التحقق من حالة الصفقة - المدير يمكنه تحرير الأموال في معظم الحالات
        // منع التحرير فقط للصفقات المكتملة أو المرفوضة أو الملغاة
        if (in_array($deal['status'], ['COMPLETED', 'REJECTED', 'CANCELLED'])) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'لا يمكن تحرير الأموال لهذه الصفقة - الصفقة مكتملة أو ملغاة بالفعل']);
            return;
        }
        
        // التحقق من وجود أموال للتحرير - السماح بالتحرير حتى لو لم تكن في الضمان
        if ($deal['escrow_status'] === 'RELEASED') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'تم تحرير الأموال بالفعل لهذه الصفقة']);
            return;
        }
        
        // التحقق من وجود محفظة البائع
        $stmt = $pdo->prepare('SELECT balance, pending_balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$deal['seller_id']]);
        $seller_wallet = $stmt->fetch();
        
        if (!$seller_wallet) {
            // إنشاء محفظة للبائع إذا لم تكن موجودة
            $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)');
            $stmt->execute([$deal['seller_id']]);
            $seller_wallet = ['balance' => 0.00, 'pending_balance' => 0.00];
        }
        
        // حساب الرسوم والمبلغ للبائع
        $fee_percentage = 10.00; // 10%
        $fee_amount = (float)$deal['amount'] * ($fee_percentage / 100);
        $seller_amount = (float)$deal['amount'] - $fee_amount;
        
        // تحديث رصيد البائع - إضافة للرصيد الأساسي وحذف من الرصيد المعلق
        $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ?, pending_balance = GREATEST(0, pending_balance - ?) WHERE user_id = ?');
        $stmt->execute([$seller_amount, $seller_amount, $deal['seller_id']]);
        
        // خصم الرصيد المعلق من المشتري (المبلغ الكامل للصفقة)
        $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = GREATEST(0, pending_balance - ?) WHERE user_id = ?');
        $stmt->execute([$deal['amount'], $deal['buyer_id']]);
        
        // إضافة الرسوم إلى رصيد النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" OR role = "admin" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        
        if ($system_user) {
            $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
            $stmt->execute([$fee_amount, $system_user['id']]);
        }
        
        // تحديث حالة الصفقة مع حفظ تفاصيل الرسوم
        $stmt = $pdo->prepare('UPDATE deals SET status = "COMPLETED", escrow_status = "RELEASED", platform_fee = ?, seller_amount = ?, fee_percentage = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$fee_amount, $seller_amount, $fee_percentage, $deal_id]);
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;
        
        // تسجيل المعاملة في سجل المعاملات المالية (المبلغ للبائع)
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description, created_at) VALUES (?, "RELEASE", ?, ?, ?, "تحرير أموال الصفقة للبائع بواسطة الإدارة (بدون تأكيد المشتري)", NOW())');
        $stmt->execute([$deal_id, $seller_amount, $system_user_id, $deal['seller_id']]);
        
        // تسجيل رسوم المنصة
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description, created_at) VALUES (?, "FEE", ?, ?, ?, "رسوم المنصة 10% - تحرير إداري", NOW())');
        $stmt->execute([$deal_id, $fee_amount, $deal['seller_id'], $system_user_id]);
        
        // حذف الحساب المرتبط بالصفقة بعد تحرير الأموال
        if ($deal['account_id']) {
            // حذف الصور المرتبطة بالحساب
            $stmtImgs = $pdo->prepare('SELECT image_path FROM account_images WHERE account_id = ?');
            $stmtImgs->execute([$deal['account_id']]);
            $images = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);
            $uploadsDir = realpath(__DIR__ . '/../uploads');
            foreach ($images as $imgPath) {
                if (!$imgPath) continue;
                $candidate = realpath(__DIR__ . '/../' . ltrim($imgPath, '/\\')) ?: realpath($imgPath);
                if ($candidate && $uploadsDir && strpos($candidate, $uploadsDir) === 0) {
                    if (is_file($candidate)) {
                        @unlink($candidate);
                    }
                }
            }
            
            // حذف سجلات الصور من قاعدة البيانات
            $pdo->prepare('DELETE FROM account_images WHERE account_id = ?')->execute([$deal['account_id']]);
            // حذف الحساب
            $pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$deal['account_id']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
             'success' => true, 
             'message' => 'تم تحرير الأموال للبائع بنجاح وحذف الحساب من الموقع',
             'seller_amount' => $seller_amount,
             'fee_amount' => $fee_amount
         ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function getDealByConversation() {
    global $pdo;
    
    $conversation_id = $_GET['conversation_id'] ?? '';
    if (!$conversation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف المحادثة مطلوب']);
        return;
    }
    
    try {
        // البحث عن الصفقة المرتبطة بالمحادثة
        $stmt = $pdo->prepare('
            SELECT d.*, 
                   buyer.name as buyer_name, buyer.phone as buyer_phone,
                   seller.name as seller_name, seller.phone as seller_phone,
                   a.game_name, "" as account_description
            FROM deals d
            JOIN users buyer ON d.buyer_id = buyer.id
            JOIN users seller ON d.seller_id = seller.id
            LEFT JOIN accounts a ON d.account_id = a.id
            WHERE d.conversation_id = ?
            ORDER BY d.created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$conversation_id]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deal) {
            echo json_encode(['success' => false, 'error' => 'لا توجد صفقة مرتبطة بهذه المحادثة']);
            return;
        }
        
        echo json_encode(['success' => true, 'deal' => $deal]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}

function rejectCancelRequest() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['deal_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }

    $deal_id = intval($input['deal_id']);
    $admin_note = $input['admin_note'] ?? 'تم رفض طلب الإلغاء من قبل الإدارة';

    try {
        $pdo->beginTransaction();

        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? AND status = "PENDING_CANCEL" FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();

        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو ليست في حالة انتظار إلغاء']);
            return;
        }

        // إرجاع الصفقة لحالتها السابقة (FUNDED)
        $stmt = $pdo->prepare('UPDATE deals SET status = "FUNDED", cancel_reason = NULL, cancel_requested_by = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$deal_id]);

        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;

        // إضافة رسالة في المحادثة
        $message = "⚠️ تم رفض طلب إلغاء الصفقة من قبل الإدارة.\n\n{$admin_note}\n\nالصفقة الآن في حالة نشطة ويجب إكمالها.";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]);

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'تم رفض طلب الإلغاء وإرجاع الصفقة للحالة النشطة']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
}
?>

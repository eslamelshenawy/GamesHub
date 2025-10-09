<?php
// ملف تحديث نظام البلاغات في قاعدة البيانات
// يجب تشغيل هذا الملف مرة واحدة فقط لتحديث قاعدة البيانات

require_once 'db.php';

try {
    // بدء المعاملة
    $pdo->beginTransaction();
    
    echo "بدء تحديث نظام البلاغات...\n";
    
    // 1. التأكد من وجود جدول reports وتحديثه إذا لزم الأمر
    $checkReportsTable = $pdo->query("SHOW TABLES LIKE 'reports'");
    if ($checkReportsTable->rowCount() == 0) {
        // إنشاء جدول البلاغات إذا لم يكن موجوداً
        $createReportsTable = "
        CREATE TABLE `reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `reporter_id` int(11) NOT NULL COMMENT 'معرف المبلغ',
            `reported_user_id` int(11) NOT NULL COMMENT 'معرف المبلغ عنه',
            `conversation_id` int(11) DEFAULT NULL COMMENT 'معرف المحادثة المبلغ عنها',
            `reason` text NOT NULL COMMENT 'سبب البلاغ',
            `status` enum('pending','under_review','resolved','dismissed') DEFAULT 'pending' COMMENT 'حالة البلاغ',
            `admin_conversation_id` int(11) DEFAULT NULL COMMENT 'معرف محادثة البلاغ مع الأدمن',
            `admin_notes` text DEFAULT NULL COMMENT 'ملاحظات الأدمن',
            `reviewed_by` int(11) DEFAULT NULL COMMENT 'معرف الأدمن الذي راجع البلاغ',
            `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ مراجعة البلاغ',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إنشاء البلاغ',
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
            PRIMARY KEY (`id`),
            KEY `idx_reporter_id` (`reporter_id`),
            KEY `idx_reported_user_id` (`reported_user_id`),
            KEY `idx_conversation_id` (`conversation_id`),
            KEY `idx_admin_conversation_id` (`admin_conversation_id`),
            KEY `idx_status` (`status`),
            KEY `idx_reviewed_by` (`reviewed_by`),
            FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reported_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`admin_conversation_id`) REFERENCES `conversations`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول البلاغات';
        ";
        
        $pdo->exec($createReportsTable);
        echo "تم إنشاء جدول البلاغات بنجاح.\n";
    } else {
        echo "جدول البلاغات موجود بالفعل.\n";
        
        // التحقق من وجود الأعمدة المطلوبة وإضافتها إذا لم تكن موجودة
        $columns = $pdo->query("DESCRIBE reports")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('admin_conversation_id', $columns)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN admin_conversation_id int(11) DEFAULT NULL COMMENT 'معرف محادثة البلاغ مع الأدمن' AFTER status");
            echo "تم إضافة عمود admin_conversation_id.\n";
        }
        
        if (!in_array('admin_notes', $columns)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN admin_notes text DEFAULT NULL COMMENT 'ملاحظات الأدمن' AFTER admin_conversation_id");
            echo "تم إضافة عمود admin_notes.\n";
        }
        
        if (!in_array('reviewed_by', $columns)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_by int(11) DEFAULT NULL COMMENT 'معرف الأدمن الذي راجع البلاغ' AFTER admin_notes");
            echo "تم إضافة عمود reviewed_by.\n";
        }
        
        if (!in_array('reviewed_at', $columns)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_at timestamp NULL DEFAULT NULL COMMENT 'تاريخ مراجعة البلاغ' AFTER reviewed_by");
            echo "تم إضافة عمود reviewed_at.\n";
        }
    }
    
    // 2. التأكد من تحديث جدول conversations لدعم أنواع المحادثات المختلفة
    $checkConversationsColumns = $pdo->query("DESCRIBE conversations")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('conversation_type', $checkConversationsColumns)) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN conversation_type enum('normal','report','admin_support') DEFAULT 'normal' COMMENT 'نوع المحادثة' AFTER last_message_at");
        echo "تم إضافة عمود conversation_type إلى جدول conversations.\n";
    }
    
    if (!in_array('related_report_id', $checkConversationsColumns)) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN related_report_id int(11) DEFAULT NULL COMMENT 'معرف البلاغ المرتبط' AFTER conversation_type");
        echo "تم إضافة عمود related_report_id إلى جدول conversations.\n";
    }
    
    // 3. إنشاء فهارس لتحسين الأداء
    try {
        $pdo->exec("CREATE INDEX idx_conversation_type ON conversations(conversation_type)");
        echo "تم إنشاء فهرس conversation_type.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "فهرس conversation_type موجود بالفعل.\n";
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_related_report_id ON conversations(related_report_id)");
        echo "تم إنشاء فهرس related_report_id.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "فهرس related_report_id موجود بالفعل.\n";
    }
    
    // 4. إنشاء view لعرض البلاغات مع تفاصيل المستخدمين
    $createReportsView = "
    CREATE OR REPLACE VIEW reports_with_users AS
    SELECT 
        r.*,
        reporter.name as reporter_name,
        reporter.phone as reporter_phone,
        reported.name as reported_user_name,
        reported.phone as reported_user_phone,
        admin_user.name as admin_name,
        c.user1_id as conversation_user1,
        c.user2_id as conversation_user2
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported ON r.reported_user_id = reported.id
    LEFT JOIN users admin_user ON r.reviewed_by = admin_user.id
    LEFT JOIN conversations c ON r.conversation_id = c.id
    ";
    
    $pdo->exec($createReportsView);
    echo "تم إنشاء view البلاغات مع تفاصيل المستخدمين.\n";
    
    // تأكيد المعاملة إذا كانت نشطة
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    echo "تم تحديث نظام البلاغات بنجاح!\n";
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ إذا كانت نشطة
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "خطأ في تحديث نظام البلاغات: " . $e->getMessage() . "\n";
    exit(1);
}
?>
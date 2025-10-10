-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 14, 2025 at 07:43 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `games_accounts`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,0) NOT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `user_id`, `game_name`, `description`, `price`, `created_at`) VALUES
(69, 44, 'Pubg Mobile', 'حساب ببجي للبيع', 300, '2025-09-05'),
(70, 44, 'Call Of Duty', 'حساب كول اوف ديوتي للبيع', 1000, '2025-09-05'),
(71, 44, 'Free Fire', 'حساب فري فاير للبيع', 1200, '2025-09-05');

-- --------------------------------------------------------

--
-- Table structure for table `account_images`
--

CREATE TABLE `account_images` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `image_path` varchar(250) NOT NULL,
  `uploaded_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_images`
--

INSERT INTO `account_images` (`id`, `account_id`, `image_path`, `uploaded_at`) VALUES
(87, 69, 'uploads/img_68bb3d2f755e5_images (1).jpeg', '2025-09-05'),
(88, 70, 'uploads/img_68bb3d72aa7e0_header.jpg', '2025-09-05'),
(89, 71, 'uploads/img_68bb3dc7d9590_images (2).jpeg', '2025-09-05');

-- --------------------------------------------------------

--
-- Table structure for table `admin_chats`
--

CREATE TABLE `admin_chats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_chat_messages`
--

CREATE TABLE `admin_chat_messages` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_type` enum('admin','user') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_reviews`
--

CREATE TABLE `admin_reviews` (
  `id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL COMMENT 'معرف الإداري المراجع',
  `review_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `messages_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'نسخة من الرسائل وقت المراجعة' CHECK (json_valid(`messages_snapshot`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversation_type` enum('normal','report','admin_support') DEFAULT 'normal' COMMENT 'نوع المحادثة',
  `related_report_id` int(11) DEFAULT NULL COMMENT 'معرف البلاغ المرتبط'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user1_id`, `user2_id`, `last_message_at`, `conversation_type`, `related_report_id`) VALUES
(5, 43, 44, '2025-09-05 19:56:09', 'normal', NULL),
(6, 43, 38, '2025-09-05 19:57:22', 'normal', NULL),
(7, 44, 38, '2025-09-05 19:58:04', 'normal', NULL),
(8, 45, 44, '2025-09-13 17:55:31', 'normal', NULL),
(9, 46, 44, '2025-09-13 18:00:30', 'normal', NULL),
(10, 47, 44, '2025-09-13 18:11:22', 'normal', NULL),
(11, 48, 44, '2025-09-14 04:12:32', 'normal', NULL),
(12, 48, 35, '2025-09-14 05:32:32', 'admin_support', 1),
(13, 48, 45, '2025-09-14 05:39:36', 'normal', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `deals`
--

CREATE TABLE `deals` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `details` text NOT NULL,
  `status` enum('CREATED','FUNDED','ON_HOLD','DELIVERED','BUYER_CONFIRMED','ADMIN_REVIEW','COMPLETED','CANCELLED','DISPUTED','REFUNDED') DEFAULT 'CREATED',
  `escrow_amount` decimal(10,2) DEFAULT 0.00,
  `escrow_status` enum('PENDING','FUNDED','RELEASED','REFUNDED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_id` int(11) DEFAULT NULL COMMENT 'معرف الحساب المباع',
  `conversation_id` int(11) DEFAULT NULL COMMENT 'معرف المحادثة المرتبطة',
  `buyer_confirmed_at` timestamp NULL DEFAULT NULL COMMENT 'وقت تأكيد المشتري للاستلام',
  `admin_review_status` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'حالة المراجعة الإدارية',
  `admin_reviewed_by` int(11) DEFAULT NULL COMMENT 'معرف الإداري الذي راجع الصفقة',
  `admin_reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'وقت المراجعة الإدارية',
  `admin_notes` text DEFAULT NULL COMMENT 'ملاحظات الإدارة',
  `platform_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'رسوم المنصة',
  `seller_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ المحول للبائع بعد خصم الرسوم',
  `fee_percentage` decimal(5,2) DEFAULT 10.00 COMMENT 'نسبة الرسوم المئوية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `deals`
--

INSERT INTO `deals` (`id`, `buyer_id`, `seller_id`, `amount`, `details`, `status`, `escrow_amount`, `escrow_status`, `created_at`, `updated_at`, `account_id`, `conversation_id`, `buyer_confirmed_at`, `admin_review_status`, `admin_reviewed_by`, `admin_reviewed_at`, `admin_notes`, `platform_fee`, `seller_amount`, `fee_percentage`) VALUES
(43, 47, 44, 300.00, 'صفقة شراء حساب: Pubg Mobile - حساب ببجي للبيع', 'COMPLETED', 0.00, 'RELEASED', '2025-09-13 18:12:23', '2025-09-13 18:21:50', 69, 10, NULL, 'pending', NULL, NULL, NULL, 30.00, 270.00, 10.00),
(44, 37, 36, 100.00, 'صفقة تجريبية للاختبار', 'CANCELLED', 0.00, 'REFUNDED', '2025-09-13 18:17:28', '2025-09-13 18:42:10', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 0.00, 0.00, 10.00),
(45, 37, 36, 100.00, 'صفقة تجريبية للاختبار المباشر', 'COMPLETED', 0.00, 'RELEASED', '2025-09-13 18:18:02', '2025-09-13 18:41:56', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 10.00, 90.00, 10.00),
(46, 37, 36, 100.00, 'اختبار الأعمدة الجديدة', 'CREATED', 0.00, 'PENDING', '2025-09-13 18:18:56', '2025-09-13 18:18:56', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 10.00, 90.00, 10.00);

-- --------------------------------------------------------

--
-- Stand-in structure for view `deals_with_users`
-- (See below for the actual view)
--
CREATE TABLE `deals_with_users` (
`id` int(11)
,`buyer_id` int(11)
,`seller_id` int(11)
,`amount` decimal(10,2)
,`details` text
,`status` enum('CREATED','FUNDED','ON_HOLD','DELIVERED','BUYER_CONFIRMED','ADMIN_REVIEW','COMPLETED','CANCELLED','DISPUTED','REFUNDED')
,`escrow_amount` decimal(10,2)
,`escrow_status` enum('PENDING','FUNDED','RELEASED','REFUNDED')
,`created_at` timestamp
,`updated_at` timestamp
,`account_id` int(11)
,`conversation_id` int(11)
,`buyer_confirmed_at` timestamp
,`admin_review_status` enum('pending','approved','rejected')
,`admin_reviewed_by` int(11)
,`admin_reviewed_at` timestamp
,`admin_notes` text
,`buyer_name` varchar(90)
,`buyer_phone` varchar(50)
,`seller_name` varchar(90)
,`seller_phone` varchar(50)
,`game_name` varchar(100)
,`account_description` text
);

-- --------------------------------------------------------

--
-- Table structure for table `deal_status_history`
--

CREATE TABLE `deal_status_history` (
  `id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `change_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `raised_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('OPEN','RESOLVED','CLOSED') DEFAULT 'OPEN',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_transactions`
--

CREATE TABLE `escrow_transactions` (
  `transaction_id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('PENDING','FUNDED','RELEASED','REFUNDED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_logs`
--

CREATE TABLE `financial_logs` (
  `id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `type` enum('ESCROW','WITHDRAW','DEPOSIT','REFUND','fee') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `financial_logs`
--

INSERT INTO `financial_logs` (`id`, `deal_id`, `type`, `amount`, `from_user`, `to_user`, `description`, `created_at`) VALUES
(108, 43, 'ESCROW', 300.00, 47, 44, NULL, '2025-09-13 18:12:23'),
(109, 43, 'DEPOSIT', 270.00, 38, 44, 'تحويل مبلغ الصفقة للبائع بعد خصم رسوم المنصة', '2025-09-13 18:21:50'),
(110, 43, 'fee', 30.00, 44, 38, 'رسوم المنصة 10%', '2025-09-13 18:21:50'),
(113, 45, 'DEPOSIT', 90.00, 38, 36, 'تحويل مبلغ الصفقة للبائع بعد خصم رسوم المنصة', '2025-09-13 18:41:56'),
(114, 45, 'fee', 10.00, 36, 38, 'رسوم المنصة 10%', '2025-09-13 18:41:56'),
(115, 44, 'REFUND', 100.00, 38, 37, NULL, '2025-09-13 18:42:10');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `deal_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `created_at`, `is_read`, `deal_id`) VALUES
(258, 43, 44, 'مرحباً! أنا مهتم بحساب Free Fire', '2025-09-05 22:56:09', 1, NULL),
(259, 44, 43, 'hello', '2025-09-05 22:56:24', 1, NULL),
(268, 45, 44, 'مرحباً! أنا مهتم بحساب Free Fire', '2025-09-13 20:55:31', 0, NULL),
(269, 46, 44, 'مرحباً! أنا مهتم بحساب Free Fire', '2025-09-13 21:00:30', 0, NULL),
(272, 47, 44, 'مرحباً! أنا مهتم بحساب Pubg Mobile', '2025-09-13 21:11:22', 0, NULL),
(273, 47, 44, '🔒 تم بدء صفقة آمنة جديدة\n💰 المبلغ: 300 ج.م\n🎮 الحساب: Pubg Mobile\n📋 رقم الصفقة: #43\n⏳ في انتظار تسليم الحساب من البائع', '2025-09-13 21:12:23', 0, 43),
(274, 47, 47, 'تم تمويل الصفقة من قبل Mohamed Hamed. الصفقة الآن في حالة FUNDED.', '2025-09-13 21:12:32', 0, 43),
(275, 47, 44, 'تم تمويل الصفقة من قبل Mohamed Hamed. الصفقة الآن في حالة FUNDED.', '2025-09-13 21:12:32', 0, 43),
(276, 38, 47, 'تم اعتماد الصفقة من قبل الإدارة. تم تحويل المبلغ 270 جنيه إلى البائع (بعد خصم رسوم المنصة 10% = 30 جنيه).', '2025-09-13 21:21:50', 0, 43),
(277, 38, 44, 'تم اعتماد الصفقة من قبل الإدارة. تم تحويل المبلغ 270 جنيه إلى البائع (بعد خصم رسوم المنصة 10% = 30 جنيه).', '2025-09-13 21:21:50', 0, 43),
(278, 38, 37, 'تم اعتماد الصفقة من قبل الإدارة. تم تحويل المبلغ 90 جنيه إلى البائع (بعد خصم رسوم المنصة 10% = 10 جنيه).', '2025-09-13 21:41:56', 0, 45),
(279, 38, 36, 'تم اعتماد الصفقة من قبل الإدارة. تم تحويل المبلغ 90 جنيه إلى البائع (بعد خصم رسوم المنصة 10% = 10 جنيه).', '2025-09-13 21:41:56', 0, 45),
(280, 38, 37, 'تم رفض الصفقة من قبل الإدارة. السبب: تا. تم إرجاع المبلغ 100.00 جنيه إلى المشتري.', '2025-09-13 21:42:10', 0, 44),
(281, 38, 36, 'تم رفض الصفقة من قبل الإدارة. السبب: تا. تم إرجاع المبلغ 100.00 جنيه إلى المشتري.', '2025-09-13 21:42:10', 0, 44),
(282, 48, 44, 'مرحباً! أنا مهتم بحساب Free Fire', '2025-09-14 07:12:32', 0, NULL),
(283, 35, 48, 'مرحباً، تم استلام بلاغك بنجاح.\n\nرقم البلاغ: #1\nالمبلغ عنه: mmmm\nسبب البلاغ: نصاب نصاب نصاب\n\nسيتم مراجعة بلاغك من قبل فريق الإدارة وسنتواصل معك قريباً.', '2025-09-14 08:08:20', 1, NULL),
(284, 45, 48, 'تم حل بلاغك بنجاح. شكراً لك على التبليغ.', '2025-09-14 08:32:22', 1, NULL),
(285, 45, 48, 'تم حل بلاغك بنجاح. شكراً لك على التبليغ.', '2025-09-14 08:32:23', 1, NULL),
(286, 45, 48, 'تم رفض بلاغك بعد المراجعة.', '2025-09-14 08:32:30', 1, NULL),
(287, 45, 48, 'تم رفض بلاغك بعد المراجعة.', '2025-09-14 08:32:32', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `from_id`, `to_id`, `amount`, `payment_method`, `created_at`) VALUES
(1, 36, 37, 3500.00, 'istapay', '2025-09-02 03:05:23'),
(2, 36, 37, 850.00, 'istapay', '2025-09-02 03:05:47'),
(3, 36, 37, 850.00, 'vodafone_cash', '2025-09-02 03:08:02'),
(4, 37, 36, 600.00, 'istapay', '2025-09-02 08:14:32'),
(5, 36, 37, 6000.00, 'istapay', '2025-09-02 08:15:41'),
(6, 37, 36, 11999.00, 'istapay', '2025-09-02 08:17:29'),
(7, 36, 37, 50.00, 'istapay', '2025-09-02 08:18:25'),
(8, 37, 36, 50.00, 'istapay', '2025-09-02 08:21:45'),
(9, 36, 37, 50.00, 'istapay', '2025-09-02 08:24:48'),
(10, 36, 37, 50.00, 'istapay', '2025-09-02 08:25:41'),
(11, 37, 36, 50.00, 'istapay', '2025-09-02 08:27:15'),
(12, 36, 37, 300.00, 'istapay', '2025-09-02 08:31:26'),
(13, 37, 36, 300.00, 'istapay', '2025-09-02 08:36:38'),
(14, 36, 37, 300.00, 'istapay', '2025-09-02 08:39:25'),
(15, 37, 36, 30.00, 'istapay', '2025-09-02 08:48:24'),
(16, 37, 36, 10.00, 'istapay', '2025-09-02 13:48:50'),
(17, 37, 36, 20.00, 'istapay', '2025-09-02 13:50:56'),
(18, 37, 36, 30.00, 'istapay', '2025-09-02 13:54:07'),
(19, 37, 36, 60.00, 'istapay', '2025-09-02 14:40:12'),
(20, 36, 35, 39000.00, 'istapay', '2025-09-02 14:40:36'),
(21, 37, 36, 100.00, 'istapay', '2025-09-02 14:52:51'),
(22, 37, 36, 100.00, 'istapay', '2025-09-02 14:53:06'),
(23, 36, 37, 100.00, 'istapay', '2025-09-02 14:53:42'),
(24, 37, 36, 10.00, 'istapay', '2025-09-02 15:23:40'),
(25, 36, 37, 100.00, 'istapay', '2025-09-02 15:45:55'),
(26, 37, 36, 10.00, 'istapay', '2025-09-02 15:59:13'),
(27, 37, 36, 10.00, 'istapay', '2025-09-02 16:00:10'),
(28, 37, 36, 10.00, 'istapay', '2025-09-02 17:57:45'),
(29, 37, 36, 10.00, 'istapay', '2025-09-02 18:06:10'),
(30, 37, 36, 10.00, 'istapay', '2025-09-02 18:41:16'),
(31, 37, 36, 10.00, 'istapay', '2025-09-02 18:46:07'),
(32, 37, 36, 10.00, 'istapay', '2025-09-02 18:56:27'),
(33, 36, 37, 30.00, 'istapay', '2025-09-02 19:02:50'),
(34, 36, 37, 200.00, 'istapay', '2025-09-02 19:06:58'),
(35, 36, 37, 100.00, 'istapay', '2025-09-02 20:22:16'),
(36, 36, 37, 200.00, 'istapay', '2025-09-03 18:17:18'),
(37, 37, 36, 200.00, 'istapay', '2025-09-03 18:20:48'),
(38, 36, 37, 100.00, 'istapay', '2025-09-03 18:22:12'),
(39, 37, 36, 100.00, 'istapay', '2025-09-03 18:34:52'),
(40, 36, 37, 200.00, 'istapay', '2025-09-03 19:04:53'),
(41, 36, 37, 10.00, 'istapay', '2025-09-03 19:26:35'),
(42, 37, 36, 10.00, 'istapay', '2025-09-03 19:44:51'),
(43, 37, 36, 200.00, 'istapay', '2025-09-03 19:46:05'),
(44, 37, 36, 100.00, 'istapay', '2025-09-03 19:52:27'),
(45, 37, 36, 50.00, 'istapay', '2025-09-03 20:15:58'),
(46, 36, 37, 50.00, 'istapay', '2025-09-03 20:25:19'),
(47, 36, 37, 10.00, 'istapay', '2025-09-03 21:00:31'),
(48, 37, 36, 10.00, 'istapay', '2025-09-03 21:02:29'),
(49, 37, 36, 10.00, 'istapay', '2025-09-03 21:36:19'),
(50, 36, 37, 30.00, 'istapay', '2025-09-03 22:47:05'),
(51, 37, 36, 10.00, 'istapay', '2025-09-03 23:55:56'),
(52, 37, 36, 10.00, 'istapay', '2025-09-04 01:55:55'),
(53, 37, 39, 10.00, 'istapay', '2025-09-04 08:18:33'),
(54, 37, 39, 190.00, 'istapay', '2025-09-04 08:18:53'),
(55, 39, 37, 20.00, 'istapay', '2025-09-04 16:37:09'),
(56, 39, 35, 100.00, 'istapay', '2025-09-04 17:28:25'),
(57, 41, 41, 200.00, 'vodafone_cash', '2025-09-04 20:20:24'),
(58, 44, 41, 1000.00, 'vodafone_cash', '2025-09-05 01:57:01');

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_admin_reviews`
-- (See below for the actual view)
--
CREATE TABLE `pending_admin_reviews` (
`id` int(11)
,`buyer_id` int(11)
,`seller_id` int(11)
,`amount` decimal(10,2)
,`details` text
,`status` enum('CREATED','FUNDED','ON_HOLD','DELIVERED','BUYER_CONFIRMED','ADMIN_REVIEW','COMPLETED','CANCELLED','DISPUTED','REFUNDED')
,`escrow_amount` decimal(10,2)
,`escrow_status` enum('PENDING','FUNDED','RELEASED','REFUNDED')
,`created_at` timestamp
,`updated_at` timestamp
,`account_id` int(11)
,`conversation_id` int(11)
,`buyer_confirmed_at` timestamp
,`admin_review_status` enum('pending','approved','rejected')
,`admin_reviewed_by` int(11)
,`admin_reviewed_at` timestamp
,`admin_notes` text
,`buyer_name` varchar(90)
,`seller_name` varchar(90)
,`game_name` varchar(100)
,`review_id` int(11)
,`review_requested_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `pending_balance_transactions`
--

CREATE TABLE `pending_balance_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` enum('hold','release','refund') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL COMMENT 'معرف المبلغ',
  `reported_user_id` int(11) NOT NULL COMMENT 'معرف المبلغ عنه',
  `conversation_id` int(11) DEFAULT NULL,
  `reason` text NOT NULL COMMENT 'سبب البلاغ',
  `status` enum('pending','under_review','resolved','dismissed') DEFAULT 'pending' COMMENT 'حالة البلاغ',
  `admin_conversation_id` int(11) DEFAULT NULL COMMENT 'معرف محادثة البلاغ مع الأدمن',
  `admin_notes` text DEFAULT NULL COMMENT 'ملاحظات الأدمن',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'معرف الأدمن الذي راجع البلاغ',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ مراجعة البلاغ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إنشاء البلاغ',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول البلاغات';

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `reported_user_id`, `conversation_id`, `reason`, `status`, `admin_conversation_id`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 48, 44, 11, 'نصاب نصاب نصاب', 'dismissed', 12, '', 45, '2025-09-14 05:32:32', '2025-09-14 05:08:20', '2025-09-14 05:32:32');

-- --------------------------------------------------------

--
-- Stand-in structure for view `reports_with_users`
-- (See below for the actual view)
--
CREATE TABLE `reports_with_users` (
`id` int(11)
,`reporter_id` int(11)
,`reported_user_id` int(11)
,`conversation_id` int(11)
,`reason` text
,`status` enum('pending','under_review','resolved','dismissed')
,`admin_conversation_id` int(11)
,`admin_notes` text
,`reviewed_by` int(11)
,`reviewed_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`reporter_name` varchar(90)
,`reporter_phone` varchar(50)
,`reported_user_name` varchar(90)
,`reported_user_phone` varchar(50)
,`admin_name` varchar(90)
,`conversation_user1` int(11)
,`conversation_user2` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `suggestions`
--

CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(90) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` int(11) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(32) DEFAULT 'buyer',
  `balance` decimal(10,2) DEFAULT 0.00,
  `wallet_balance` decimal(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `typing_to` int(11) DEFAULT NULL,
  `pending_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد المعلق في الضمان',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `password`, `age`, `gender`, `phone`, `email`, `role`, `balance`, `wallet_balance`, `avatar`, `bio`, `image`, `is_online`, `typing_to`, `pending_balance`, `created_at`) VALUES
(35, 'محمد الاختبار الاول', '$2y$10$nkkSc37keNUPDE0U4F7kierP3Ls5f7QwraKtBqMDEhUsSlTTl4Wai', 12, 'ذكر', '123456789', NULL, 'admin', 0.00, 0.00, NULL, 'انا ادمن', 'uploads/avatar_68b372f62b00f_Screenshot 2025-08-29 020330.png', 1, NULL, 0.00, '2025-09-13 18:43:30'),
(36, 'احمد حسين السيد', '$2y$10$0nHku5yI8kWBqWhPisDUMeuo1mdfUaIQxsUhbiqrOprUDIdEMT/gC', 45, '', '0123456789', NULL, 'seller', 0.00, 0.00, NULL, '', 'uploads/avatar_68b381b0361a5_Screenshot 2025-08-30 023835.png', 0, NULL, 0.00, '2025-09-13 18:43:30'),
(37, 'mohamed salah', '$2y$10$U0DbiqFLuSmMFKDY/c/3ieXTsCEmIR.POYdH/hk.gqy3I8VxIRsAC', 12, 'ذكر', '012345678', NULL, 'buyer', 0.00, 0.00, NULL, 'البايو', 'uploads/avatar_68b8d50687b5f_Screenshot 2025-09-03 233623.png', 0, NULL, 0.00, '2025-09-13 18:43:30'),
(38, 'site_wallet', '', 0, '', '', NULL, 'system', 0.00, 0.00, NULL, NULL, NULL, 0, NULL, 0.00, '2025-09-13 18:43:30'),
(39, 'عبدالله عدلي', '$2y$10$VssJPoTtmbe1i5EOsAkCSuYaFTcTQx/OWM3Wswdoo4k3KgtmNTVzS', 6, 'male', '01556059182', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 0, NULL, 0.00, '2025-09-13 18:43:30'),
(40, 'essa', '$2y$10$2Xpmed.9xftyc5dEejbT8.wJaZczn4iy1twvBaC0u8aKMwDVzyYVq', 1, 'male', '01144874785', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(41, 'mohamed_1st', '$2y$12$Vhq1YMMDPQl2NXZY6PLGoeBbtT1FaeCuLKAeTP/CmYKvGKcq/5aQK', 20, 'male', '01062532581', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(42, 'Admin', 'e11170b8cbd2d74102651cb967fa28e5', 25, 'male', '1111111111', NULL, 'admin', 0.00, 0.00, NULL, NULL, NULL, 0, NULL, 0.00, '2025-09-13 18:43:30'),
(43, 'mmm', '$2y$12$XCsTlfXIdGFwVhDX/pkrJOYWuf0LHDX4xVxiErKagwdA0F8Yba4x2', 20, 'male', '01000000000', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(44, 'mmmm', '$2y$12$4/zxJZwG3IgNVy.RTyeTeeOKUYFRaDr1x2h2r0v0tX20buBBPrIDO', 20, 'male', '01000000001', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(45, 'mmmm', '$2y$12$LbIphgRVh2MNODUK6aQmtOJWeLm1Ld.RAhWXi3ys6/mWsdiI4aEQy', 11, 'male', '22222222222', 'admin@gmail.com', 'admin', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(46, '3333', '$2y$12$gdR.MgzqtlvNPHzmxzPEvO15JAj870rSskQ/Z.TRCaPXfueNAKoIG', 11, 'male', '33333333333', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(47, 'Mohamed Hamed', '$2y$12$4FUb8KH/5QnP04ATGlrm5eNGAw3s1oNbITupo/CdEpZb.hcTEHmIO', 13, 'male', '00000000000', NULL, 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-13 18:43:30'),
(48, 'jkhfd', '$2y$12$loxwdHCJOtSqde80ONCNQueQHnNOAolkNynxjtT/ftNoNtvQFh.O6', 11, 'male', '12111111111', 'd@gmail.com', 'buyer', 0.00, 0.00, NULL, NULL, NULL, 1, NULL, 0.00, '2025-09-14 04:12:12');

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pending_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `pending_balance`, `updated_at`) VALUES
(10, 35, 39640.00, 0.00, '2025-09-13 21:41:56'),
(11, 36, 30490.00, 0.00, '2025-09-13 21:41:56'),
(12, 37, 120.00, -100.00, '2025-09-13 21:42:10'),
(13, 39, 380.00, 0.00, '2025-09-04 17:31:48'),
(16, 41, 36500.00, 0.00, '2025-09-05 22:27:32'),
(17, 42, 0.00, 0.00, '2025-09-04 21:26:45'),
(18, 44, 7870.00, 0.00, '2025-09-13 21:21:50'),
(20, 43, 8800.00, 0.00, '2025-09-05 22:57:54'),
(23, 46, 98500.00, 1500.00, '2025-09-13 21:04:02'),
(24, 47, 99700.00, 0.00, '2025-09-13 21:21:50'),
(25, 37, 100.00, -100.00, '2025-09-13 21:42:10');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_topups`
--

CREATE TABLE `wallet_topups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `receipt` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `wallet_topups`
--

INSERT INTO `wallet_topups` (`id`, `user_id`, `method`, `amount`, `phone`, `receipt`, `status`, `created_at`, `reviewed_at`) VALUES
(36, 43, 'vodafone_cash', 10000.00, '01062532581', '', 'approved', '2025-09-05 22:55:43', '2025-09-05 22:55:49'),
(37, 46, 'vodafone_cash', 100000.00, '010625324324', '', 'approved', '2025-09-13 21:00:55', '2025-09-13 21:01:06'),
(38, 47, 'vodafone_cash', 100000.00, '1000000000', '', 'approved', '2025-09-13 21:10:45', '2025-09-13 21:11:00');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` enum('deposit','withdraw','purchase','refund','escrow_hold','escrow_release','fee') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deal_id` int(11) DEFAULT NULL COMMENT 'معرف الصفقة المرتبطة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `amount`, `type`, `description`, `created_at`, `deal_id`) VALUES
(19, 37, 800.00, '', 'شحن عبر الإدارة', '2025-08-31 19:00:41', NULL),
(20, 35, 2000.00, '', 'شحن عبر الإدارة', '2025-08-31 19:05:20', NULL),
(21, 36, 300.00, '', 'شحن عبر الإدارة', '2025-09-01 00:55:47', NULL),
(22, 35, 500.00, '', 'شحن عبر الإدارة', '2025-09-01 16:44:43', NULL),
(23, 36, 50000.00, '', 'شحن عبر الإدارة', '2025-09-01 19:41:53', NULL),
(24, 37, 2000.00, '', 'شحن عبر الإدارة', '2025-09-01 19:42:48', NULL),
(25, 39, 300.00, '', 'شحن عبر الإدارة', '2025-09-04 17:31:48', NULL),
(26, 41, 10000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-04 20:14:08', NULL),
(27, 41, 20000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-05 00:09:52', NULL),
(28, 44, 10000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-05 01:44:21', NULL),
(29, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:12:55', NULL),
(30, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:12:55', NULL),
(31, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:22:37', NULL),
(32, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:23:53', NULL),
(33, 41, 0.00, 'deposit', 'إتمام صفقة رقم 5', '2025-09-05 03:29:30', NULL),
(34, 41, 0.00, 'deposit', 'إتمام صفقة رقم 6', '2025-09-05 03:30:11', NULL),
(35, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:31:35', NULL),
(36, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:31:35', NULL),
(37, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:32:05', NULL),
(38, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:32:05', NULL),
(39, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:32:43', NULL),
(40, 41, 0.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:32:43', NULL),
(41, 41, 200.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:42:35', NULL),
(42, 41, 200.00, 'deposit', 'إتمام صفقة رقم 4', '2025-09-05 03:42:36', NULL),
(43, 41, 200.00, 'deposit', 'إتمام صفقة رقم 7', '2025-09-05 03:45:33', NULL),
(44, 41, 200.00, 'deposit', 'إتمام صفقة رقم 7', '2025-09-05 03:45:33', NULL),
(45, 44, 200.00, 'refund', 'استرداد صفقة رقم 4 - نصب', '2025-09-05 03:46:40', NULL),
(46, 44, 200.00, 'refund', 'استرداد صفقة رقم 4 - كدا وخلاص', '2025-09-05 03:48:14', NULL),
(47, 44, 200.00, 'refund', 'استرداد صفقة رقم 8 - كدا وخلاص', '2025-09-05 03:49:11', NULL),
(48, 44, 200.00, 'refund', 'استرداد صفقة رقم 4 - كدا وخلاص', '2025-09-05 03:55:08', NULL),
(49, 44, 200.00, 'refund', 'استرداد صفقة رقم 11 - ه', '2025-09-05 04:04:44', NULL),
(50, 44, 200.00, 'refund', 'استرداد صفقة رقم 12 - كدا وخلاص', '2025-09-05 04:10:28', NULL),
(51, 44, 200.00, 'refund', 'استرداد صفقة رقم 13 - اخر تست', '2025-09-05 04:11:14', NULL),
(52, 44, 200.00, 'refund', 'استرداد صفقة رقم 14 - اخررررر', '2025-09-05 04:12:50', NULL),
(53, 44, 200.00, 'refund', 'استرداد صفقة رقم 14 - اخررررر', '2025-09-05 04:12:50', NULL),
(54, 44, 200.00, 'refund', 'استرداد صفقة رقم 15 - ب', '2025-09-05 04:34:39', NULL),
(55, 44, 200.00, 'refund', 'استرداد صفقة رقم 15 - ب', '2025-09-05 04:34:39', NULL),
(56, 44, 200.00, 'refund', 'استرداد صفقة رقم 16 - m', '2025-09-05 05:03:31', NULL),
(57, 44, 200.00, 'refund', 'استرداد صفقة رقم 16 - m', '2025-09-05 05:03:31', NULL),
(58, 41, 200.00, 'deposit', 'إتمام صفقة رقم 17', '2025-09-05 05:06:30', NULL),
(59, 41, 200.00, 'deposit', 'إتمام صفقة رقم 17', '2025-09-05 05:06:30', NULL),
(60, 41, 200.00, 'deposit', 'إتمام صفقة رقم 18', '2025-09-05 05:15:43', NULL),
(61, 41, 200.00, 'deposit', 'إتمام صفقة رقم 18', '2025-09-05 05:15:43', NULL),
(62, 41, 200.00, 'deposit', 'إتمام صفقة رقم 19', '2025-09-05 05:18:42', NULL),
(63, 41, 200.00, 'deposit', 'إتمام صفقة رقم 19', '2025-09-05 05:18:42', NULL),
(64, 41, 200.00, 'deposit', 'إتمام صفقة رقم 20', '2025-09-05 05:29:29', NULL),
(65, 41, 200.00, 'deposit', 'إتمام صفقة رقم 20', '2025-09-05 05:29:29', NULL),
(66, 41, 200.00, 'deposit', 'إتمام صفقة رقم 21', '2025-09-05 05:35:55', NULL),
(67, 41, 200.00, 'deposit', 'إتمام صفقة رقم 22', '2025-09-05 05:38:32', NULL),
(68, 41, 200.00, 'deposit', 'إتمام صفقة رقم 22', '2025-09-05 05:38:32', NULL),
(69, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 23', '2025-09-05 05:45:14', NULL),
(70, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 23', '2025-09-05 05:45:14', NULL),
(71, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 24', '2025-09-05 14:43:26', NULL),
(72, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 24', '2025-09-05 14:43:26', NULL),
(73, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 25', '2025-09-05 14:43:52', NULL),
(74, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 26', '2025-09-05 14:44:39', NULL),
(75, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 27', '2025-09-05 16:49:06', NULL),
(76, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 28', '2025-09-05 18:39:52', NULL),
(77, 44, 200.00, 'refund', 'استرداد صفقة رقم 29 - kj', '2025-09-05 18:43:40', NULL),
(78, 44, 200.00, 'refund', 'استرداد صفقة رقم 30 - mnbm', '2025-09-05 18:45:33', NULL),
(79, 44, 200.00, 'refund', 'استرداد صفقة رقم 31 - m,nb', '2025-09-05 19:16:11', NULL),
(80, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 32', '2025-09-05 19:58:22', NULL),
(81, 44, 200.00, 'refund', 'استرداد صفقة رقم 34 - لم يتم تحديد السبب', '2025-09-05 20:36:32', NULL),
(82, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 33', '2025-09-05 20:46:36', NULL),
(83, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 35', '2025-09-05 20:57:22', NULL),
(84, 44, 200.00, 'refund', 'استرداد صفقة رقم 36 - لم يتم تحديد السبب', '2025-09-05 21:05:46', NULL),
(85, 44, 200.00, 'refund', 'استرداد صفقة رقم 36 - بيب', '2025-09-05 21:22:56', NULL),
(86, 44, 200.00, 'refund', 'استرداد صفقة رقم 37 - ..', '2025-09-05 22:26:10', NULL),
(87, 41, 200.00, 'deposit', 'تحويل من صفقة رقم 38', '2025-09-05 22:27:32', NULL),
(88, 43, 10000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-05 22:55:49', NULL),
(89, 44, 1200.00, 'deposit', 'تحويل من صفقة رقم 39', '2025-09-05 22:57:15', NULL),
(90, 43, 1000.00, 'refund', 'استرداد صفقة رقم 40 - لم يتم تحديد السبب', '2025-09-05 22:57:54', NULL),
(91, 46, 100000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-13 21:01:06', NULL),
(92, 47, 100000.00, 'deposit', 'شحن عبر الإدارة', '2025-09-13 21:11:00', NULL),
(93, 44, 270.00, 'deposit', 'تحويل من صفقة رقم 43 (بعد خصم رسوم 10%)', '2025-09-13 21:21:50', NULL),
(94, 38, 30.00, 'fee', 'رسوم منصة من صفقة رقم 43', '2025-09-13 21:21:50', NULL),
(95, 36, 90.00, 'deposit', 'تحويل من صفقة رقم 45 (بعد خصم رسوم 10%)', '2025-09-13 21:41:56', NULL),
(96, 38, 10.00, 'fee', 'رسوم منصة من صفقة رقم 45', '2025-09-13 21:41:56', NULL),
(97, 37, 100.00, 'refund', 'استرداد صفقة رقم 44 - تا', '2025-09-13 21:42:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `withdraw_requests`
--

CREATE TABLE `withdraw_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `method` varchar(32) NOT NULL DEFAULT 'vodafone_cash',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `admin_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_nopad_ci;

-- --------------------------------------------------------

--
-- Structure for view `deals_with_users`
--
DROP TABLE IF EXISTS `deals_with_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `deals_with_users`  AS SELECT `d`.`id` AS `id`, `d`.`buyer_id` AS `buyer_id`, `d`.`seller_id` AS `seller_id`, `d`.`amount` AS `amount`, `d`.`details` AS `details`, `d`.`status` AS `status`, `d`.`escrow_amount` AS `escrow_amount`, `d`.`escrow_status` AS `escrow_status`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, `d`.`account_id` AS `account_id`, `d`.`conversation_id` AS `conversation_id`, `d`.`buyer_confirmed_at` AS `buyer_confirmed_at`, `d`.`admin_review_status` AS `admin_review_status`, `d`.`admin_reviewed_by` AS `admin_reviewed_by`, `d`.`admin_reviewed_at` AS `admin_reviewed_at`, `d`.`admin_notes` AS `admin_notes`, `buyer`.`name` AS `buyer_name`, `buyer`.`phone` AS `buyer_phone`, `seller`.`name` AS `seller_name`, `seller`.`phone` AS `seller_phone`, `acc`.`game_name` AS `game_name`, `acc`.`description` AS `account_description` FROM (((`deals` `d` left join `users` `buyer` on(`d`.`buyer_id` = `buyer`.`id`)) left join `users` `seller` on(`d`.`seller_id` = `seller`.`id`)) left join `accounts` `acc` on(`d`.`account_id` = `acc`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `pending_admin_reviews`
--
DROP TABLE IF EXISTS `pending_admin_reviews`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_admin_reviews`  AS SELECT `d`.`id` AS `id`, `d`.`buyer_id` AS `buyer_id`, `d`.`seller_id` AS `seller_id`, `d`.`amount` AS `amount`, `d`.`details` AS `details`, `d`.`status` AS `status`, `d`.`escrow_amount` AS `escrow_amount`, `d`.`escrow_status` AS `escrow_status`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, `d`.`account_id` AS `account_id`, `d`.`conversation_id` AS `conversation_id`, `d`.`buyer_confirmed_at` AS `buyer_confirmed_at`, `d`.`admin_review_status` AS `admin_review_status`, `d`.`admin_reviewed_by` AS `admin_reviewed_by`, `d`.`admin_reviewed_at` AS `admin_reviewed_at`, `d`.`admin_notes` AS `admin_notes`, `buyer`.`name` AS `buyer_name`, `seller`.`name` AS `seller_name`, `acc`.`game_name` AS `game_name`, `ar`.`id` AS `review_id`, `ar`.`created_at` AS `review_requested_at` FROM ((((`deals` `d` left join `users` `buyer` on(`d`.`buyer_id` = `buyer`.`id`)) left join `users` `seller` on(`d`.`seller_id` = `seller`.`id`)) left join `accounts` `acc` on(`d`.`account_id` = `acc`.`id`)) left join `admin_reviews` `ar` on(`d`.`id` = `ar`.`deal_id`)) WHERE `d`.`status` = 'BUYER_CONFIRMED' AND `d`.`admin_review_status` = 'pending' ;

-- --------------------------------------------------------

--
-- Structure for view `reports_with_users`
--
DROP TABLE IF EXISTS `reports_with_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `reports_with_users`  AS SELECT `r`.`id` AS `id`, `r`.`reporter_id` AS `reporter_id`, `r`.`reported_user_id` AS `reported_user_id`, `r`.`conversation_id` AS `conversation_id`, `r`.`reason` AS `reason`, `r`.`status` AS `status`, `r`.`admin_conversation_id` AS `admin_conversation_id`, `r`.`admin_notes` AS `admin_notes`, `r`.`reviewed_by` AS `reviewed_by`, `r`.`reviewed_at` AS `reviewed_at`, `r`.`created_at` AS `created_at`, `r`.`updated_at` AS `updated_at`, `reporter`.`name` AS `reporter_name`, `reporter`.`phone` AS `reporter_phone`, `reported`.`name` AS `reported_user_name`, `reported`.`phone` AS `reported_user_phone`, `admin_user`.`name` AS `admin_name`, `c`.`user1_id` AS `conversation_user1`, `c`.`user2_id` AS `conversation_user2` FROM ((((`reports` `r` left join `users` `reporter` on(`r`.`reporter_id` = `reporter`.`id`)) left join `users` `reported` on(`r`.`reported_user_id` = `reported`.`id`)) left join `users` `admin_user` on(`r`.`reviewed_by` = `admin_user`.`id`)) left join `conversations` `c` on(`r`.`conversation_id` = `c`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accounts_user_id` (`user_id`);

--
-- Indexes for table `account_images`
--
ALTER TABLE `account_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_chats`
--
ALTER TABLE `admin_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_user_chat` (`user_id`,`admin_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_admin_chats_user_admin` (`user_id`,`admin_id`),
  ADD KEY `idx_admin_chats_last_message` (`last_message_at`);

--
-- Indexes for table `admin_chat_messages`
--
ALTER TABLE `admin_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_created` (`chat_id`,`created_at`);

--
-- Indexes for table `admin_reviews`
--
ALTER TABLE `admin_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deal_id` (`deal_id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_reviewer_id` (`reviewer_id`),
  ADD KEY `idx_review_status` (`review_status`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  ADD KEY `user2_id` (`user2_id`),
  ADD KEY `idx_conversations_type` (`conversation_type`),
  ADD KEY `idx_conversation_type` (`conversation_type`),
  ADD KEY `idx_related_report_id` (`related_report_id`);

--
-- Indexes for table `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_admin_review_status` (`admin_review_status`),
  ADD KEY `idx_admin_reviewed_by` (`admin_reviewed_by`);

--
-- Indexes for table `deal_status_history`
--
ALTER TABLE `deal_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deal_id` (`deal_id`),
  ADD KEY `idx_changed_by` (`changed_by`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deal_id` (`deal_id`),
  ADD KEY `raised_by` (`raised_by`);

--
-- Indexes for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `deal_id` (`deal_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`account_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `financial_logs`
--
ALTER TABLE `financial_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deal_id` (`deal_id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `fk_messages_deal` (`deal_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `to_id` (`to_id`);

--
-- Indexes for table `pending_balance_transactions`
--
ALTER TABLE `pending_balance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_deal_id` (`deal_id`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_reporter` (`reporter_id`),
  ADD KEY `idx_reports_reported_user` (`reported_user_id`),
  ADD KEY `idx_reports_conversation` (`conversation_id`),
  ADD KEY `idx_reports_status` (`status`);

--
-- Indexes for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wallet_topups`
--
ALTER TABLE `wallet_topups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_deal_id` (`deal_id`);

--
-- Indexes for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `account_images`
--
ALTER TABLE `account_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `admin_chats`
--
ALTER TABLE `admin_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_chat_messages`
--
ALTER TABLE `admin_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_reviews`
--
ALTER TABLE `admin_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `deal_status_history`
--
ALTER TABLE `deal_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `financial_logs`
--
ALTER TABLE `financial_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `pending_balance_transactions`
--
ALTER TABLE `pending_balance_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `wallet_topups`
--
ALTER TABLE `wallet_topups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_chats`
--
ALTER TABLE `admin_chats`
  ADD CONSTRAINT `admin_chats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_chats_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_chat_messages`
--
ALTER TABLE `admin_chat_messages`
  ADD CONSTRAINT `admin_chat_messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `admin_chats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_reviews`
--
ALTER TABLE `admin_reviews`
  ADD CONSTRAINT `fk_admin_reviews_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_reviews_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deals`
--
ALTER TABLE `deals`
  ADD CONSTRAINT `deals_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deals_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deals_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_deals_admin_reviewer` FOREIGN KEY (`admin_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_deals_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deals_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_deals_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deal_status_history`
--
ALTER TABLE `deal_status_history`
  ADD CONSTRAINT `fk_deal_status_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deal_status_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD CONSTRAINT `escrow_transactions_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `financial_logs`
--
ALTER TABLE `financial_logs`
  ADD CONSTRAINT `financial_logs_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `financial_logs_ibfk_2` FOREIGN KEY (`from_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `financial_logs_ibfk_3` FOREIGN KEY (`to_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`from_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pending_balance_transactions`
--
ALTER TABLE `pending_balance_transactions`
  ADD CONSTRAINT `fk_pending_balance_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pending_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `wallet_topups`
--
ALTER TABLE `wallet_topups`
  ADD CONSTRAINT `wallet_topups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

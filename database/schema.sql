-- ==============================================================================
-- Canteen Management System - Database Schema & Initial Seed Data
-- ==============================================================================
-- Safe for GitHub: Contains NO sensitive personal emails, API keys, or live session tokens.
-- Compatible with MySQL 5.7+, MySQL 8.x, and MariaDB 10.3+.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- -----------------------------------------------------------------------------
-- 1. Table structure for table `settings`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'institution_name', 'University Campus'),
(2, 'institution_logo', '1774425996_BWU_logo.png'),
(3, 'canteen_name', 'Pet Pujo Canteen'),
(4, 'canteen_logo', '1774425996_pet_pujo_logo.png'),
(5, 'address1', 'Central Campus, Main Block'),
(6, 'address2', 'Kolkata, West Bengal');

-- -----------------------------------------------------------------------------
-- 2. Table structure for table `super_admins`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `super_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Super Admin: SA001 / password
INSERT INTO `super_admins` (`id`, `admin_id`, `password`, `name`, `email`, `status`, `created_at`) VALUES
(1, 'SA001', 'password', 'Super Administrator', 'admin@canteen.local', 'active', NOW());

-- -----------------------------------------------------------------------------
-- 3. Table structure for table `managers`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `managers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `t_pin` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Manager: EMP01 / password (T-PIN: 1234)
INSERT INTO `managers` (`id`, `manager_id`, `password`, `name`, `email`, `status`, `created_at`, `t_pin`) VALUES
(1, 'EMP01', 'password', 'Canteen Manager', 'manager@canteen.local', 'active', NOW(), '1234');

-- -----------------------------------------------------------------------------
-- 4. Table structure for table `canteen_staff`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `canteen_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Staff: STF001 / password
INSERT INTO `canteen_staff` (`id`, `staff_id`, `password`, `name`, `email`, `status`, `created_at`) VALUES
(1, 'STF001', 'password', 'Kitchen Staff 1', 'staff@canteen.local', 'active', NOW());

-- -----------------------------------------------------------------------------
-- 5. Table structure for table `members`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `roll` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `t_pin` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Demo Member: 1001 / password (T-PIN: 1234)
INSERT INTO `members` (`id`, `member_id`, `password`, `name`, `department`, `roll`, `email`, `status`, `created_at`, `t_pin`) VALUES
(1, '1001', 'password', 'Alex Demo Member', 'Computer Science', 'CS-2026-01', 'member@canteen.local', 'active', NOW(), '1234');

-- -----------------------------------------------------------------------------
-- 6. Table structure for table `wallet`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_id` (`member_id`),
  CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Wallet for Demo Member with 250.00 initial balance
INSERT INTO `wallet` (`id`, `member_id`, `balance`) VALUES
(1, 1, 250.00);

-- -----------------------------------------------------------------------------
-- 7. Table structure for table `menu_categories`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `menu_categories` (`id`, `name`) VALUES
(1, 'Main Dish'),
(2, 'Side Dish'),
(3, 'Thali'),
(4, 'Dessert');

-- -----------------------------------------------------------------------------
-- 8. Table structure for table `menu`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'default.jpg',
  `stock` int(11) NOT NULL DEFAULT 0,
  `default_stock` int(11) NOT NULL DEFAULT 0,
  `last_reset_date` date DEFAULT NULL,
  `available_from` time DEFAULT '00:00:00',
  `available_to` time DEFAULT '23:59:59',
  `available_days` varchar(50) NOT NULL DEFAULT 'mon,tue,wed,thu,fri,sat,sun',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `menu` (`id`, `name`, `description`, `price`, `image`, `stock`, `default_stock`, `last_reset_date`, `available_from`, `available_to`, `available_days`, `status`, `created_at`, `category_id`) VALUES
(1, 'RICE FULL', 'Freshly steamed basmati rice bowl', 15.00, 'rice.jpg', 50, 50, CURDATE(), '08:00:00', '22:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', NOW(), 1),
(2, 'Haser Dim Kosha', 'Delicious duck egg spicy curry', 25.00, 'hasdim.png', 50, 50, CURDATE(), '08:00:00', '22:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', NOW(), 2),
(3, 'Veg Thali', 'Complete wholesome vegetarian meal with dal, sabzi & roti', 40.00, 'veg thali.png', 50, 50, CURDATE(), '10:00:00', '22:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', NOW(), 3),
(4, 'Sweet Curd (Tok Dohi)', 'Traditional fresh curd bowl', 15.00, 'PROD_Curd Star Of Indian Kitchen REG_Banner_1652355979192.jpg', 50, 50, CURDATE(), '08:00:00', '22:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', NOW(), 4);

-- -----------------------------------------------------------------------------
-- 9. Table structure for table `orders`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` varchar(25) NOT NULL,
  `member_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','fully_served','partially_served','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `expected_checkout_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_id` (`coupon_id`,`created_at`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 10. Table structure for table `order_items`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `served_quantity` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','served','cancelled','refunded') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 11. Table structure for table `cart_reservations`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_menu` (`member_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 12. Table structure for table `serving_history`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `serving_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `quantity_served` int(11) NOT NULL,
  `served_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_item_id` (`order_item_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `serving_history_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `serving_history_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `canteen_staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 13. Table structure for table `transactions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 14. Table structure for table `email_queue`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `html_body` text NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `embedded_images` text DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

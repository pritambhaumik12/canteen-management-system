-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 29, 2026 at 10:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `canteen`
--

-- --------------------------------------------------------

--
-- Table structure for table `canteen_staff`
--

CREATE TABLE `canteen_staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `canteen_staff`
--

INSERT INTO `canteen_staff` (`id`, `staff_id`, `password`, `name`, `email`, `status`, `last_login`, `session_token`, `created_at`, `reset_token`, `reset_expires`) VALUES
(1, 'STF001', 'STF001', 'Test Staff Member', 'webconnection.wc@gmail.com', 'active', '2026-03-29 13:07:12', '863bb6454af1c0e6b864f150d8e620ba9554a99d565a311bef091b367eb509ae', '2026-02-25 16:52:01', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cart_reservations`
--

CREATE TABLE `cart_reservations` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `html_body` text NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `embedded_images` text DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `managers`
--

CREATE TABLE `managers` (
  `id` int(11) NOT NULL,
  `manager_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `t_pin` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `managers`
--

INSERT INTO `managers` (`id`, `manager_id`, `password`, `name`, `email`, `status`, `last_login`, `session_token`, `created_at`, `t_pin`) VALUES
(1, 'EMP01', 'EMP01', 'John Doe', 'pritambhaumiksmail1203@gmail.com', 'active', '2026-03-29 13:38:58', '19d609368c6c5380f76a3a05341799998726db039d1f92f7cd6f858a8a9fcac8', '2026-02-26 13:42:30', '1234'),
(2, 'MGR002', 'password', 'Jane Smith', 'jane.manager@example.com', 'active', NULL, NULL, '2026-02-26 13:42:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
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
  `t_pin` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_id`, `password`, `name`, `department`, `roll`, `email`, `status`, `last_login`, `session_token`, `created_at`, `reset_token`, `reset_expires`, `t_pin`) VALUES
(1, '1111', '1111', 'Sanjana Samanta', 'Computer Science', NULL, 'sanjanasamanta50@gmail.com', 'active', '2026-03-28 11:21:13', '84b8feede4197b737ee45dba8841b33cf708d4e5cbd8dd6ce362ff4568bf1e1e', '2026-02-26 13:20:07', NULL, NULL, NULL),
(2, '2222', '2222', 'Pritam Bhaumik', 'CSE', NULL, 'pritambhaumiksmail1203@gmail.com', 'active', '2026-03-29 14:01:17', 'a62e820ceb1618feff81124b6b4d97f3a09cc867c579012098635e43e55f1352', '2026-02-26 13:20:07', '626fa4377d82bd308cf5533d16fb369d9bb8fefa36cdf02dfaabdd0767617072', '2026-03-29 13:24:53', '1234'),
(14, '3333', 'Pritam123@', 'PB', 'CSE', NULL, 'codewithpritam.pb@gmail.com', 'active', '2026-03-28 20:01:57', '15d672d0e9f2c5b9382385cb0718988f76ebf0a706eeb7c42107d8b42c634039', '2026-03-28 14:14:10', NULL, NULL, '1234');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
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
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `name`, `description`, `price`, `image`, `stock`, `default_stock`, `last_reset_date`, `available_from`, `available_to`, `available_days`, `status`, `created_at`, `category_id`) VALUES
(1, 'RICE FULL', '', 15.00, 'rice.jpg', 48, 50, '2026-03-29', '10:00:00', '23:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', '2026-03-11 09:11:53', 1),
(2, 'Haser Dim Kosha', '', 25.00, 'hasdim.png', 50, 50, '2026-03-29', '11:00:00', '23:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', '2026-03-28 08:06:08', 2),
(3, 'Veg Thali', '', 40.00, 'veg thali.png', 50, 50, '2026-03-29', '11:00:00', '23:00:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', '2026-03-28 08:23:06', 3),
(4, 'Tok Dohi', 'tokkkkkkk doi', 15.00, 'PROD_Curd Star Of Indian Kitchen REG_Banner_1652355979192.jpg', 47, 50, '2026-03-29', '00:00:00', '23:59:00', 'mon,tue,wed,thu,fri,sat,sun', 'active', '2026-03-28 08:23:52', 4);

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`) VALUES
(4, 'Dessert'),
(1, 'Main Dish'),
(2, 'Side Dish'),
(3, 'Thali');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `coupon_id` varchar(25) NOT NULL,
  `member_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','fully_served','partially_served','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `expected_checkout_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `coupon_id`, `member_id`, `total_amount`, `status`, `expected_checkout_time`, `created_at`) VALUES
(1, '20260329-000001', 2, 0.00, 'refunded', NULL, '2026-03-28 19:45:12'),
(2, '20260329-000002', 2, 0.00, 'refunded', NULL, '2026-03-28 20:39:22'),
(3, '20260329-000003', 2, 0.00, 'refunded', NULL, '2026-03-28 20:49:10'),
(4, '20260329-000004', 2, 15.00, 'fully_served', NULL, '2026-03-29 05:02:35');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `served_quantity` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','served','cancelled','refunded') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `quantity`, `served_quantity`, `rate`, `amount`, `status`) VALUES
(1, 1, 4, 0, 0, 15.00, 0.00, 'refunded'),
(2, 2, 4, 0, 0, 15.00, 0.00, 'refunded'),
(3, 3, 3, 0, 0, 40.00, 0.00, 'refunded'),
(4, 4, 1, 1, 1, 15.00, 15.00, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `serving_history`
--

CREATE TABLE `serving_history` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `quantity_served` int(11) NOT NULL,
  `served_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `serving_history`
--

INSERT INTO `serving_history` (`id`, `order_item_id`, `staff_id`, `quantity_served`, `served_at`) VALUES
(1, 4, 1, 1, '2026-03-29 05:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'institution_name', 'Brainware University'),
(2, 'institution_logo', '1774425996_BWU_logo.png'),
(3, 'canteen_name', 'Pet Pujo'),
(4, 'canteen_logo', '1774425996_pet_pujo_logo.png'),
(5, 'address1', 'Barasat,Kazipara,700125'),
(6, 'address2', 'Barasat, Kolkata, West Bengal');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `admin_id`, `password`, `name`, `email`, `status`, `last_login`, `session_token`, `created_at`) VALUES
(1, 'SA001', 'password', 'Super Admin', 'admin@example.com', 'active', '2026-03-29 12:56:43', '40b05e74ff4534e970c6b7aa5197f85c889b1bfe3df5b94a27ab223f50304051', '2026-02-25 15:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `member_id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 2, 'debit', 15.00, 'Order placed: #20260329-000001', '2026-03-28 19:45:12'),
(2, 2, 'credit', 15.00, 'Refund for unserved item (Coupon #20260329-000001)', '2026-03-28 19:52:15'),
(3, 2, 'debit', 15.00, 'Order placed: #20260329-000002', '2026-03-28 20:39:22'),
(4, 2, 'credit', 15.00, 'Refund for unserved item (Coupon #20260329-000002)', '2026-03-28 20:39:38'),
(5, 2, 'credit', 15.00, 'Recharged (Cash) | By Manager ID: 1', '2026-03-28 20:44:19'),
(6, 2, 'debit', 40.00, 'Order placed: #20260329-000003 via POS (Paid via WALLET) | By Manager ID: 1', '2026-03-28 20:49:10'),
(7, 2, 'credit', 40.00, 'Refund for unserved item (Coupon #20260329-000003)', '2026-03-28 20:50:02'),
(8, 2, 'debit', 15.00, 'Order placed: #20260329-000004 via POS (Paid via WALLET) | By Manager ID: 1', '2026-03-29 05:02:35');

-- --------------------------------------------------------

--
-- Table structure for table `wallet`
--

CREATE TABLE `wallet` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet`
--

INSERT INTO `wallet` (`id`, `member_id`, `balance`) VALUES
(1, 1, 185.00),
(2, 2, 310.00),
(3, 14, 0.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `canteen_staff`
--
ALTER TABLE `canteen_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `cart_reservations`
--
ALTER TABLE `cart_reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_menu` (`member_id`,`menu_id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `managers`
--
ALTER TABLE `managers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `manager_id` (`manager_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_id` (`member_id`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_menu_category` (`category_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_id` (`coupon_id`,`created_at`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Indexes for table `serving_history`
--
ALTER TABLE `serving_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_id` (`admin_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_id` (`member_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `canteen_staff`
--
ALTER TABLE `canteen_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_reservations`
--
ALTER TABLE `cart_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `managers`
--
ALTER TABLE `managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `serving_history`
--
ALTER TABLE `serving_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wallet`
--
ALTER TABLE `wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menu`
--
ALTER TABLE `menu`
  ADD CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `serving_history`
--
ALTER TABLE `serving_history`
  ADD CONSTRAINT `serving_history_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `serving_history_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `canteen_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet`
--
ALTER TABLE `wallet`
  ADD CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

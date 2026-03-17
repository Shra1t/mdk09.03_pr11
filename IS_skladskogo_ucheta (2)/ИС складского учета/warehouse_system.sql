-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Окт 13 2025 г., 22:52
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `warehouse_system`
--

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `parent_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `parent_id`) VALUES
(1, 'Игрушкиter', 'Детские игрушки и развлеченияterw', NULL),
(2, 'Плюшевые игрушкиq', 'Мягкие плюшевые игрушкиt', NULL),
(3, 'Электронные игрушки', 'Игрушки с электронными функциями', NULL),
(9, '412512', '512523', NULL),
(11, '312', '312', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int NOT NULL,
  `delivery_code` varchar(20) NOT NULL,
  `supplier_id` int NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `deliveries`
--

INSERT INTO `deliveries` (`id`, `delivery_code`, `supplier_id`, `status`, `order_date`, `expected_date`, `actual_date`, `total_amount`, `notes`, `created_by`) VALUES
(1, 'DEL-20251011-065', 4, 'completed', '2025-10-11', '2025-10-24', '2025-10-12', '44999.00', 'labubu', 1),
(3, 'DEL-20251012-668', 5, 'in_transit', '2025-10-12', '2025-10-14', '2025-10-12', '40000.00', 'Тумба-юмба', 5),
(11, 'DEL-20251012-466', 4, 'cancelled', '2025-10-12', '2025-10-15', '2025-10-12', '66000.00', '', 6),
(12, 'DEL-20251013-379', 4, 'completed', '2025-10-13', '2025-10-15', '2025-10-13', '99000.00', '', 1),
(13, 'DEL-20251013-624', 5, 'completed', '2025-10-13', '2025-10-15', '2025-10-13', '8153.16', 'уйцуцй', 6),
(14, 'test', 5, 'completed', '2025-10-13', '2025-10-16', '2025-10-13', '6170.00', 'test', 6),
(15, 'DEL-20251013-079', 7, 'completed', '2025-10-13', '2026-06-28', '2025-10-13', '369.00', '', 6);

-- --------------------------------------------------------

--
-- Структура таблицы `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int NOT NULL,
  `delivery_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_code` varchar(20) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `product_description` text,
  `category_id` int DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'шт',
  `min_stock_level` int DEFAULT '10',
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(12,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  `received_quantity` int DEFAULT '0',
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `delivery_items`
--

INSERT INTO `delivery_items` (`id`, `delivery_id`, `product_id`, `product_code`, `product_name`, `product_description`, `category_id`, `unit`, `min_stock_level`, `image_path`, `created_at`, `quantity`, `unit_price`, `received_quantity`, `notes`) VALUES
(5, 11, NULL, 'test', 'labuba', 'Топовая игрушка для вашего маленького демона(waga)', 1, 'шт', 5, NULL, '2025-10-12 16:10:56', 6, '11000.00', 0, ''),
(6, 12, NULL, 'test', 'labuba', 'Топовая игрушка для вашего маленького демона(waga)', 1, 'шт', 5, NULL, '2025-10-13 15:53:20', 9, '11000.00', 0, ''),
(7, 13, NULL, 'testike', 'testikqqqqqqtestikqqqqqqqqq', '', 1, 'шт', 3, NULL, '2025-10-13 19:08:08', 6, '1233.86', 0, ''),
(8, 13, NULL, 'new', 'new', 'new', 9, 'компл', 3, NULL, '2025-10-13 19:08:08', 5, '150.00', 0, ''),
(9, 14, NULL, 'test', 'test', '312', 2, 'м³', 7, NULL, '2025-10-13 19:11:57', 5, '1234.00', 0, ''),
(10, 15, NULL, 'test2', '435345', 'qwe', 1, 'упак', 103, NULL, '2025-10-13 19:15:01', 123, '3.00', 0, '');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `category_id` int NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantity_in_stock` int NOT NULL DEFAULT '0',
  `min_stock_level` int DEFAULT '10',
  `unit` varchar(20) DEFAULT 'шт',
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `delivery_id` int DEFAULT NULL,
  `order_status` enum('ordered','in_transit','received','in_stock') DEFAULT 'in_stock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `description`, `category_id`, `price`, `quantity_in_stock`, `min_stock_level`, `unit`, `image_path`, `is_active`, `delivery_id`, `order_status`) VALUES
(4, '312', 'волки-толки', '54545454', 3, '123.01', 8, 1, 'шт', 'uploads/products/68eb9af570b7c_1760271093.jpg', 1, NULL, 'in_stock'),
(16, 'testike', 'testikqqqqqqtestikqqqqqqqqq', '', 1, '1234.00', 9, 3, 'шт', NULL, 1, NULL, 'in_stock'),
(17, 'new', 'new', 'new', 11, '150.00', 5, 3, 'м²', 'uploads/products/68ed54ac8a678_1760384172.jpg', 1, 13, 'in_stock'),
(19, 'test2', '435345', 'qwe', 1, '3.00', 500, 103, 'упак', NULL, 1, 15, 'in_stock'),
(20, 'testikeqwweqwq', 'qweqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqtestikqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqtestikqqqqqqqqq', 'eqweqw', 1, '1234.00', 6, 3, 'шт', NULL, 1, NULL, 'in_stock'),
(21, '1', '1', '', 1, '3.00', 412, 103, 'упак', NULL, 1, 16, 'in_stock');

-- --------------------------------------------------------

--
-- Структура таблицы `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('delivery','order','adjustment') NOT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
  `supplier_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `payment_terms` varchar(50) DEFAULT 'prepayment',
  `delivery_terms` varchar(50) DEFAULT 'pickup',
  `notes` text,
  `contact_person` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `full_name`, `company_name`, `phone`, `email`, `address`, `payment_terms`, `delivery_terms`, `notes`, `contact_person`, `is_active`, `created_at`) VALUES
(4, 'SUP001', 'М. М. Такой-то', 'gegege', '84951234567', 'omel@toymaster.ru', 'ekb', 'credit_30', 'delivery_conditional', NULL, 'Данил Омельченко', 1, '2025-10-08 13:41:00'),
(5, 'SUP005', 'Д. А. Омельченкоrttrtr', 'ООО ДАНЯОtretertret', '84951234567', 'omel@toymaster.ru', 'ekb', 'postpayment', 'delivery_free', NULL, 'Данил Омельченкоetrertretre', 1, '2025-10-08 13:45:25'),
(7, 'SUP006', 'new31', 'new321', '3213213312123', 'qwe@mail.ru', 'уйцуйцуйцу', '50_50', 'delivery_paid', NULL, 'new321', 1, '2025-10-13 19:23:25');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('employee','admin','supervisor') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'admin@warehouse.local', 'admin', 1, '2025-10-12 13:27:59'),
(2, 'employee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Сотрудник склада', 'employee@warehouse.local', 'employee', 1, '2025-10-12 13:27:59'),
(3, 'temik', '$2y$10$d94mZ2yJQuQNu6s7OnPmQuFiRdUy2ALg8Ag5InQcvQ6RJW5j62qN2', 'В. А. Попик', 'bobik_temik@mail.ru', 'admin', 1, '2025-10-12 13:36:51'),
(4, 'qwe', '$2y$10$1yu/H140zAyDurBhWHJhtuj4DPVLjmevooTM5Kzcmmu7PoT5UNn/u', 'Q. W. Employee', 'qwe@mail.ru', 'employee', 1, '2025-10-12 13:40:43'),
(5, 'sikvile', '$2y$10$EHFkTE8SGwUoLw.BiK2e4uqt6Flf9PGbaYBkOmZg5b885j6zSBriu', 'А. Н. Столяров', 'sikvile@gmail.com', 'admin', 1, '2025-10-12 13:56:55'),
(6, 'Shra1t', '$2y$10$YDccU.MRt7ZoaYWUdj9e6uvzyqD6B4C.UNIiiJAw/HiQUqrn/.PLW', 'Д. Д. Габбасов', 'Flacko2018@mail.ru', 'supervisor', 1, '2025-10-12 14:14:00'),
(8, 'qwewqewqe', '$2y$10$4bzm/lh3JKe124dByKAoieXHKEFfC1c6exbHVBxcymsNDsRwIX85e', 'eqweqwewqe', 'qweqweqwe@gmail.com', 'employee', 1, '2025-10-12 17:15:44');

-- --------------------------------------------------------

--
-- Структура таблицы `work_shifts`
--

CREATE TABLE `work_shifts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Индексы таблицы `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_code` (`delivery_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_code` (`product_code`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `work_shifts`
--
ALTER TABLE `work_shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_shift` (`user_id`,`shift_date`,`start_time`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `work_shifts`
--
ALTER TABLE `work_shifts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `delivery_items_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `work_shifts`
--
ALTER TABLE `work_shifts`
  ADD CONSTRAINT `work_shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

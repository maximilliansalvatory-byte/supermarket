-- Database creation
CREATE DATABASE IF NOT EXISTS `supermarket_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `supermarket_db`;

-- Drop tables if they exist to start fresh
DROP TABLE IF EXISTS `admin_logs`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- Users table (Customers and Admins)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'admin') DEFAULT 'customer',
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    `verification_token` VARCHAR(255) DEFAULT NULL,
    `email_verified` TINYINT(1) DEFAULT 0,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB;

-- Categories table
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `image` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB;

-- Products table
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(180) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `sale_price` DECIMAL(10, 2) DEFAULT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `images` TEXT DEFAULT NULL, -- JSON array of image file paths
    `featured` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_featured` (`featured`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- Cart table
CREATE TABLE `cart` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB;

-- Orders table
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `total` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    `payment_status` ENUM('Pending', 'Paid', 'Failed', 'Refunded') DEFAULT 'Pending',
    `address` TEXT NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- Order Items table
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Login Attempts (Rate Limiting)
CREATE TABLE `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_ip` (`email`, `ip_address`)
) ENGINE=InnoDB;

-- Admin Logs table
CREATE TABLE `admin_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================================
-- INSERT SEED DATA
-- ========================================================

-- Insert Users (Passwords are hashed 'admin123' and 'customer123' respectively)
-- Hashed admin123: $2y$10$wK1W57GvXm781Qy9T197e.jBqVj1nS7pXGqHlV81Y0zXNn2.j9zK. (standard bcrypt)
-- Using a standard bcrypt hash of 'password123': $2y$10$t3J6CgC/n7N.7k9r4m1nUOKQz9r02Kq7K9bLgUe3Y05Q4y0Kq7bLO
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `city`, `status`, `email_verified`, `created_at`) VALUES
(1, 'System Administrator', 'admin@supermarket.com', '$2y$10$t3J6CgC/n7N.7k9r4m1nUOKQz9r02Kq7K9bLgUe3Y05Q4y0Kq7bLO', 'admin', '1234567890', '123 HQ Plaza, Suite 100', 'Metro City', 'active', 1, NOW()),
(2, 'John Doe', 'john@gmail.com', '$2y$10$t3J6CgC/n7N.7k9r4m1nUOKQz9r02Kq7K9bLgUe3Y05Q4y0Kq7bLO', 'customer', '9876543210', '456 Green Valley Road', 'Greenwood', 'active', 1, NOW()),
(3, 'Jane Smith', 'jane@yahoo.com', '$2y$10$t3J6CgC/n7N.7k9r4m1nUOKQz9r02Kq7K9bLgUe3Y05Q4y0Kq7bLO', 'customer', '5551234567', '789 Pineview Terrace', 'Oakwood', 'active', 1, NOW()),
(4, 'Blocked User', 'blocked@supermarket.com', '$2y$10$t3J6CgC/n7N.7k9r4m1nUOKQz9r02Kq7K9bLgUe3Y05Q4y0Kq7bLO', 'customer', '5559876543', '12 Lock Road', 'Stonetown', 'blocked', 1, NOW());

-- Insert Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `status`) VALUES
(1, 'Fresh Fruits', 'fresh-fruits', 'fruits.png', 'active'),
(2, 'Vegetables', 'vegetables', 'vegetables.png', 'active'),
(3, 'Dairy & Eggs', 'dairy-eggs', 'dairy.png', 'active'),
(4, 'Bakery & Bread', 'bakery-bread', 'bakery.png', 'active'),
(5, 'Beverages', 'beverages', 'beverages.png', 'active');

-- Insert Products
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `sale_price`, `stock`, `images`, `featured`, `status`) VALUES
(1, 1, 'Organic Red Apples', 'organic-red-apples', 'Crisp, sweet, and locally grown organic red apples. Rich in antioxidants and perfect for a healthy snack or baking a classic apple pie.', 4.99, 3.99, 120, '["apples_1.jpg", "apples_2.jpg"]', 1, 'active'),
(2, 1, 'Fresh Bananas', 'fresh-bananas', 'A bunch of fresh yellow bananas. Loaded with potassium and nutrients, these bananas are perfect for smoothies, baking, or on-the-go snacking.', 1.99, NULL, 250, '["bananas_1.jpg"]', 1, 'active'),
(3, 2, 'Fresh Broccoli Crowns', 'fresh-broccoli-crowns', 'Crisp green broccoli crowns. Freshly harvested, highly nutritious, and perfect for steaming, stir-frying, or roasting as a healthy side dish.', 2.49, NULL, 80, '["broccoli_1.jpg"]', 1, 'active'),
(4, 2, 'Organic Carrots', 'organic-carrots', '1lb bag of sweet and crunchy organic orange carrots. Perfect for juices, soups, roasting, or raw snacking with hummus.', 1.89, 1.49, 15, '["carrots_1.jpg"]', 0, 'active'), -- Low stock
(5, 3, 'Whole Milk 1 Gallon', 'whole-milk-1-gallon', 'Pasteurized vitamin D whole milk from local dairy farms. Creamy and delicious, perfect for the whole family.', 3.49, NULL, 60, '["milk_1.jpg"]', 1, 'active'),
(6, 3, 'Organic Large Brown Eggs', 'organic-large-brown-eggs', 'One dozen cage-free, organic large brown eggs. High in protein and farm-fresh, great for all your breakfast and baking needs.', 5.99, 4.99, 40, '["eggs_1.jpg"]', 1, 'active'),
(7, 4, 'Sourdough Bread', 'sourdough-bread', 'Artisanal freshly baked sourdough bread with a crispy crust and soft, airy interior. Baked fresh daily in our in-store bakery.', 4.50, NULL, 25, '["sourdough_1.jpg"]', 0, 'active'),
(8, 4, 'Chocolate Chip Muffins', 'chocolate-chip-muffins', 'Pack of 4 delicious, moist muffins loaded with rich chocolate chips. An indulgence that pairs wonderfully with your morning coffee.', 5.49, 4.49, 5, '["muffins_1.jpg"]', 1, 'active'), -- Very low stock
(9, 5, '100% Pure Orange Juice', 'pure-orange-juice', '59 fl oz bottle of premium, pulp-free orange juice. Squeezed from fresh, ripe oranges with no added sugar or preservatives.', 4.29, NULL, 95, '["orange_juice_1.jpg"]', 1, 'active'),
(10, 5, 'Sparkling Water 12-Pack', 'sparkling-water-12-pack', 'Refreshing lime flavored sparkling water with zero calories and zero sweeteners. Clean and bubbly refreshment.', 6.99, 5.99, 0, '["sparkling_1.jpg"]', 0, 'active'); -- Out of stock

-- Insert Sample Orders to simulate realistic sales data (spread across multiple past days)
-- Let's define: Total sales, states. All are John Doe (user_id = 2) or Jane Smith (user_id = 3)
INSERT INTO `orders` (`id`, `user_id`, `order_number`, `total`, `status`, `payment_status`, `address`, `phone`, `city`, `notes`, `created_at`) VALUES
(1, 2, 'ORD-20260515-9821', 17.46, 'Delivered', 'Paid', '456 Green Valley Road', '9876543210', 'Greenwood', 'Leave at door', '2026-05-15 10:30:00'),
(2, 3, 'ORD-20260518-1240', 32.94, 'Delivered', 'Paid', '789 Pineview Terrace', '5551234567', 'Oakwood', NULL, '2026-05-18 14:15:00'),
(3, 2, 'ORD-20260520-4311', 12.47, 'Shipped', 'Paid', '456 Green Valley Road', '9876543210', 'Greenwood', 'Call on arrival', '2026-05-20 09:00:00'),
(4, 3, 'ORD-20260521-8845', 21.96, 'Processing', 'Paid', '789 Pineview Terrace', '5551234567', 'Oakwood', 'Ring doorbell', '2026-05-21 17:40:00'),
(5, 2, 'ORD-20260522-0982', 15.96, 'Pending', 'Pending', '456 Green Valley Road', '9876543210', 'Greenwood', NULL, NOW());

-- Insert Order Items for the orders
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) VALUES
-- ORD 1 items (Total 17.46)
(1, 1, 2, 3.99), -- Apples
(1, 5, 2, 3.49), -- Milk
(1, 2, 1, 1.99), -- Bananas
-- ORD 2 items (Total 32.94)
(2, 6, 2, 4.99), -- Eggs
(2, 9, 3, 4.29), -- OJ
(2, 7, 2, 4.50), -- Bread
-- ORD 3 items (Total 12.47)
(3, 3, 3, 2.49), -- Broccoli
(3, 6, 1, 4.99), -- Eggs
-- ORD 4 items (Total 21.96)
(4, 10, 2, 5.99), -- Sparkling water
(4, 1, 2, 3.99), -- Apples
(4, 2, 1, 1.99), -- Bananas
-- ORD 5 items (Total 15.96)
(5, 1, 4, 3.99); -- Apples

-- Add some default items to Jane Smith's cart
INSERT INTO `cart` (`user_id`, `product_id`, `quantity`) VALUES
(3, 2, 3), -- 3 Bananas in cart
(3, 5, 1); -- 1 Milk in cart

-- Add some default Admin Logs
INSERT INTO `admin_logs` (`admin_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 'System Initialization', 'Database seed loaded successfully with categories and products.', '127.0.0.1', '2026-05-15 08:00:00'),
(1, 'Stock Update', 'Product "Sparkling Water 12-Pack" stock set to 0 (out of stock).', '127.0.0.1', '2026-05-20 11:22:00'),
(1, 'Category Created', 'Created new category: Beverages.', '127.0.0.1', '2026-05-15 08:15:00');

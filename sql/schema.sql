-- Ton Grid Bot - MySQL Schema (Railway ready)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(128) NOT NULL,
  `username` VARCHAR(128) NOT NULL,
  `invite_link` VARCHAR(255) DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED PRIMARY KEY,
  `username` VARCHAR(64) DEFAULT NULL,
  `first_name` VARCHAR(128) DEFAULT NULL,
  `last_name` VARCHAR(128) DEFAULT NULL,
  `balance` DECIMAL(18,6) DEFAULT 0.000000,
  `referral_code` VARCHAR(32) DEFAULT NULL,
  `referred_by` BIGINT UNSIGNED DEFAULT NULL,
  `is_joined` TINYINT(1) DEFAULT 0,
  `is_blocked` TINYINT(1) DEFAULT 0,
  `wallet_address` VARCHAR(128) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_referral` (`referral_code`),
  INDEX `idx_referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('deposit','withdraw','bonus','referral','admin_add','admin_cut','task') NOT NULL,
  `amount` DECIMAL(18,6) NOT NULL,
  `status` ENUM('pending','completed','rejected','cancelled') DEFAULT 'pending',
  `tx_hash` VARCHAR(128) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(18,6) NOT NULL,
  `address` VARCHAR(128) NOT NULL,
  `status` ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
  `admin_note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admins` (`username`, `password_hash`) VALUES
('admin', '$2y$10$R.8RkWvI7k58MVrnttm3/O40peeTROQnPv4C0eT5/31DlU2loOqQe')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('bot_token', ''),
('bot_username', ''),
('support_username', ''),
('welcome_text', 'Welcome to Ton Grid HAMIM-XYZ'),
('currency_name', 'USDT'),
('currency_symbol', '$'),
('min_withdraw', '1'),
('withdraw_mode', 'manual'),
('referral_percent', '10'),
('payment_channel', ''),
('notify_channel', ''),
('hot_wallet_private_key', ''),
('network', 'BEP20'),
('is_maintenance', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

SET FOREIGN_KEY_CHECKS = 1;

<?php
/**
 * Auto-create tables if missing (safe to run multiple times)
 */

function runMigrations(): array
{
    $db = getDB();
    $done = [];

    $statements = [
        'admins' => "CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'channels' => "CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(128) NOT NULL,
  `username` VARCHAR(128) NOT NULL,
  `invite_link` VARCHAR(255) DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'users' => "CREATE TABLE IF NOT EXISTS `users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'transactions' => "CREATE TABLE IF NOT EXISTS `transactions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'withdrawals' => "CREATE TABLE IF NOT EXISTS `withdrawals` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $name => $sql) {
        $db->exec($sql);
        $done[] = $name;
    }

    // Default password: admin123
    // IMPORTANT: use single-quoted hash so $2y$ is NOT treated as PHP variables
    $adminHash = '$2y$10$R.8RkWvI7k58MVrnttm3/O40peeTROQnPv4C0eT5/31DlU2loOqQe';

    $count = (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['admin', $adminHash]);
        $done[] = 'seed_admin';
    } else {
        // Repair broken hash from older double-quoted seed (password would never verify)
        $row = $db->query("SELECT id, password_hash FROM admins WHERE username = 'admin' LIMIT 1")->fetch();
        if ($row && !password_verify('admin123', (string)$row['password_hash'])) {
            $stmt = $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $stmt->execute([$adminHash, $row['id']]);
            $done[] = 'repair_admin_password';
        }
    }

    $defaults = [
        'bot_token' => '',
        'bot_username' => '',
        'support_username' => '',
        'welcome_text' => 'Welcome to Ton Grid HAMIM-XYZ',
        'currency_name' => 'USDT',
        'currency_symbol' => '$',
        'min_withdraw' => '1',
        'withdraw_mode' => 'manual',
        'referral_percent' => '10',
        'referral_bonus' => '1.00',
        'payment_channel' => '',
        'notify_channel' => '',
        'hot_wallet_private_key' => '',
        'network' => 'BEP20',
        'is_maintenance' => '0',
        'menu_btn_wallet' => 'USDT Wallet',
        'menu_btn_referrals' => 'Referrals',
        'menu_btn_payout' => 'USDT Payout',
        'menu_btn_earn' => 'EARN MORE',
        'usdt_contract' => '0x55d398326f99059fF775485246999027B3197955',
        'rpc_url' => '',
        'img_welcome' => '',
        'img_join' => '',
        'img_menu' => '',
        'img_wallet' => '',
        'img_referrals' => '',
        'img_payout' => '',
        'img_earn' => '',
        'img_welcome_on' => '0',
        'img_join_on' => '0',
        'img_menu_on' => '0',
        'img_wallet_on' => '0',
        'img_referrals_on' => '0',
        'img_payout_on' => '0',
        'img_earn_on' => '0',
        'webhook_enabled' => '0',
        'ce_welcome_1' => '5458904472598095631',
        'ce_welcome_2' => '6001538474795078519',
        'ce_welcome_3' => '6269103435413459285',
        'ce_welcome_4' => '5386757680679377085',
        'ce_welcome_5' => '6059724471223194869',
        'ce_welcome_6' => '6222198028854367391',
    ];
    $stmt = $db->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    $added = 0;
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
        $added += $stmt->rowCount();
    }
    if ($added > 0) {
        $done[] = 'seed_settings_' . $added;
    }

    try {
        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'has_agreed'")->fetch();
        if (!$cols) {
            $db->exec("ALTER TABLE users ADD COLUMN has_agreed TINYINT(1) DEFAULT 0 AFTER is_joined");
            $done[] = 'col_has_agreed';
        }
    } catch (Throwable $e) {}

    try {
        $c = $db->query("SHOW COLUMNS FROM users LIKE 'bot_state'")->fetch();
        if (!$c) {
            $db->exec("ALTER TABLE users ADD COLUMN bot_state VARCHAR(64) DEFAULT NULL");
            $db->exec("ALTER TABLE users ADD COLUMN bot_state_data TEXT DEFAULT NULL");
            $done[] = 'col_bot_state';
        }
    } catch (Throwable $e) {}

    try {
        $c = $db->query("SHOW COLUMNS FROM users LIKE 'bot_msgs'")->fetch();
        if (!$c) {
            $db->exec("ALTER TABLE users ADD COLUMN bot_msgs TEXT DEFAULT NULL");
            $done[] = 'col_bot_msgs';
        }
    } catch (Throwable $e) {}

    return $done;
}

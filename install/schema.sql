-- منصة تذاكر الأطفال — قاعدة البيانات (MySQL 8 / MariaDB 10.4+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  total_count INT UNSIGNED NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prizes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  image VARCHAR(255) NULL,
  quantity INT UNSIGNED NOT NULL,
  won_count INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  code CHAR(10) NOT NULL,
  prize_id INT UNSIGNED NULL,
  status ENUM('unused','used','void') NOT NULL DEFAULT 'unused',
  used_at DATETIME NULL,
  used_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (code),
  KEY idx_batch_status (batch_id, status),
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS winners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL UNIQUE,
  claim_token CHAR(32) NOT NULL UNIQUE,
  child_name VARCHAR(100) NULL,
  guardian_phone VARCHAR(20) NULL,
  phone_verified_at DATETIME NULL,
  delivery_status ENUM('pending','delivered') NOT NULL DEFAULT 'pending',
  delivered_at DATETIME NULL,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  code_entered VARCHAR(40) NOT NULL,
  result ENUM('win','lose','used','invalid','inactive','blocked') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  winner_id INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_winner (winner_id),
  FOREIGN KEY (winner_id) REFERENCES winners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  winner_id INT UNSIGNED NULL,
  channel ENUM('whatsapp','sms') NOT NULL,
  recipient VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  twilio_sid VARCHAR(64) NULL,
  status VARCHAR(30) NOT NULL,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (winner_id) REFERENCES winners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('site_name', 'تذكرة الحظ'),
('site_tagline', 'أدخل رمز تذكرتك واكتشف إذا ربحت!'),
('support_whatsapp', ''),
('msg_win', 'ألف مبروك! 🎉 بطلنا {child} ربح {prize}. رقم المطالبة: {claim}. سنتواصل معكم لترتيب الاستلام.'),
('msg_delivered', 'تم تسليم جائزة {child} ({prize}) بنجاح. شكراً لمشاركتكم! 🎁'),
('wa_content_sid_win', ''),
('wa_content_sid_delivered', '');

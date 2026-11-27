<?php
declare(strict_types=1);

/**
 * ترقيات قاعدة البيانات — تُشغَّل تلقائياً مرة واحدة عند أول طلب بعد التحديث.
 * كل ترقية تزيد schema_version بواحد، ولا تُعاد أبداً.
 */

const SCHEMA_VERSION = 4;

function column_exists(string $table, string $column): bool
{
    return (bool)q(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    )->fetchColumn();
}

function run_migrations(): void
{
    $current = (int)setting('schema_version', '1');
    if ($current >= SCHEMA_VERSION) return;

    $lock = (int)q("SELECT GET_LOCK('kt_migrate', 10)")->fetchColumn();
    if ($lock !== 1) return;
    try {
        $current = (int)(q("SELECT `value` FROM settings WHERE `key` = 'schema_version'")->fetchColumn() ?: 1);

        if ($current < 2) {
            // v2: بوت واتساب — مصدر المحاولة/الفوز، اتجاه الرسالة، وجلسات المحادثة
            if (!column_exists('attempts', 'source')) {
                db()->exec("ALTER TABLE attempts ADD COLUMN source ENUM('web','whatsapp') NOT NULL DEFAULT 'web' AFTER result");
            }
            if (!column_exists('winners', 'source')) {
                db()->exec("ALTER TABLE winners ADD COLUMN source ENUM('web','whatsapp') NOT NULL DEFAULT 'web' AFTER claim_token");
            }
            if (!column_exists('message_logs', 'direction')) {
                db()->exec("ALTER TABLE message_logs ADD COLUMN direction ENUM('out','in') NOT NULL DEFAULT 'out' AFTER channel");
            }
            db()->exec(
                "CREATE TABLE IF NOT EXISTS wa_sessions (
                   phone VARCHAR(20) PRIMARY KEY,
                   state VARCHAR(20) NOT NULL DEFAULT 'idle',
                   winner_id INT UNSIGNED NULL,
                   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                   FOREIGN KEY (winner_id) REFERENCES winners(id) ON DELETE SET NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $defaults = [
                'wa_bot_enabled' => '0',
                'bot_welcome' => "أهلاً بك في {site}! 🎟️\nأرسل رمز تذكرتك (10 حروف وأرقام) وأخبرك فوراً إذا ربحت.",
                'bot_win'     => "🎉 ألف مبروك! 🎉\nربحت: {prize}\nرقم المطالبة: {claim}\n\nأرسل *اسم الطفل* الآن لإكمال تسجيل الجائزة.",
                'bot_lose'    => "حظ أوفر المرة القادمة 💜\nلا تزعل يا بطل، كل تذكرة فرصة جديدة! أرسل رمزاً آخر متى ما حبيت.",
                'bot_done'    => "تم تسجيل جائزة {child} ✅\nالجائزة: {prize}\nرقم المطالبة: {claim}\nسنتواصل معكم على هذا الرقم لترتيب الاستلام.",
            ];
            $st = db()->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
            foreach ($defaults as $k => $v) $st->execute([$k, $v]);
            save_setting('schema_version', '2');
        }

        if ($current < 3) {
            // v3: بانرات الواجهة الرئيسية (شرائح متحركة يديرها الأدمين)
            db()->exec(
                "CREATE TABLE IF NOT EXISTS banners (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   eyebrow VARCHAR(80) NULL,
                   title1 VARCHAR(80) NOT NULL,
                   title2 VARCHAR(80) NULL,
                   body VARCHAR(240) NULL,
                   image VARCHAR(255) NOT NULL,
                   btn_text VARCHAR(40) NULL,
                   sort SMALLINT NOT NULL DEFAULT 0,
                   is_active TINYINT(1) NOT NULL DEFAULT 1,
                   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            if (!(int)q('SELECT COUNT(*) FROM banners')->fetchColumn()) {
                $st = db()->prepare('INSERT INTO banners (eyebrow, title1, title2, body, image, btn_text, sort) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $st->execute(['اكشف · افرح · اربح', 'جرّب حظك', 'واكشف تذكرتك', 'في كل تذكرة مفاجأة تنتظرك. أدخل الرمز واكتشف إذا كنت من الرابحين!', 'art:hero-city.svg', 'اكشف تذكرتك', 1]);
                $st->execute(['جوائز كل يوم', 'مهرجان', 'الجوائز الكبرى', 'دراجات وألعاب وهدايا مميزة بانتظار الأبطال. كل رمز فرصة جديدة للفوز.', 'art:hero-carnival.svg', 'جرّب الآن', 2]);
                $st->execute(['مغامرة الحظ', 'انطلق', 'نحو الفوز', 'امسح رمز QR أو اكتب رمز تذكرتك، وخلّ المفاجأة تبدأ!', 'art:hero-space.svg', 'ابدأ المغامرة', 3]);
            }
            save_setting('schema_version', '3');
        }

        if ($current < 4) {
            // v4: المتسابقون، المستويات، بنك الأسئلة، سجل النقاط
            $tables = [
                "CREATE TABLE IF NOT EXISTS players (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   full_name VARCHAR(120) NOT NULL,
                   phone VARCHAR(20) NOT NULL UNIQUE,
                   age TINYINT UNSIGNED NULL,
                   avatar VARCHAR(40) NOT NULL DEFAULT 'a1',
                   points INT NOT NULL DEFAULT 0,
                   streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                   last_play_date DATE NULL,
                   consent_at DATETIME NOT NULL,
                   is_blocked TINYINT(1) NOT NULL DEFAULT 0,
                   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   INDEX (points)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS levels (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   sort TINYINT UNSIGNED NOT NULL,
                   name VARCHAR(40) NOT NULL,
                   min_points INT UNSIGNED NOT NULL,
                   prize VARCHAR(120) NOT NULL,
                   winners_limit INT UNSIGNED NOT NULL DEFAULT 10,
                   color VARCHAR(10) NOT NULL DEFAULT 'gold',
                   is_active TINYINT(1) NOT NULL DEFAULT 1
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS questions (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   body VARCHAR(300) NOT NULL,
                   opt_a VARCHAR(120) NOT NULL,
                   opt_b VARCHAR(120) NOT NULL,
                   opt_c VARCHAR(120) NOT NULL,
                   opt_d VARCHAR(120) NOT NULL,
                   correct CHAR(1) NOT NULL,
                   category VARCHAR(40) NOT NULL DEFAULT 'عام',
                   difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
                   is_active TINYINT(1) NOT NULL DEFAULT 1,
                   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS answers (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   player_id INT UNSIGNED NOT NULL,
                   question_id INT UNSIGNED NOT NULL,
                   choice CHAR(1) NULL,
                   is_correct TINYINT(1) NOT NULL DEFAULT 0,
                   ms INT UNSIGNED NOT NULL DEFAULT 0,
                   points INT NOT NULL DEFAULT 0,
                   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   UNIQUE KEY uq_pq (player_id, question_id),
                   INDEX (player_id, created_at),
                   FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                   FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS points_ledger (
                   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   player_id INT UNSIGNED NOT NULL,
                   points INT NOT NULL,
                   reason VARCHAR(30) NOT NULL,
                   ref VARCHAR(60) NULL,
                   created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                   INDEX (player_id),
                   FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS level_progress (
                   player_id INT UNSIGNED NOT NULL,
                   level_id INT UNSIGNED NOT NULL,
                   reached_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                   PRIMARY KEY (player_id, level_id),
                   INDEX (level_id, reached_at),
                   FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                   FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ];
            foreach ($tables as $sql) db()->exec($sql);

            if (!(int)q('SELECT COUNT(*) FROM levels')->fetchColumn()) {
                $st = db()->prepare('INSERT INTO levels (sort, name, min_points, prize, winners_limit, color) VALUES (?, ?, ?, ?, ?, ?)');
                $st->execute([1, 'البرونزي', 300, 'جائزة الدرجة الثالثة', 100, 'bronze']);
                $st->execute([2, 'الفضي', 800, 'جائزة الدرجة الثانية', 20, 'silver']);
                $st->execute([3, 'الذهبي', 1500, 'الجائزة الذهبية الكبرى', 3, 'gold']);
            }
            if (!(int)q('SELECT COUNT(*) FROM questions')->fetchColumn()) {
                require APP_ROOT . '/includes/seed_questions.php';
                $st = db()->prepare('INSERT INTO questions (body, opt_a, opt_b, opt_c, opt_d, correct, category, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                foreach (seed_questions() as $row) $st->execute($row);
            }
            $defaults = [
                'daily_questions' => '10', 'points_correct' => '10', 'points_speed' => '5',
                'question_seconds' => '20', 'points_streak' => '20', 'grace_days' => '3',
                'points_ticket' => '50', 'contest_rules' => '',
                'contest_license' => '',
            ];
            $st = db()->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
            foreach ($defaults as $k => $v) $st->execute([$k, $v]);
            save_setting('schema_version', '4');
        }
    } finally {
        q("SELECT RELEASE_LOCK('kt_migrate')");
    }
    reset_settings_cache();
}

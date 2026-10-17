<?php
declare(strict_types=1);

/**
 * ترقيات قاعدة البيانات — تُشغَّل تلقائياً مرة واحدة عند أول طلب بعد التحديث.
 * كل ترقية تزيد schema_version بواحد، ولا تُعاد أبداً.
 */

const SCHEMA_VERSION = 3;

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
    } finally {
        q("SELECT RELEASE_LOCK('kt_migrate')");
    }
    reset_settings_cache();
}

<?php
declare(strict_types=1);
/**
 * معالج التثبيت — يُشغَّل مرة واحدة فقط.
 * ينشئ الجداول وحساب المالك وملف config.php، ثم يقفل نفسه.
 * بعد التثبيت احذف مجلد install من السيرفر.
 */
$root = dirname(__DIR__);
$configFile = $root . '/config.php';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

session_start();
if (empty($_SESSION['inst_csrf'])) $_SESSION['inst_csrf'] = bin2hex(random_bytes(16));

$installed = is_file($configFile);
$errors = [];
$done = false;

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$guessBase = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/\\');

$f = array_merge([
    'base_url' => $guessBase, 'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_name' => 'تذكرة الحظ', 'admin_name' => '', 'admin_email' => '', 'admin_pass' => '',
    'tw_sid' => '', 'tw_token' => '', 'tw_verify' => '', 'tw_wa_from' => '', 'tw_sms_from' => '', 'tw_otp_channel' => 'sms',
], array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST));

$checks = [
    'PHP 8.1 أو أحدث' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'إضافة pdo_mysql' => extension_loaded('pdo_mysql'),
    'إضافة curl (لـ Twilio)' => extension_loaded('curl'),
    'إضافة mbstring' => extension_loaded('mbstring'),
    'صلاحية الكتابة في مجلد الموقع' => is_writable($root),
    'صلاحية الكتابة في مجلد uploads' => is_dir($root . '/uploads') ? is_writable($root . '/uploads') : is_writable($root),
];

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['inst_csrf'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'انتهت صلاحية الصفحة، حدّثها.';
    if (in_array(false, $checks, true)) $errors[] = 'بعض متطلبات السيرفر غير متوفرة (انظر القائمة).';
    if (!filter_var($f['base_url'], FILTER_VALIDATE_URL)) $errors[] = 'رابط الموقع غير صحيح.';
    if ($f['db_name'] === '' || $f['db_user'] === '') $errors[] = 'أكمل بيانات قاعدة البيانات.';
    if ($f['admin_name'] === '' || !filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'أكمل اسم وبريد المالك.';
    if (strlen($f['admin_pass']) < 10) $errors[] = 'كلمة مرور المالك 10 أحرف على الأقل.';

    if (!$errors) {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $f['db_host'], (int)$f['db_port'], $f['db_name']), $f['db_user'], $f['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute(['site_name', $f['site_name'] ?: 'تذكرة الحظ']);
            $exists = $pdo->prepare('SELECT 1 FROM admins WHERE email = ?');
            $exists->execute([strtolower($f['admin_email'])]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, 'owner')")
                    ->execute([$f['admin_name'], strtolower($f['admin_email']), password_hash($f['admin_pass'], PASSWORD_DEFAULT)]);
            }

            $config = [
                'base_url' => rtrim($f['base_url'], '/'),
                'timezone' => 'Asia/Riyadh',
                'debug'    => false,
                'db'       => ['host' => $f['db_host'], 'port' => (int)$f['db_port'], 'name' => $f['db_name'], 'user' => $f['db_user'], 'pass' => $f['db_pass']],
                'twilio'   => [
                    'account_sid'        => $f['tw_sid'],
                    'auth_token'         => $f['tw_token'],
                    'verify_service_sid' => $f['tw_verify'],
                    'otp_channel'        => in_array($f['tw_otp_channel'], ['sms', 'whatsapp'], true) ? $f['tw_otp_channel'] : 'sms',
                    'whatsapp_from'      => $f['tw_wa_from'],
                    'sms_from'           => $f['tw_sms_from'],
                ],
            ];
            $php = "<?php\n// أُنشئ بواسطة معالج التثبيت في " . date('Y-m-d H:i') . " — لا تشارك هذا الملف مع أحد.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configFile, $php, LOCK_EX) === false) throw new RuntimeException('تعذّر كتابة config.php، تحقق من الصلاحيات.');
            @chmod($configFile, 0640);
            if (!is_dir($root . '/uploads')) @mkdir($root . '/uploads', 0755, true);
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'خطأ: ' . $ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>تثبيت منصة التذاكر</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>.inst{max-width:760px;margin:30px auto;padding:0 16px}.inst h1{font-size:1.7rem;margin:0 0 4px}.inst .card{margin-top:16px}</style>
</head>
<body class="admin admin--guest">
<div class="inst">
  <h1>تثبيت منصة تذاكر الأطفال</h1>
  <p class="muted">خطوة واحدة: أدخل بيانات قاعدة البيانات وحساب المالك.</p>

  <?php if ($installed && !$done): ?>
    <div class="card"><div class="alert alert--warn">المنصة مثبّتة مسبقاً. لإعادة التثبيت احذف ملف config.php أولاً.</div>
    <p><strong>للأمان: احذف مجلد <code>install</code> من السيرفر الآن.</strong></p><a class="btn btn--primary" href="../admin/">الذهاب للوحة التحكم</a></div>
  <?php elseif ($done): ?>
    <div class="card">
      <div class="alert alert--ok">تم التثبيت بنجاح! 🎉</div>
      <ol>
        <li><strong>احذف مجلد <code>install</code> من السيرفر فوراً</strong> (من File Manager في Hostinger).</li>
        <li>ادخل لوحة التحكم وأنشئ أول دفعة تذاكر.</li>
        <li>جرّب رمزاً من الدفعة في الصفحة الرئيسية.</li>
      </ol>
      <a class="btn btn--primary" href="../admin/">دخول لوحة التحكم</a> <a class="btn btn--ghost" href="../">الصفحة الرئيسية</a>
    </div>
  <?php else: ?>
    <div class="card">
      <h2>متطلبات السيرفر</h2>
      <ul class="checklist"><?php foreach ($checks as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'no' ?>"><?= $h($label) ?></li><?php endforeach; ?></ul>
    </div>
    <?php if ($errors): ?><div class="alert alert--error"><?php foreach ($errors as $e) echo '<div>' . $h($e) . '</div>'; ?></div><?php endif; ?>
    <form method="post" class="card form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= $h($_SESSION['inst_csrf']) ?>">
      <h2>الموقع</h2>
      <div class="form-grid">
        <label>رابط الموقع<input name="base_url" dir="ltr" value="<?= $h($f['base_url']) ?>" required></label>
        <label>اسم الموقع<input name="site_name" value="<?= $h($f['site_name']) ?>"></label>
      </div>
      <h2 class="form__section">قاعدة البيانات MySQL</h2>
      <p class="muted small">من hPanel ← Databases ← MySQL Databases: أنشئ قاعدة ومستخدماً وانسخ بياناتهم هنا.</p>
      <div class="form-grid">
        <label>Host<input name="db_host" dir="ltr" value="<?= $h($f['db_host']) ?>" required></label>
        <label>Port<input name="db_port" dir="ltr" value="<?= $h($f['db_port']) ?>"></label>
        <label>اسم القاعدة<input name="db_name" dir="ltr" value="<?= $h($f['db_name']) ?>" required placeholder="u123456789_tickets"></label>
        <label>المستخدم<input name="db_user" dir="ltr" value="<?= $h($f['db_user']) ?>" required></label>
        <label class="span-2">كلمة المرور<input type="password" name="db_pass" dir="ltr" value="<?= $h($f['db_pass']) ?>"></label>
      </div>
      <h2 class="form__section">حساب المالك (الأدمين)</h2>
      <div class="form-grid">
        <label>الاسم<input name="admin_name" value="<?= $h($f['admin_name']) ?>" required></label>
        <label>البريد<input type="email" name="admin_email" dir="ltr" value="<?= $h($f['admin_email']) ?>" required></label>
        <label class="span-2">كلمة المرور (10 أحرف على الأقل)<input type="password" name="admin_pass" dir="ltr" minlength="10" required autocomplete="new-password"></label>
      </div>
      <details class="advanced">
        <summary>Twilio وواتساب (اختياري — يمكن إضافتها لاحقاً في config.php)</summary>
        <div class="form-grid">
          <label>Account SID<input name="tw_sid" dir="ltr" value="<?= $h($f['tw_sid']) ?>" placeholder="ACxxxxxxxx"></label>
          <label>Auth Token<input type="password" name="tw_token" dir="ltr" value="<?= $h($f['tw_token']) ?>"></label>
          <label>Verify Service SID <small class="muted">(لرمز OTP)</small><input name="tw_verify" dir="ltr" value="<?= $h($f['tw_verify']) ?>" placeholder="VAxxxxxxxx"></label>
          <label>قناة OTP<select name="tw_otp_channel"><option value="sms">SMS</option><option value="whatsapp" <?= $f['tw_otp_channel'] === 'whatsapp' ? 'selected' : '' ?>>واتساب</option></select></label>
          <label>رقم واتساب المرسل<input name="tw_wa_from" dir="ltr" value="<?= $h($f['tw_wa_from']) ?>" placeholder="+14155238886"></label>
          <label>رقم SMS المرسل <small class="muted">(اختياري)</small><input name="tw_sms_from" dir="ltr" value="<?= $h($f['tw_sms_from']) ?>"></label>
        </div>
      </details>
      <button class="btn btn--primary">تثبيت</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>

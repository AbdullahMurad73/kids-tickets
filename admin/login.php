<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

if (current_admin()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = client_ip();
    $fails = (int)q("SELECT COUNT(*) FROM activity_log WHERE action = 'login_failed' AND ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)", [$ip])->fetchColumn();
    if ($fails >= 5) {
        $error = 'محاولات كثيرة. انتظر 15 دقيقة ثم حاول مرة أخرى.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $admin = q('SELECT * FROM admins WHERE email = ? AND is_active = 1', [$email])->fetch();
        if ($admin && password_verify($pass, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_seen'] = time();
            q('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$admin['id']]);
            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $admin['id']]);
            }
            log_activity('login');
            redirect('index.php');
        }
        log_activity('login_failed', $email);
        $error = 'البريد أو كلمة المرور غير صحيحة.';
    }
}

admin_header('تسجيل الدخول', '');
?>
<div class="login">
  <form method="post" class="card login__card">
    <div class="login__logo">★</div>
    <h1>لوحة التحكم</h1>
    <p class="muted"><?= e(setting('site_name')) ?></p>
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
    <label>البريد الإلكتروني<input type="email" name="email" required autofocus dir="ltr" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>كلمة المرور<input type="password" name="password" required dir="ltr"></label>
    <button class="btn btn--primary btn--block" type="submit">دخول</button>
  </form>
</div>
<?php admin_footer();

<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$me = require_admin(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'owner' ? 'owner' : 'staff';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
            flash('أكمل البيانات: اسم، بريد صحيح، وكلمة مرور 10 أحرف على الأقل.', 'error');
        } elseif (q('SELECT 1 FROM admins WHERE email = ?', [$email])->fetchColumn()) {
            flash('هذا البريد مسجّل مسبقاً.', 'error');
        } else {
            q('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            log_activity('admin_added', $email);
            flash('تمت إضافة المشرف.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== (int)$me['id']) {
            q('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [$id]);
            log_activity('admin_toggled', "#$id");
            flash('تم تحديث الحالة.');
        }
    }
    redirect('admins.php');
}

$admins = q('SELECT * FROM admins ORDER BY id')->fetchAll();
$log = q('SELECT l.*, a.name FROM activity_log l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 25')->fetchAll();

admin_header('المشرفون', 'admins');
?>
<div class="grid-2">
  <section class="card">
    <h2>الحسابات</h2>
    <table class="table">
      <thead><tr><th>الاسم</th><th>الصلاحية</th><th>آخر دخول</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr class="<?= $a['is_active'] ? '' : 'is-off' ?>">
          <td><strong><?= e($a['name']) ?></strong><br><small class="muted" dir="ltr"><?= e($a['email']) ?></small></td>
          <td><?= $a['role'] === 'owner' ? 'مالك' : 'موظف' ?></td>
          <td class="muted small"><?= e(dt($a['last_login_at'])) ?></td>
          <td><?php if ((int)$a['id'] !== (int)$me['id']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn--ghost btn--sm"><?= $a['is_active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <form method="post" class="card form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>إضافة مشرف</h2>
    <label>الاسم<input name="name" required maxlength="100"></label>
    <label>البريد<input type="email" name="email" required dir="ltr"></label>
    <label>كلمة المرور <small class="muted">(10 أحرف على الأقل)</small><input type="password" name="password" required minlength="10" dir="ltr" autocomplete="new-password"></label>
    <label>الصلاحية
      <select name="role"><option value="staff">موظف: الدفعات، الرابحون، البحث</option><option value="owner">مالك: كل شيء + الإعدادات والمشرفون</option></select>
    </label>
    <button class="btn btn--primary">إضافة</button>
  </form>
</div>

<section class="card">
  <h2>سجل العمليات</h2>
  <table class="table table--compact">
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr><td class="muted small"><?= e(dt($l['created_at'])) ?></td><td><?= e($l['name'] ?: '—') ?></td><td><code><?= e($l['action']) ?></code></td><td class="small"><?= e($l['details']) ?></td><td dir="ltr" class="muted small"><?= e($l['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_footer();

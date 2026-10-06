<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$batch = q('SELECT * FROM batches WHERE id = ?', [$id])->fetch();
if (!$batch) { flash('الدفعة غير موجودة.', 'error'); redirect('batches.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $map = ['pause' => 'paused', 'resume' => 'active', 'end' => 'ended'];
    if (isset($map[$action])) {
        q('UPDATE batches SET status = ? WHERE id = ?', [$map[$action], $id]);
        log_activity('batch_' . $action, "#$id");
        flash(['pause' => 'تم إيقاف الدفعة مؤقتاً، لن تقبل أي تذاكر منها.', 'resume' => 'تم تفعيل الدفعة.', 'end' => 'تم إنهاء الدفعة.'][$action]);
    } elseif ($action === 'dates') {
        $s = trim((string)($_POST['starts_at'] ?? ''));
        $en = trim((string)($_POST['ends_at'] ?? ''));
        q('UPDATE batches SET starts_at = ?, ends_at = ? WHERE id = ?', [
            $s ? date('Y-m-d H:i:s', strtotime($s)) : null, $en ? date('Y-m-d H:i:s', strtotime($en)) : null, $id,
        ]);
        log_activity('batch_dates', "#$id");
        flash('تم تحديث الفترة.');
    } elseif ($action === 'prize_image') {
        $pid = (int)($_POST['prize_id'] ?? 0);
        try {
            $img = save_uploaded_image($_FILES['image'] ?? []);
            if ($img) {
                q('UPDATE prizes SET image = ? WHERE id = ? AND batch_id = ?', [$img, $pid, $id]);
                flash('تم تحديث صورة الجائزة.');
            }
        } catch (RuntimeException $ex) {
            flash($ex->getMessage(), 'error');
        }
    }
    redirect('batch.php?id=' . $id);
}

$counts = q("SELECT status, COUNT(*) n FROM tickets WHERE batch_id = ? GROUP BY status", [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$used = (int)($counts['used'] ?? 0);
$prizes = q('SELECT * FROM prizes WHERE batch_id = ? ORDER BY id', [$id])->fetchAll();
$pct = $batch['total_count'] ? round($used / $batch['total_count'] * 100) : 0;
$pages = (int)ceil($batch['total_count'] / 400);
$toLocal = fn(?string $d) => $d ? date('Y-m-d\TH:i', strtotime($d)) : '';

admin_header($batch['name'], 'batches');
?>
<div class="toolbar">
  <?= status_badge($batch['status']) ?>
  <span class="spacer"></span>
  <?php if ($batch['status'] === 'active'): ?>
    <form method="post"><?= csrf_field() ?><button name="action" value="pause" class="btn btn--ghost btn--sm">إيقاف مؤقت</button></form>
  <?php elseif ($batch['status'] === 'paused'): ?>
    <form method="post"><?= csrf_field() ?><button name="action" value="resume" class="btn btn--primary btn--sm">تفعيل</button></form>
  <?php endif; ?>
  <?php if ($batch['status'] !== 'ended'): ?>
    <form method="post" onsubmit="return confirm('إنهاء الدفعة نهائياً؟ لن تقبل أي تذاكر منها بعد ذلك.')"><?= csrf_field() ?><button name="action" value="end" class="btn btn--danger btn--sm">إنهاء</button></form>
  <?php endif; ?>
</div>

<div class="stats">
  <div class="stat"><span>إجمالي التذاكر</span><strong><?= number_format((int)$batch['total_count']) ?></strong></div>
  <div class="stat"><span>المستخدمة</span><strong><?= number_format($used) ?></strong><small><?= $pct ?>%</small></div>
  <div class="stat"><span>الجوائز الموزّعة</span><strong><?= number_format(array_sum(array_column($prizes, 'won_count'))) ?></strong><small>من <?= number_format(array_sum(array_column($prizes, 'quantity'))) ?></small></div>
  <div class="stat"><span>الفترة</span><strong class="stat__sm"><?= e($batch['starts_at'] ? date('Y-m-d', strtotime($batch['starts_at'])) : 'فوراً') ?> ← <?= e($batch['ends_at'] ? date('Y-m-d', strtotime($batch['ends_at'])) : 'مفتوحة') ?></strong></div>
</div>

<div class="grid-2">
  <section class="card">
    <h2>الجوائز</h2>
    <table class="table">
      <thead><tr><th></th><th>الجائزة</th><th>العدد</th><th>رُبحت</th><th>متبقية</th></tr></thead>
      <tbody>
      <?php foreach ($prizes as $p): ?>
        <tr>
          <td>
            <form method="post" enctype="multipart/form-data" class="thumb-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="prize_image"><input type="hidden" name="prize_id" value="<?= (int)$p['id'] ?>">
              <label class="thumb" title="تغيير الصورة">
                <?php if ($p['image']): ?><img src="<?= e(prize_image_url($p['image'])) ?>" alt=""><?php else: ?><span>🎁</span><?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
              </label>
            </form>
          </td>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><?= number_format((int)$p['quantity']) ?></td>
          <td><?= number_format((int)$p['won_count']) ?></td>
          <td><?= number_format(max(0, (int)$p['quantity'] - (int)$p['won_count'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted small">اضغط على الصورة لتغييرها. أماكن التذاكر الرابحة سرية ولا تظهر في أي تصدير.</p>
  </section>

  <section class="card">
    <h2>طباعة وتصدير</h2>
    <p class="muted">كل تذكرة فيها الرمز ورمز QR يفتح الموقع مباشرة.</p>
    <div class="export-list">
      <?php for ($pg = 1; $pg <= $pages; $pg++): $from = ($pg - 1) * 400 + 1; $to = min($pg * 400, (int)$batch['total_count']); ?>
        <a class="btn btn--ghost btn--sm" target="_blank" href="export.php?id=<?= $id ?>&type=print&page=<?= $pg ?>">🖨 طباعة <?= number_format($from) ?>–<?= number_format($to) ?></a>
      <?php endfor; ?>
      <a class="btn btn--primary btn--sm" href="export.php?id=<?= $id ?>&type=csv">⬇ ملف Excel (CSV) للمطبعة</a>
    </div>

    <h2 class="form__section">فترة الحملة</h2>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="action" value="dates">
      <label>تبدأ<input type="datetime-local" name="starts_at" value="<?= e($toLocal($batch['starts_at'])) ?>"></label>
      <label>تنتهي<input type="datetime-local" name="ends_at" value="<?= e($toLocal($batch['ends_at'])) ?>"></label>
      <div class="span-2"><button class="btn btn--ghost btn--sm">حفظ الفترة</button></div>
    </form>
  </section>
</div>
<?php admin_footer();

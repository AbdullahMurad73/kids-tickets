<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'void') {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $n = q("UPDATE tickets SET status = 'void' WHERE id = ? AND status = 'unused'", [$tid])->rowCount();
        if ($n) { log_activity('ticket_void', "#$tid"); flash('تم إلغاء التذكرة، ولن تُقبل في الموقع.'); }
        else flash('لا يمكن إلغاء تذكرة مستخدمة.', 'error');
        redirect('tickets.php?code=' . urlencode((string)($_POST['code'] ?? '')));
    }

    if ($action === 'add') {
        $bid = (int)($_POST['batch_id'] ?? 0);
        $pid = (int)($_POST['prize_id'] ?? 0) ?: null;
        $batch = q('SELECT id FROM batches WHERE id = ?', [$bid])->fetch();
        if (!$batch) { flash('اختر دفعة.', 'error'); redirect('tickets.php'); }
        if ($pid && !q('SELECT id FROM prizes WHERE id = ? AND batch_id = ?', [$pid, $bid])->fetch()) {
            flash('الجائزة لا تتبع هذه الدفعة.', 'error'); redirect('tickets.php');
        }
        $pdo = db();
        $pdo->beginTransaction();
        do { $code = generate_code(); } while (q('SELECT 1 FROM tickets WHERE code = ?', [$code])->fetchColumn());
        q('INSERT INTO tickets (batch_id, code, prize_id) VALUES (?, ?, ?)', [$bid, $code, $pid]);
        q('UPDATE batches SET total_count = total_count + 1 WHERE id = ?', [$bid]);
        if ($pid) q('UPDATE prizes SET quantity = quantity + 1 WHERE id = ?', [$pid]);
        $pdo->commit();
        log_activity('ticket_added', "batch #$bid" . ($pid ? " prize #$pid" : ''));
        $_SESSION['created_code'] = $code;
        redirect('tickets.php?code=' . $code);
    }
}

$created = $_SESSION['created_code'] ?? null;
unset($_SESSION['created_code']);

$code = normalize_code((string)($_GET['code'] ?? ''));
$ticket = null;
$attempts = [];
if ($code !== '') {
    $ticket = q(
        'SELECT t.*, b.name AS batch_name, p.name AS prize_name, w.id AS winner_id, w.child_name, w.guardian_phone, w.delivery_status
           FROM tickets t JOIN batches b ON b.id = t.batch_id
           LEFT JOIN prizes p ON p.id = t.prize_id LEFT JOIN winners w ON w.ticket_id = t.id
          WHERE t.code = ?',
        [$code]
    )->fetch();
    $attempts = q('SELECT ip, result, created_at FROM attempts WHERE code_entered = ? ORDER BY id DESC LIMIT 10', [$code])->fetchAll();
}

$batches = q("SELECT id, name FROM batches WHERE status <> 'ended' ORDER BY id DESC")->fetchAll();
$prizesByBatch = [];
foreach (q('SELECT id, batch_id, name FROM prizes ORDER BY id')->fetchAll() as $p) $prizesByBatch[$p['batch_id']][] = $p;

admin_header('البحث عن تذكرة', 'tickets');
?>
<div class="grid-2">
  <section class="card">
    <h2>ابحث برمز التذكرة</h2>
    <form method="get" class="search">
      <input name="code" dir="ltr" placeholder="XXXX-XXXX-XX" value="<?= e($code ? format_code($code) : '') ?>" autofocus class="mono">
      <button class="btn btn--primary">بحث</button>
    </form>

    <?php if ($code !== ''): ?>
      <?php if (!$ticket): ?>
        <div class="alert alert--error">لا توجد تذكرة بهذا الرمز.</div>
      <?php else: ?>
        <?php if ($created === $ticket['code']): ?><div class="alert alert--ok">تم إنشاء التذكرة. انسخ الرمز أو اطبعها.</div><?php endif; ?>
        <dl class="details">
          <dt>الرمز</dt><dd class="mono big"><?= e(format_code($ticket['code'])) ?></dd>
          <dt>الحالة</dt><dd><?= status_badge($ticket['status']) ?></dd>
          <dt>الدفعة</dt><dd><a href="batch.php?id=<?= (int)$ticket['batch_id'] ?>"><?= e($ticket['batch_name']) ?></a></dd>
          <dt>النتيجة</dt><dd><?= $ticket['prize_name'] ? '<strong class="text-win">رابحة: ' . e($ticket['prize_name']) . '</strong>' : 'غير رابحة' ?></dd>
          <dt>استُخدمت في</dt><dd><?= e(dt($ticket['used_at'])) ?><?= $ticket['used_ip'] ? ' <small class="muted">(' . e($ticket['used_ip']) . ')</small>' : '' ?></dd>
          <?php if ($ticket['winner_id']): ?>
            <dt>الرابح</dt><dd><?= e($ticket['child_name'] ?: 'لم يكمل التسجيل') ?> <?= $ticket['guardian_phone'] ? '· <span dir="ltr">' . e($ticket['guardian_phone']) . '</span>' : '' ?> · <?= status_badge($ticket['delivery_status']) ?></dd>
          <?php endif; ?>
          <dt>رابط QR</dt><dd><a href="<?= e(base_url('?c=' . $ticket['code'])) ?>" target="_blank" rel="noopener" dir="ltr" class="small"><?= e(base_url('?c=' . $ticket['code'])) ?></a></dd>
        </dl>
        <?php if ($ticket['status'] === 'unused'): ?>
          <form method="post" onsubmit="return confirm('إلغاء هذه التذكرة؟ (مثلاً لو ضاعت أو سُرقت)')">
            <?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>"><input type="hidden" name="code" value="<?= e($ticket['code']) ?>">
            <button class="btn btn--danger btn--sm">إلغاء التذكرة</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($attempts): ?>
        <h3 class="form__section">محاولات إدخال هذا الرمز</h3>
        <table class="table table--compact"><tbody>
        <?php foreach ($attempts as $at): ?><tr><td><?= e($at['result']) ?></td><td dir="ltr" class="muted"><?= e($at['ip']) ?></td><td class="muted"><?= e(dt($at['created_at'])) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>إضافة تذكرة واحدة يدوياً</h2>
    <p class="muted">مثلاً تذكرة هدية لمناسبة خاصة. تُضاف للدفعة المختارة.</p>
    <?php if (!$batches): ?>
      <p>أنشئ دفعة أولاً. <a href="batch-new.php">إنشاء دفعة</a></p>
    <?php else: ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>الدفعة
        <select name="batch_id" id="batchSel" required>
          <?php foreach ($batches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>النتيجة
        <select name="prize_id" id="prizeSel"></select>
      </label>
      <button class="btn btn--primary">إنشاء التذكرة</button>
    </form>
    <script>
      const PR = <?= json_encode($prizesByBatch, JSON_UNESCAPED_UNICODE) ?>;
      const bs = document.getElementById('batchSel'), ps = document.getElementById('prizeSel');
      function fill() { ps.innerHTML = '<option value="">غير رابحة (حظ أوفر)</option>' + (PR[bs.value] || []).map(p => `<option value="${p.id}">رابحة: ${p.name.replace(/</g,'&lt;')}</option>`).join(''); }
      bs.onchange = fill; fill();
    </script>
    <?php endif; ?>
  </section>
</div>
<?php admin_footer();

<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $wid = (int)($_POST['winner_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $w = q('SELECT w.*, p.name AS prize_name FROM winners w JOIN tickets t ON t.id = w.ticket_id LEFT JOIN prizes p ON p.id = t.prize_id WHERE w.id = ?', [$wid])->fetch();
    if ($w) {
        if ($action === 'deliver' && $w['delivery_status'] === 'pending') {
            q("UPDATE winners SET delivery_status = 'delivered', delivered_at = NOW(), notes = ? WHERE id = ?", [mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500) ?: $w['notes'], $wid]);
            log_activity('prize_delivered', "winner #$wid");
            $sent = false;
            if ($w['guardian_phone'] && !empty($_POST['notify'])) {
                $vars = ['child' => first_name($w['child_name']), 'prize' => $w['prize_name']];
                $sent = send_notification($wid, $w['guardian_phone'], fill_template(setting('msg_delivered'), $vars), setting('wa_content_sid_delivered'), ['1' => $vars['child'], '2' => $vars['prize']]);
            }
            flash('تم تسجيل التسليم' . ($sent ? ' وإرسال رسالة واتساب.' : '.'));
        } elseif ($action === 'undo' && $w['delivery_status'] === 'delivered') {
            q("UPDATE winners SET delivery_status = 'pending', delivered_at = NULL WHERE id = ?", [$wid]);
            log_activity('prize_undo', "winner #$wid");
            flash('أُعيدت الحالة إلى "لم تُسلَّم".');
        } elseif ($action === 'note') {
            q('UPDATE winners SET notes = ? WHERE id = ?', [mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500), $wid]);
            flash('تم حفظ الملاحظة.');
        } elseif ($action === 'resend' && $w['guardian_phone']) {
            $t = q('SELECT code FROM tickets WHERE id = ?', [$w['ticket_id']])->fetchColumn();
            $vars = ['child' => first_name($w['child_name']), 'prize' => $w['prize_name'], 'claim' => format_code((string)$t)];
            $ok = send_notification($wid, $w['guardian_phone'], fill_template(setting('msg_win'), $vars), setting('wa_content_sid_win'), ['1' => $vars['child'], '2' => $vars['prize'], '3' => $vars['claim']]);
            flash($ok ? 'أُعيد إرسال رسالة الفوز.' : 'تعذّر الإرسال، راجع سجل الرسائل.', $ok ? 'ok' : 'error');
        }
    }
    redirect('winners.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'q', 'batch', 'p']))));
}

$status = in_array($_GET['status'] ?? '', ['pending', 'delivered', 'incomplete'], true) ? $_GET['status'] : '';
$search = trim((string)($_GET['q'] ?? ''));
$batchF = (int)($_GET['batch'] ?? 0);

$where = ['1=1'];
$params = [];
if ($status === 'incomplete') $where[] = 'w.phone_verified_at IS NULL';
elseif ($status) { $where[] = 'w.delivery_status = ?'; $params[] = $status; }
if ($batchF) { $where[] = 't.batch_id = ?'; $params[] = $batchF; }
if ($search !== '') {
    $digits = preg_replace('/\D/', '', normalize_arabic_digits($search)) ?? '';
    $where[] = '(w.child_name LIKE ? OR t.code = ?' . ($digits !== '' ? ' OR w.guardian_phone LIKE ?' : '') . ')';
    array_push($params, '%' . $search . '%', normalize_code($search));
    if ($digits !== '') $params[] = '%' . $digits . '%';
}
$sqlBase = 'FROM winners w JOIN tickets t ON t.id = w.ticket_id LEFT JOIN prizes p ON p.id = t.prize_id JOIN batches b ON b.id = t.batch_id WHERE ' . implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    log_activity('winners_export');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="winners-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['رقم المطالبة', 'اسم الطفل', 'جوال ولي الأمر', 'الجائزة', 'الدفعة', 'تاريخ الفوز', 'تم التحقق', 'حالة التسليم', 'تاريخ التسليم', 'ملاحظات']);
    foreach (q("SELECT t.code, w.child_name, w.guardian_phone, p.name AS prize, b.name AS batch, w.created_at, w.phone_verified_at, w.delivery_status, w.delivered_at, w.notes $sqlBase ORDER BY w.id DESC", $params) as $r) {
        fputcsv($out, [format_code($r['code']), $r['child_name'], $r['guardian_phone'], $r['prize'], $r['batch'], $r['created_at'], $r['phone_verified_at'] ? 'نعم' : 'لا', $r['delivery_status'] === 'delivered' ? 'سُلِّمت' : 'لم تُسلَّم', $r['delivered_at'], $r['notes']]);
    }
    exit;
}

$per = 30;
$page = max(1, (int)($_GET['p'] ?? 1));
$total = (int)q("SELECT COUNT(*) $sqlBase", $params)->fetchColumn();
$rows = q("SELECT w.*, t.code, p.name AS prize, b.name AS batch $sqlBase ORDER BY w.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params)->fetchAll();
$batches = q('SELECT id, name FROM batches ORDER BY id DESC')->fetchAll();
$qs = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(['status' => $status, 'q' => $search, 'batch' => $batchF ?: ''], $extra)));

admin_header('الرابحون', 'winners');
?>
<form method="get" class="card filters">
  <input name="q" value="<?= e($search) ?>" placeholder="اسم، جوال، أو رمز تذكرة">
  <select name="status">
    <option value="">كل الحالات</option>
    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>لم تُسلَّم</option>
    <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>سُلِّمت</option>
    <option value="incomplete" <?= $status === 'incomplete' ? 'selected' : '' ?>>لم يكمل التسجيل</option>
  </select>
  <select name="batch">
    <option value="">كل الدفعات</option>
    <?php foreach ($batches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $batchF === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn--primary btn--sm">تصفية</button>
  <a class="btn btn--ghost btn--sm" href="<?= e($qs(['export' => 'csv'])) ?>">⬇ تصدير Excel</a>
</form>

<section class="card">
  <p class="muted small"><?= number_format($total) ?> رابح</p>
  <?php if (!$rows): ?><p class="muted">لا توجد نتائج.</p><?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>الطفل</th><th>جوال ولي الأمر</th><th>الجائزة</th><th>رقم المطالبة</th><th>التاريخ</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $w): ?>
      <tr>
        <td><strong><?= e($w['child_name'] ?: '—') ?></strong><?php if (($w['source'] ?? '') === 'whatsapp'): ?> <span class="src">واتساب</span><?php endif; ?><?php if (!$w['phone_verified_at']): ?><br><small class="text-warn">لم يكمل التسجيل</small><?php endif; ?></td>
        <td dir="ltr" class="ltr-cell"><?= $w['guardian_phone'] ? '<a href="https://wa.me/' . e(ltrim($w['guardian_phone'], '+')) . '" target="_blank" rel="noopener">' . e($w['guardian_phone']) . '</a>' : '—' ?></td>
        <td><?= e($w['prize']) ?><br><small class="muted"><?= e($w['batch']) ?></small></td>
        <td class="mono small"><?= e(format_code($w['code'])) ?></td>
        <td class="muted small"><?= e(dt($w['created_at'])) ?></td>
        <td><?= status_badge($w['delivery_status']) ?><?php if ($w['delivered_at']): ?><br><small class="muted"><?= e(dt($w['delivered_at'])) ?></small><?php endif; ?></td>
        <td>
          <details class="row-actions">
            <summary class="btn btn--ghost btn--sm">إجراءات</summary>
            <div class="row-actions__menu">
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="winner_id" value="<?= (int)$w['id'] ?>">
                <label>ملاحظة<textarea name="notes" rows="2" maxlength="500"><?= e($w['notes']) ?></textarea></label>
                <?php if ($w['delivery_status'] === 'pending'): ?>
                  <label class="check"><input type="checkbox" name="notify" value="1" <?= $w['guardian_phone'] ? 'checked' : 'disabled' ?>> إرسال رسالة "تم التسليم"</label>
                  <button name="action" value="deliver" class="btn btn--primary btn--sm">تم التسليم ✓</button>
                <?php else: ?>
                  <button name="action" value="undo" class="btn btn--ghost btn--sm">تراجع عن التسليم</button>
                <?php endif; ?>
                <button name="action" value="note" class="btn btn--ghost btn--sm">حفظ الملاحظة</button>
                <?php if ($w['guardian_phone']): ?><button name="action" value="resend" class="btn btn--ghost btn--sm">إعادة إرسال رسالة الفوز</button><?php endif; ?>
              </form>
            </div>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($total > $per): $pages = (int)ceil($total / $per); ?>
    <nav class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= e($qs(['p' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a><?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php admin_footer();

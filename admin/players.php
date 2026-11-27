<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'adjust') {
        $pts = (int)($_POST['points'] ?? 0);
        $why = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 50) ?: 'تعديل يدوي';
        if ($pts !== 0 && $pts >= -100000 && $pts <= 100000) {
            award_points($id, $pts, 'admin', $why);
            log_activity('points_adjusted', "player #$id: $pts ($why)");
            flash('تم تعديل النقاط.');
        }
    } elseif ($action === 'block') {
        q('UPDATE players SET is_blocked = 1 - is_blocked WHERE id = ?', [$id]);
        log_activity('player_block_toggle', "#$id");
        flash('تم تحديث حالة الحساب.');
    } elseif ($action === 'delete') {
        q('DELETE FROM players WHERE id = ?', [$id]);
        log_activity('player_deleted', "#$id");
        flash('تم حذف المتسابق وكل بياناته.');
    }
    redirect('players.php?' . http_build_query(['q' => $_GET['q'] ?? '']));
}

$search = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = '1=1';
if ($search !== '') {
    $digits = preg_replace('/\D/', '', normalize_arabic_digits($search)) ?? '';
    $where = '(full_name LIKE ?' . ($digits !== '' ? ' OR phone LIKE ?' : '') . ')';
    $params[] = '%' . $search . '%';
    if ($digits !== '') $params[] = '%' . ltrim($digits, '0') . '%';
}

if (($_GET['export'] ?? '') === 'csv') {
    log_activity('players_export');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="players-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['الاسم الرباعي', 'جوال ولي الأمر', 'العمر', 'النقاط', 'المستوى', 'السلسلة', 'آخر لعب', 'تاريخ التسجيل']);
    foreach (q("SELECT * FROM players WHERE $where ORDER BY points DESC", $params) as $r) {
        fputcsv($out, [$r['full_name'], $r['phone'], $r['age'], $r['points'], level_name_for((int)$r['points']), $r['streak'], $r['last_play_date'], $r['created_at']]);
    }
    exit;
}

$rows = q("SELECT p.*, (SELECT COUNT(*) FROM answers a WHERE a.player_id = p.id) AS answers, (SELECT ROUND(100 * AVG(a.is_correct)) FROM answers a WHERE a.player_id = p.id) AS acc
             FROM players p WHERE $where ORDER BY p.points DESC, p.id LIMIT 300", $params)->fetchAll();
$total = (int)q('SELECT COUNT(*) FROM players')->fetchColumn();
$today = (int)q('SELECT COUNT(DISTINCT player_id) FROM answers WHERE created_at >= CURDATE()')->fetchColumn();

admin_header('المتسابقون', 'players');
?>
<div class="stats">
  <div class="stat"><span>إجمالي المتسابقين</span><strong><?= number_format($total) ?></strong></div>
  <div class="stat"><span>لعبوا اليوم</span><strong><?= number_format($today) ?></strong></div>
  <div class="stat"><span>إجابات اليوم</span><strong><?= number_format((int)q('SELECT COUNT(*) FROM answers WHERE created_at >= CURDATE()')->fetchColumn()) ?></strong></div>
</div>
<form method="get" class="card filters">
  <input name="q" value="<?= e($search) ?>" placeholder="الاسم أو رقم الجوال">
  <button class="btn btn--primary btn--sm">بحث</button>
  <a class="btn btn--ghost btn--sm" href="?<?= e(http_build_query(['q' => $search, 'export' => 'csv'])) ?>">⬇ تصدير Excel</a>
</form>
<section class="card">
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>#</th><th>الاسم الرباعي</th><th>جوال ولي الأمر</th><th>العمر</th><th>النقاط</th><th>المستوى</th><th>الإجابات</th><th>الدقة</th><th>السلسلة</th><th>تعديل النقاط</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr class="<?= $r['is_blocked'] ? 'muted' : '' ?>">
        <td><?= $i + 1 ?></td>
        <td><?= e($r['full_name']) ?><?= $r['is_blocked'] ? ' <span class="badge badge--red">موقوف</span>' : '' ?></td>
        <td class="ltr-cell"><?= e($r['phone']) ?></td>
        <td><?= (int)$r['age'] ?></td>
        <td><strong><?= number_format((int)$r['points']) ?></strong></td>
        <td><?= e(level_name_for((int)$r['points'])) ?></td>
        <td><?= number_format((int)$r['answers']) ?></td>
        <td><?= $r['acc'] !== null ? (int)$r['acc'] . '%' : '—' ?></td>
        <td><?= (int)$r['streak'] ?></td>
        <td><form method="post" class="toolbar"><?= csrf_field() ?><input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="number" name="points" placeholder="±" style="width:70px"><input name="reason" placeholder="السبب" style="width:100px"><button class="btn btn--ghost btn--sm">تطبيق</button></form></td>
        <td class="row-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button name="action" value="block" class="btn btn--ghost btn--sm"><?= $r['is_blocked'] ? 'تفعيل' : 'إيقاف' ?></button>
          <button name="action" value="delete" class="btn btn--danger btn--sm" onclick="return confirm('حذف المتسابق وكل نقاطه وإجاباته نهائياً؟')">حذف</button>
        </form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="11" class="muted">لا يوجد متسابقون بعد.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?php admin_footer();

<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$editId = (int)($_GET['edit'] ?? 0);

function q_fields(): array
{
    $f = [];
    foreach (['body' => 300, 'opt_a' => 120, 'opt_b' => 120, 'opt_c' => 120, 'opt_d' => 120, 'category' => 40] as $k => $max) {
        $f[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    }
    $f['correct'] = in_array($_POST['correct'] ?? '', ['a', 'b', 'c', 'd'], true) ? $_POST['correct'] : 'a';
    $f['difficulty'] = max(1, min(3, (int)($_POST['difficulty'] ?? 1)));
    if ($f['category'] === '') $f['category'] = 'عام';
    return $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $f = q_fields();
        if ($f['body'] === '' || $f['opt_a'] === '' || $f['opt_b'] === '' || $f['opt_c'] === '' || $f['opt_d'] === '') {
            flash('اكتب السؤال والخيارات الأربعة.', 'error');
            redirect('questions.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($id) {
            q('UPDATE questions SET body=?, opt_a=?, opt_b=?, opt_c=?, opt_d=?, category=?, correct=?, difficulty=? WHERE id=?', [...array_values($f), $id]);
            flash('تم حفظ السؤال.');
        } else {
            q('INSERT INTO questions (body, opt_a, opt_b, opt_c, opt_d, category, correct, difficulty) VALUES (?,?,?,?,?,?,?,?)', array_values($f));
            flash('تمت إضافة السؤال.');
        }
        log_activity('question_saved');
    } elseif ($action === 'toggle') {
        q('UPDATE questions SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } elseif ($action === 'delete') {
        q('DELETE FROM questions WHERE id = ?', [$id]);
        log_activity('question_deleted', "#$id");
        flash('تم حذف السؤال.');
    } elseif ($action === 'import' && !empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $n = 0; $bad = 0;
        $st = db()->prepare('INSERT INTO questions (body, opt_a, opt_b, opt_c, opt_d, correct, category, difficulty) VALUES (?,?,?,?,?,?,?,?)');
        while (($row = fgetcsv($fh)) !== false) {
            if (!$row || count($row) < 6) { $bad++; continue; }
            $row = array_map(fn($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v)), $row);
            $c = strtolower($row[5]);
            $map = ['أ' => 'a', 'ب' => 'b', 'ج' => 'c', 'د' => 'd', '1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'];
            $c = $map[$c] ?? $c;
            if ($row[0] === '' || !in_array($c, ['a', 'b', 'c', 'd'], true) || $row[0] === 'السؤال') { $bad++; continue; }
            $st->execute([mb_substr($row[0], 0, 300), mb_substr($row[1], 0, 120), mb_substr($row[2], 0, 120), mb_substr($row[3], 0, 120), mb_substr($row[4], 0, 120), $c, mb_substr($row[6] ?? 'عام', 0, 40) ?: 'عام', max(1, min(3, (int)($row[7] ?? 1)))]);
            $n++;
        }
        fclose($fh);
        log_activity('questions_imported', (string)$n);
        flash("تم استيراد $n سؤال" . ($bad ? " (تخطّينا $bad سطر غير صالح)" : '') . '.');
    }
    redirect('questions.php');
}

$cat = trim((string)($_GET['cat'] ?? ''));
$rows = q('SELECT q.*, (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) AS n, (SELECT ROUND(100 * AVG(a.is_correct)) FROM answers a WHERE a.question_id = q.id) AS pct
             FROM questions q ' . ($cat !== '' ? 'WHERE q.category = ? ' : '') . 'ORDER BY q.id DESC', $cat !== '' ? [$cat] : [])->fetchAll();
$cats = q('SELECT category, COUNT(*) n FROM questions GROUP BY category ORDER BY n DESC')->fetchAll();
$edit = $editId ? q('SELECT * FROM questions WHERE id = ?', [$editId])->fetch() : null;
$f = $edit ?: ['id' => 0, 'body' => '', 'opt_a' => '', 'opt_b' => '', 'opt_c' => '', 'opt_d' => '', 'correct' => 'a', 'category' => 'عام', 'difficulty' => 1];

admin_header('بنك الأسئلة', 'questions');
?>
<div class="grid-2">
  <form method="post" class="card form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
    <div class="card__head"><h2><?= $edit ? 'تعديل السؤال #' . (int)$f['id'] : 'إضافة سؤال' ?></h2><?php if ($edit): ?><a href="questions.php" class="small">+ سؤال جديد</a><?php endif; ?></div>
    <label>السؤال<textarea name="body" rows="2" maxlength="300" required><?= e($f['body']) ?></textarea></label>
    <div class="form-grid">
      <?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $k => $lbl): ?>
      <label>الخيار <?= $lbl ?><input name="opt_<?= $k ?>" maxlength="120" required value="<?= e($f['opt_' . $k]) ?>"></label>
      <?php endforeach; ?>
    </div>
    <div class="form-grid">
      <label>الإجابة الصحيحة<select name="correct"><?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $f['correct'] === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></label>
      <label>التصنيف<input name="category" maxlength="40" value="<?= e($f['category']) ?>" list="cats"></label>
      <label>الصعوبة<select name="difficulty"><?php foreach ([1 => 'سهل (كل الأعمار)', 2 => 'متوسط (9 سنوات فأكثر)', 3 => 'صعب (9 سنوات فأكثر)'] as $k => $lbl): ?><option value="<?= $k ?>" <?= (int)$f['difficulty'] === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></label>
    </div>
    <datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?></datalist>
    <button class="btn btn--primary">حفظ</button>
  </form>

  <form method="post" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?><input type="hidden" name="action" value="import">
    <div class="card__head"><h2>استيراد من Excel (CSV)</h2></div>
    <p class="muted small">احفظ ملف Excel بصيغة CSV UTF-8. كل سطر: السؤال، الخيار أ، ب، ج، د، الإجابة الصحيحة (a/b/c/d أو أ/ب/ج/د)، التصنيف، الصعوبة (1-3).</p>
    <pre class="msg-body" dir="rtl">ما عاصمة السعودية؟,جدة,الرياض,الدمام,مكة,b,وطني,1</pre>
    <label class="file-btn">اختر ملف CSV<input type="file" name="csv" accept=".csv,text/csv" required></label>
    <button class="btn btn--primary">استيراد</button>
  </form>
</div>

<section class="card">
  <div class="card__head"><h2><?= count($rows) ?> سؤال</h2>
    <form method="get" class="toolbar"><select name="cat" onchange="this.form.submit()"><option value="">كل التصنيفات</option><?php foreach ($cats as $c): ?><option value="<?= e($c['category']) ?>" <?= $cat === $c['category'] ? 'selected' : '' ?>><?= e($c['category']) ?> (<?= (int)$c['n'] ?>)</option><?php endforeach; ?></select></form>
  </div>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>#</th><th>السؤال</th><th>الصحيحة</th><th>التصنيف</th><th>الصعوبة</th><th>أُجيب</th><th>نسبة الصواب</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['is_active'] ? '' : 'muted' ?>">
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['body']) ?></td>
        <td><?= e($r['opt_' . $r['correct']]) ?></td>
        <td><?= e($r['category']) ?></td>
        <td><?= (int)$r['difficulty'] ?></td>
        <td><?= number_format((int)$r['n']) ?></td>
        <td><?= $r['pct'] !== null ? (int)$r['pct'] . '%' : '—' ?></td>
        <td class="row-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <a class="btn btn--ghost btn--sm" href="?edit=<?= (int)$r['id'] ?>">تعديل</a>
          <button name="action" value="toggle" class="btn btn--ghost btn--sm"><?= $r['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
          <button name="action" value="delete" class="btn btn--danger btn--sm" onclick="return confirm('حذف السؤال وإجاباته؟')">حذف</button>
        </form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php admin_footer();

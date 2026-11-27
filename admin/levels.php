<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

const GAME_SETTINGS = [
    'daily_questions'  => ['عدد الأسئلة اليومية لكل متسابق', 1, 50],
    'question_seconds' => ['وقت كل سؤال (ثانية)', 5, 120],
    'points_correct'   => ['نقاط الإجابة الصحيحة', 0, 1000],
    'points_speed'     => ['أقصى مكافأة سرعة (تقل كلما تأخر)', 0, 1000],
    'points_streak'    => ['مكافأة الدخول في أيام متتالية (لكل يوم)', 0, 1000],
    'grace_days'       => ['مهلة الغياب بالأيام (لا تنقطع السلسلة)', 1, 14],
    'points_ticket'    => ['نقاط كل رمز تذكرة صحيح', 0, 1000],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'levels') {
        foreach ((array)($_POST['lv'] ?? []) as $id => $f) {
            $name = mb_substr(trim((string)($f['name'] ?? '')), 0, 40);
            $prize = mb_substr(trim((string)($f['prize'] ?? '')), 0, 120);
            if ($name === '' || $prize === '') continue;
            q('UPDATE levels SET name = ?, min_points = ?, prize = ?, winners_limit = ?, is_active = ? WHERE id = ?', [
                $name, max(1, (int)($f['min_points'] ?? 1)), $prize, max(1, (int)($f['winners_limit'] ?? 1)), !empty($f['is_active']) ? 1 : 0, (int)$id,
            ]);
        }
        log_activity('levels_updated');
        flash('تم حفظ المستويات.');
    } elseif ($action === 'game') {
        foreach (GAME_SETTINGS as $k => [$label, $min, $max]) {
            if (isset($_POST[$k])) save_setting($k, (string)max($min, min($max, (int)$_POST[$k])));
        }
        save_setting('contest_license', mb_substr(trim((string)($_POST['contest_license'] ?? '')), 0, 60));
        save_setting('contest_rules', mb_substr(trim((string)($_POST['contest_rules'] ?? '')), 0, 8000));
        log_activity('game_settings_updated');
        flash('تم حفظ إعدادات التحدي.');
    }
    redirect('levels.php');
}

$levels = levels_all(false);
$reached = [];
foreach ($levels as $lv) {
    $reached[$lv['id']] = q(
        'SELECT p.id, p.full_name, p.phone, p.points, lp.reached_at FROM level_progress lp JOIN players p ON p.id = lp.player_id
          WHERE lp.level_id = ? ORDER BY lp.reached_at ASC LIMIT 200',
        [$lv['id']]
    )->fetchAll();
}

admin_header('المستويات والجوائز', 'levels');
?>
<form method="post" class="card form">
  <?= csrf_field() ?><input type="hidden" name="action" value="levels">
  <div class="card__head"><h2>المستويات</h2></div>
  <p class="muted small">الفائزون بجائزة كل مستوى هم أول من يصل لحدّه، حتى يكتمل «عدد الفائزين». الترتيب بدقة الملي ثانية.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>اسم المستوى</th><th>الحد (نقطة)</th><th>الجائزة</th><th>عدد الفائزين</th><th>مفعّل</th><th>وصلوا</th></tr></thead>
    <tbody>
    <?php foreach ($levels as $lv): ?>
      <tr>
        <td><?= (int)$lv['sort'] ?></td>
        <td><input name="lv[<?= (int)$lv['id'] ?>][name]" value="<?= e($lv['name']) ?>" maxlength="40" style="width:120px"></td>
        <td><input type="number" name="lv[<?= (int)$lv['id'] ?>][min_points]" value="<?= (int)$lv['min_points'] ?>" min="1" style="width:100px"></td>
        <td><input name="lv[<?= (int)$lv['id'] ?>][prize]" value="<?= e($lv['prize']) ?>" maxlength="120" style="width:220px"></td>
        <td><input type="number" name="lv[<?= (int)$lv['id'] ?>][winners_limit]" value="<?= (int)$lv['winners_limit'] ?>" min="1" style="width:80px"></td>
        <td><input type="checkbox" name="lv[<?= (int)$lv['id'] ?>][is_active]" value="1" <?= $lv['is_active'] ? 'checked' : '' ?>></td>
        <td><?= count($reached[$lv['id']]) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small">صور جوائز المستويات تتغير من صفحة «الصور والمظهر».</p>
  <button class="btn btn--primary">حفظ المستويات</button>
</form>

<?php foreach ($levels as $lv): $list = $reached[$lv['id']]; ?>
<section class="card">
  <div class="card__head"><h2>الواصلون للمستوى <?= e($lv['name']) ?></h2><span class="badge badge--violet"><?= count($list) ?> / الفائزون <?= (int)$lv['winners_limit'] ?></span></div>
  <?php if (!$list): ?><p class="muted">لم يصل أحد بعد.</p><?php else: ?>
  <div class="table-wrap"><table class="table table--compact">
    <thead><tr><th>الترتيب</th><th>الاسم الرباعي</th><th>جوال ولي الأمر</th><th>النقاط الحالية</th><th>وقت الوصول</th><th>الحالة</th></tr></thead>
    <tbody>
    <?php foreach ($list as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><a href="players.php?q=<?= urlencode($r['phone']) ?>"><?= e($r['full_name']) ?></a></td>
        <td class="ltr-cell"><?= e($r['phone']) ?></td>
        <td><?= number_format((int)$r['points']) ?></td>
        <td class="ltr-cell"><?= e(substr((string)$r['reached_at'], 0, 23)) ?></td>
        <td><?= $i < (int)$lv['winners_limit'] ? '<span class="badge badge--green">فائز</span>' : '<span class="badge badge--gray">بعد اكتمال العدد</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<form method="post" class="card form">
  <?= csrf_field() ?><input type="hidden" name="action" value="game">
  <div class="card__head"><h2>إعدادات التحدي والنقاط</h2></div>
  <div class="form-grid">
    <?php foreach (GAME_SETTINGS as $k => [$label, $min, $max]): ?>
      <label><?= e($label) ?><input type="number" name="<?= $k ?>" min="<?= $min ?>" max="<?= $max ?>" value="<?= e(setting($k)) ?>"></label>
    <?php endforeach; ?>
  </div>
  <h2 class="form__section">النظامي</h2>
  <label>رقم ترخيص المسابقة (يظهر في التذييل والشروط)<input name="contest_license" maxlength="60" value="<?= e(setting('contest_license')) ?>"></label>
  <label>نص الشروط والأحكام (اتركه فاضي لاستخدام النص الافتراضي)<textarea name="contest_rules" rows="8"><?= e(setting('contest_rules')) ?></textarea></label>
  <button class="btn btn--primary">حفظ</button>
</form>
<?php admin_footer();

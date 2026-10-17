<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

const BUILTIN_HERO_ART = [
    'art:hero-city.svg'     => 'مدينة النيون',
    'art:hero-carnival.svg' => 'مهرجان الألعاب',
    'art:hero-space.svg'    => 'مغامرة الفضاء',
];

$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $data = [
            'eyebrow'  => mb_substr(trim((string)($_POST['eyebrow'] ?? '')), 0, 80),
            'title1'   => mb_substr(trim((string)($_POST['title1'] ?? '')), 0, 80),
            'title2'   => mb_substr(trim((string)($_POST['title2'] ?? '')), 0, 80),
            'body'     => mb_substr(trim((string)($_POST['body'] ?? '')), 0, 240),
            'btn_text' => mb_substr(trim((string)($_POST['btn_text'] ?? '')), 0, 40),
            'is_active'=> !empty($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['title1'] === '') {
            flash('اكتب العنوان الأول.', 'error');
            redirect('home.php' . ($id ? '?edit=' . $id : ''));
        }
        $image = null;
        try {
            $image = save_uploaded_image($_FILES['image'] ?? []);
        } catch (RuntimeException $ex) {
            flash($ex->getMessage(), 'error');
            redirect('home.php' . ($id ? '?edit=' . $id : ''));
        }
        $art = (string)($_POST['art'] ?? '');
        if (!$image && isset(BUILTIN_HERO_ART[$art])) $image = $art;

        if ($id) {
            $sql = 'UPDATE banners SET eyebrow = ?, title1 = ?, title2 = ?, body = ?, btn_text = ?, is_active = ?' . ($image ? ', image = ?' : '') . ' WHERE id = ?';
            $params = array_values($data);
            if ($image) $params[] = $image;
            $params[] = $id;
            q($sql, $params);
            log_activity('banner_updated', "#$id");
            flash('تم حفظ البانر.');
        } else {
            $sort = (int)q('SELECT COALESCE(MAX(sort), 0) + 1 FROM banners')->fetchColumn();
            q('INSERT INTO banners (eyebrow, title1, title2, body, btn_text, is_active, image, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              array_merge(array_values($data), [$image ?: 'art:hero-city.svg', $sort]));
            log_activity('banner_added');
            flash('تمت إضافة البانر.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE banners SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('تم تحديث الحالة.');
    } elseif ($action === 'delete') {
        $active = (int)q('SELECT COUNT(*) FROM banners WHERE is_active = 1 AND id <> ?', [$id])->fetchColumn();
        if ($active < 1) {
            flash('لازم يبقى بانر واحد مفعّل على الأقل.', 'error');
        } else {
            q('DELETE FROM banners WHERE id = ?', [$id]);
            log_activity('banner_deleted', "#$id");
            flash('تم حذف البانر.');
        }
    } elseif ($action === 'up' || $action === 'down') {
        $rows = q('SELECT id FROM banners ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search((string)$id, array_map('strval', $rows), true);
        $j = $action === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($rows[$j])) {
            [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
            foreach ($rows as $pos => $rid) q('UPDATE banners SET sort = ? WHERE id = ?', [$pos + 1, $rid]);
        }
    }
    redirect('home.php');
}

$banners = q('SELECT * FROM banners ORDER BY sort, id')->fetchAll();
$edit = $editId ? q('SELECT * FROM banners WHERE id = ?', [$editId])->fetch() : null;
$f = $edit ?: ['id' => 0, 'eyebrow' => '', 'title1' => '', 'title2' => '', 'body' => '', 'btn_text' => 'اكشف تذكرتك', 'image' => 'art:hero-city.svg', 'is_active' => 1];

admin_header('الواجهة الرئيسية', 'home');
?>
<div class="grid-2">
  <section class="card">
    <div class="card__head"><h2>البانرات المتحركة</h2><a class="btn btn--ghost btn--sm" href="../" target="_blank" rel="noopener">عرض الرئيسية ↗</a></div>
    <p class="muted small">تظهر بالترتيب في أعلى الصفحة الرئيسية وتتبدّل تلقائياً كل 6 ثوانٍ.</p>
    <div class="banner-list">
      <?php foreach ($banners as $i => $b): ?>
      <article class="banner-item<?= $b['is_active'] ? '' : ' is-off' ?>">
        <div class="banner-item__img" style="background-image:url('<?= e(media_url($b['image'])) ?>')"></div>
        <div class="banner-item__body">
          <small class="muted"><?= e($b['eyebrow']) ?></small>
          <strong><?= e($b['title1']) ?> <span class="grad-text"><?= e($b['title2']) ?></span></strong>
          <small class="muted"><?= $b['is_active'] ? 'مفعّل' : 'مخفي' ?></small>
        </div>
        <div class="banner-item__actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button name="action" value="up" class="icon-btn" title="أعلى" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button name="action" value="down" class="icon-btn" title="أسفل" <?= $i === count($banners) - 1 ? 'disabled' : '' ?>>↓</button>
            <button name="action" value="toggle" class="btn btn--ghost btn--sm"><?= $b['is_active'] ? 'إخفاء' : 'إظهار' ?></button>
            <a class="btn btn--ghost btn--sm" href="?edit=<?= (int)$b['id'] ?>">تعديل</a>
            <button name="action" value="delete" class="btn btn--danger btn--sm" onclick="return confirm('حذف هذا البانر؟')">حذف</button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <form method="post" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
    <div class="card__head"><h2><?= $edit ? 'تعديل البانر' : 'إضافة بانر جديد' ?></h2><?php if ($edit): ?><a href="home.php" class="small">+ بانر جديد</a><?php endif; ?></div>
    <label>سطر صغير فوق العنوان<input name="eyebrow" maxlength="80" value="<?= e($f['eyebrow']) ?>" placeholder="اكشف · افرح · اربح"></label>
    <div class="form-grid">
      <label>العنوان (أبيض)<input name="title1" maxlength="80" required value="<?= e($f['title1']) ?>" placeholder="جرّب حظك"></label>
      <label>العنوان (ملوّن)<input name="title2" maxlength="80" value="<?= e($f['title2']) ?>" placeholder="واكشف تذكرتك"></label>
    </div>
    <label>الوصف<textarea name="body" rows="2" maxlength="240"><?= e($f['body']) ?></textarea></label>
    <label>نص الزر<input name="btn_text" maxlength="40" value="<?= e($f['btn_text']) ?>"></label>

    <h2 class="form__section">الصورة</h2>
    <div class="art-pick">
      <?php foreach (BUILTIN_HERO_ART as $key => $label): ?>
        <label class="art-pick__item">
          <input type="radio" name="art" value="<?= e($key) ?>" <?= $f['image'] === $key ? 'checked' : '' ?>>
          <span style="background-image:url('<?= e(media_url($key)) ?>')"></span><small><?= e($label) ?></small>
        </label>
      <?php endforeach; ?>
    </div>
    <label class="file-btn">أو ارفع صورتك (JPG/PNG/WEBP، يُفضّل 1600×600)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <?php if ($edit && !str_starts_with((string)$f['image'], 'art:')): ?><p class="muted small">الصورة الحالية مرفوعة. اختر رسمة أو ارفع صورة جديدة لتغييرها.</p><?php endif; ?>
    <label class="check"><input type="checkbox" name="is_active" value="1" <?= $f['is_active'] ? 'checked' : '' ?>> مفعّل</label>
    <button class="btn btn--primary">حفظ</button>
  </form>
</div>
<script>
document.querySelectorAll('.file-btn input').forEach(i => i.addEventListener('change', () => {
  i.parentElement.classList.toggle('has-file', !!i.files.length);
  if (i.files.length) document.querySelectorAll('.art-pick input').forEach(r => r.checked = false);
}));
</script>
<?php admin_footer();

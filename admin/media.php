<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = (string)($_POST['slot'] ?? '');
    if (isset(MEDIA_SLOTS[$key])) {
        [$label, $w, $h] = MEDIA_SLOTS[$key];
        if (($_POST['action'] ?? '') === 'reset') {
            save_setting('media_' . $key, '');
            log_activity('media_reset', $key);
            flash('رجعت الصورة الأصلية: ' . $label);
        } else {
            try {
                $name = save_slot_image($_FILES['image'] ?? [], $w, $h);
                if ($name) {
                    save_setting('media_' . $key, $name);
                    log_activity('media_updated', $key);
                    flash('تم تحديث: ' . $label);
                } else {
                    flash('اختر صورة أولاً.', 'error');
                }
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'error');
            }
        }
    }
    redirect('media.php#' . preg_replace('/[^a-z0-9_]/', '', $key));
}

admin_header('الصور والمظهر', 'media');
?>
<section class="card">
  <div class="card__head"><h2>كل صور الموقع في مكان واحد</h2></div>
  <p class="muted">اختر الصورة واضغط «رفع». إذا كانت أكبر من المقاس الموصى به، الموقع يصغّرها تلقائياً ويحافظ على الشفافية. الحد الأقصى 2 ميجابايت، والصيغ JPG أو PNG أو WEBP.</p>
  <ul class="muted small">
    <li><strong>بانرات الصفحة الرئيسية</strong> تتغير من صفحة «الواجهة الرئيسية»، ومقاسها 1600 × 600، والشخصية في الجهة اليسرى لأن النص على اليمين.</li>
    <li><strong>صور جوائز التذاكر</strong> ترفعها عند إنشاء الدفعة، ومقاسها 800 × 500.</li>
    <li>استخدم PNG بخلفية شفافة للشعار والمسكوت وصور الجوائز، عشان تندمج مع الخلفية الداكنة.</li>
  </ul>
</section>

<div class="grid-2">
<?php foreach (MEDIA_SLOTS as $key => [$label, $w, $h, $default, $tip]): $custom = setting('media_' . $key) !== ''; ?>
  <form method="post" enctype="multipart/form-data" class="card form" id="<?= e($key) ?>">
    <?= csrf_field() ?><input type="hidden" name="slot" value="<?= e($key) ?>">
    <div class="card__head"><h2 style="font-size:1rem"><?= e($label) ?></h2><?= $custom ? '<span class="badge badge--violet">مخصصة</span>' : '<span class="badge badge--gray">الأصلية</span>' ?></div>
    <div style="display:grid;place-items:center;height:150px;border-radius:12px;background:#120c33 repeating-conic-gradient(#1a1246 0 25%,#120c33 0 50%) 0 0/20px 20px;overflow:hidden">
      <img src="<?= e(media_slot_url($key)) ?>" alt="" style="max-width:100%;max-height:150px;object-fit:contain">
    </div>
    <p class="small"><strong>المقاس الموصى به: <bdi dir="ltr"><?= $w ?> × <?= $h ?></bdi> بكسل</strong> · <?= e($tip) ?></p>
    <label class="file-btn">اختر صورة<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
    <div class="form__actions">
      <button class="btn btn--primary btn--sm" name="action" value="upload">رفع</button>
      <?php if ($custom): ?><button class="btn btn--ghost btn--sm" name="action" value="reset" formnovalidate onclick="return confirm('الرجوع للصورة الأصلية؟')">الصورة الأصلية</button><?php endif; ?>
    </div>
  </form>
<?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.file-btn input').forEach(i => i.addEventListener('change', () => {
  i.parentElement.classList.toggle('has-file', !!i.files.length);
  const img = i.closest('form').querySelector('img');
  if (i.files[0]) img.src = URL.createObjectURL(i.files[0]);
}));
</script>
<?php admin_footer();

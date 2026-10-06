<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$admin = require_admin();

const MAX_BATCH = 50000;

/** خلط آمن تشفيرياً (Fisher–Yates + random_int) حتى لا يمكن توقّع أماكن التذاكر الرابحة */
function secure_shuffle(array &$a): void
{
    for ($i = count($a) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
    }
}

/** يولّد $n رمزاً فريداً غير موجود مسبقاً في قاعدة البيانات */
function unique_codes(int $n): array
{
    $codes = [];
    while (count($codes) < $n) {
        $codes[generate_code()] = true;
    }
    $codes = array_keys($codes);
    foreach (array_chunk($codes, 1000) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $taken = q("SELECT code FROM tickets WHERE code IN ($in)", $chunk)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($taken as $dup) {
            do { $new = generate_code(); } while (in_array($new, $codes, true));
            $codes[array_search($dup, $codes, true)] = $new;
        }
    }
    return $codes;
}

$errors = [];
$old = ['name' => '', 'total' => '1000', 'starts_at' => '', 'ends_at' => '', 'prizes' => [['name' => '', 'qty' => '']]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $total = (int)($_POST['total'] ?? 0);
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt = trim((string)($_POST['ends_at'] ?? ''));
    $rawPrizes = is_array($_POST['prizes'] ?? null) ? $_POST['prizes'] : [];

    $prizes = [];
    foreach ($rawPrizes as $i => $p) {
        $pn = trim((string)($p['name'] ?? ''));
        $pq = (int)($p['qty'] ?? 0);
        if ($pn === '' && $pq === 0) continue;
        if ($pn === '' || $pq < 1) { $errors[] = 'كل جائزة تحتاج اسماً وعدداً أكبر من صفر.'; break; }
        $prizes[] = ['name' => mb_substr($pn, 0, 150), 'qty' => $pq, 'idx' => $i];
    }
    $prizeSum = array_sum(array_column($prizes, 'qty'));

    if ($name === '') $errors[] = 'اكتب اسم الدفعة.';
    if ($total < 1 || $total > MAX_BATCH) $errors[] = 'عدد التذاكر يجب أن يكون بين 1 و' . number_format(MAX_BATCH) . '.';
    if (!$prizes) $errors[] = 'أضف جائزة واحدة على الأقل.';
    if ($prizeSum > $total) $errors[] = "مجموع الجوائز ($prizeSum) أكبر من عدد التذاكر ($total).";
    $s = $startsAt ? strtotime($startsAt) : null;
    $en = $endsAt ? strtotime($endsAt) : null;
    if (($startsAt && !$s) || ($endsAt && !$en)) $errors[] = 'تاريخ غير صحيح.';
    if ($s && $en && $en <= $s) $errors[] = 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية.';

    // الصور
    $images = [];
    if (!$errors) {
        foreach ($prizes as $p) {
            $f = [
                'name' => $_FILES['prize_image']['name'][$p['idx']] ?? '',
                'tmp_name' => $_FILES['prize_image']['tmp_name'][$p['idx']] ?? '',
                'error' => $_FILES['prize_image']['error'][$p['idx']] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['prize_image']['size'][$p['idx']] ?? 0,
            ];
            try { $images[$p['idx']] = save_uploaded_image($f); } catch (RuntimeException $ex) { $errors[] = $p['name'] . ': ' . $ex->getMessage(); }
        }
    }

    if (!$errors) {
        @set_time_limit(300);
        $pdo = db();
        try {
            $codes = unique_codes($total);
            $pdo->beginTransaction();
            q('INSERT INTO batches (name, total_count, starts_at, ends_at, status, created_by) VALUES (?, ?, ?, ?, ?, ?)', [
                $name, $total, $s ? date('Y-m-d H:i:s', $s) : null, $en ? date('Y-m-d H:i:s', $en) : null, 'active', $admin['id'],
            ]);
            $batchId = (int)$pdo->lastInsertId();

            $slots = array_fill(0, $total, null);
            $pos = 0;
            foreach ($prizes as $p) {
                q('INSERT INTO prizes (batch_id, name, image, quantity) VALUES (?, ?, ?, ?)', [$batchId, $p['name'], $images[$p['idx']] ?? null, $p['qty']]);
                $pid = (int)$pdo->lastInsertId();
                for ($k = 0; $k < $p['qty']; $k++) $slots[$pos++] = $pid;
            }
            secure_shuffle($slots);

            foreach (array_chunk(array_keys($codes), 1000) as $chunkKeys) {
                $vals = [];
                $params = [];
                foreach ($chunkKeys as $k) {
                    $vals[] = '(?, ?, ?)';
                    array_push($params, $batchId, $codes[$k], $slots[$k]);
                }
                q('INSERT INTO tickets (batch_id, code, prize_id) VALUES ' . implode(',', $vals), $params);
            }
            $pdo->commit();
            log_activity('batch_created', "#$batchId $name: $total تذكرة، $prizeSum جائزة");
            flash("تم إنشاء الدفعة: " . number_format($total) . " تذكرة منها " . number_format($prizeSum) . " رابحة موزّعة عشوائياً.");
            redirect('batch.php?id=' . $batchId);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[batch] ' . $ex->getMessage());
            $errors[] = 'تعذّر إنشاء الدفعة: ' . $ex->getMessage();
        }
    }

    $old = ['name' => $name, 'total' => (string)$total, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'prizes' => $rawPrizes ?: $old['prizes']];
}

admin_header('دفعة تذاكر جديدة', 'batches');
?>
<?php if ($errors): ?><div class="alert alert--error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card form" id="batchForm">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="span-2">اسم الدفعة<input name="name" required maxlength="150" value="<?= e($old['name']) ?>" placeholder="مثال: حملة الصيف 2026"></label>
    <label>عدد التذاكر<input type="number" name="total" id="total" min="1" max="<?= MAX_BATCH ?>" required value="<?= e($old['total']) ?>"></label>
    <span></span>
    <label>تبدأ في <small class="muted">(اختياري)</small><input type="datetime-local" name="starts_at" value="<?= e($old['starts_at']) ?>"></label>
    <label>تنتهي في <small class="muted">(اختياري)</small><input type="datetime-local" name="ends_at" value="<?= e($old['ends_at']) ?>"></label>
  </div>

  <h2 class="form__section">الجوائز</h2>
  <p class="muted">التذاكر الرابحة تُوزَّع عشوائياً بين كل التذاكر، والباقي "حظ أوفر".</p>
  <div id="prizeRows">
    <?php foreach (array_values($old['prizes']) as $i => $p): ?>
    <div class="prize-line">
      <input name="prizes[<?= $i ?>][name]" placeholder="اسم الجائزة" maxlength="150" value="<?= e($p['name'] ?? '') ?>">
      <input type="number" name="prizes[<?= $i ?>][qty]" class="qty" placeholder="العدد" min="1" value="<?= e((string)($p['qty'] ?? '')) ?>">
      <label class="file-btn">صورة<input type="file" name="prize_image[<?= $i ?>]" accept="image/jpeg,image/png,image/webp"></label>
      <button type="button" class="icon-btn" data-remove aria-label="حذف">×</button>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn--ghost btn--sm" id="addPrize">+ إضافة جائزة</button>

  <div class="summary" id="summary"></div>

  <div class="form__actions">
    <button type="submit" class="btn btn--primary" id="submitBtn">توليد التذاكر</button>
    <a href="batches.php" class="btn btn--ghost">إلغاء</a>
  </div>
</form>

<script>
(() => {
  const rows = document.getElementById('prizeRows'), total = document.getElementById('total'), sum = document.getElementById('summary');
  let n = rows.children.length;
  function update() {
    const t = +total.value || 0;
    const w = [...rows.querySelectorAll('.qty')].reduce((a, i) => a + (+i.value || 0), 0);
    const odds = w ? Math.round(t / w) : 0;
    sum.className = 'summary' + (w > t ? ' summary--bad' : '');
    sum.textContent = w > t ? `مجموع الجوائز (${w}) أكبر من عدد التذاكر (${t})`
      : `${t.toLocaleString('ar')} تذكرة · ${w.toLocaleString('ar')} رابحة · ${(t - w).toLocaleString('ar')} غير رابحة` + (odds ? ` · تقريباً تذكرة رابحة من كل ${odds}` : '');
  }
  document.getElementById('addPrize').onclick = () => {
    const d = document.createElement('div'); d.className = 'prize-line';
    d.innerHTML = `<input name="prizes[${n}][name]" placeholder="اسم الجائزة" maxlength="150"><input type="number" name="prizes[${n}][qty]" class="qty" placeholder="العدد" min="1"><label class="file-btn">صورة<input type="file" name="prize_image[${n}]" accept="image/jpeg,image/png,image/webp"></label><button type="button" class="icon-btn" data-remove aria-label="حذف">×</button>`;
    rows.appendChild(d); n++; d.querySelector('input').focus();
  };
  rows.addEventListener('click', (e) => { if (e.target.closest('[data-remove]') && rows.children.length > 1) { e.target.closest('.prize-line').remove(); update(); } });
  rows.addEventListener('change', (e) => { if (e.target.type === 'file') e.target.parentElement.classList.toggle('has-file', !!e.target.files.length); });
  document.getElementById('batchForm').addEventListener('input', update);
  document.getElementById('batchForm').addEventListener('submit', () => { const b = document.getElementById('submitBtn'); b.disabled = true; b.textContent = 'جاري التوليد…'; });
  update();
})();
</script>
<?php admin_footer();

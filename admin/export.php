<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$type = (string)($_GET['type'] ?? 'csv');
$batch = q('SELECT * FROM batches WHERE id = ?', [$id])->fetch();
if (!$batch) { http_response_code(404); exit('Not found'); }

// ملاحظة أمنية: التصدير لا يحتوي على أي معلومة عن الجوائز، فالمطبعة لا تعرف التذاكر الرابحة.

if ($type === 'csv') {
    log_activity('batch_export_csv', "#$id");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tickets-batch-' . $id . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // ليفتح بالعربي في Excel
    fputcsv($out, ['#', 'code', 'code_formatted', 'qr_url']);
    $st = db()->prepare('SELECT code FROM tickets WHERE batch_id = ? ORDER BY id');
    $st->execute([$id]);
    $n = 0;
    while ($code = $st->fetchColumn()) {
        fputcsv($out, [++$n, $code, format_code($code), base_url('?c=' . $code)]);
    }
    fclose($out);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 400;
$codes = q('SELECT code FROM tickets WHERE batch_id = ? ORDER BY id LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), [$id])->fetchAll(PDO::FETCH_COLUMN);
$site = setting('site_name');
$botNum = bot_enabled() ? tw_cfg('whatsapp_from') : '';
log_activity('batch_print', "#$id page $page");
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>طباعة تذاكر · <?= e($batch['name']) ?> · صفحة <?= $page ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@700;800&family=Tajawal:wght@500;700&display=swap" rel="stylesheet">
<style>
  @page{size:A4;margin:8mm}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Tajawal',sans-serif;color:#1b1340;background:#eee}
  .bar{position:sticky;top:0;background:#1b1340;color:#fff;padding:10px 16px;display:flex;gap:12px;align-items:center;font-size:14px}
  .bar button{background:#a855f7;color:#fff;border:0;border-radius:8px;padding:8px 16px;font:inherit;font-weight:700;cursor:pointer}
  .sheet{display:grid;grid-template-columns:repeat(2,1fr);gap:4mm;padding:6mm;max-width:210mm;margin:0 auto;background:#fff}
  .tk{position:relative;height:44mm;border-radius:5mm;overflow:hidden;display:grid;grid-template-columns:1fr 30mm;break-inside:avoid;
      background:linear-gradient(110deg,#7c3aed,#a855f7 55%,#38bdf8);color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .tk::before,.tk::after{content:"";position:absolute;width:7mm;height:7mm;border-radius:50%;background:#fff;top:50%;transform:translateY(-50%)}
  .tk::before{right:-3.5mm}.tk::after{left:-3.5mm}
  .tk__main{padding:4mm 6mm;display:flex;flex-direction:column;justify-content:space-between}
  .tk__brand{font-family:'Baloo Bhaijaan 2',sans-serif;font-weight:800;font-size:15pt;line-height:1}
  .tk__hint{font-size:8pt;opacity:.9}
  .tk__code{direction:ltr;text-align:right;font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:800;font-size:15pt;letter-spacing:1.5pt;background:rgba(255,255,255,.95);color:#1b1340;border-radius:2.5mm;padding:1.5mm 3mm;align-self:flex-start}
  .tk__url{font-size:7pt;opacity:.85;direction:ltr;text-align:right}
  .tk__qr{background:#fff;margin:4mm 4mm 4mm 0;border-radius:3mm;display:grid;place-items:center;border-right:1.2mm dashed rgba(255,255,255,.6)}
  .tk__qr canvas,.tk__qr img{width:24mm!important;height:24mm!important}
  @media print{body{background:#fff}.bar{display:none}.sheet{padding:0;gap:4mm}}
</style>
</head>
<body>
<div class="bar"><strong><?= e($batch['name']) ?></strong> · صفحة <?= $page ?> · <?= count($codes) ?> تذكرة <button onclick="window.print()">طباعة / حفظ PDF</button><span id="st">جاري تجهيز رموز QR…</span></div>
<div class="sheet">
<?php foreach ($codes as $c): ?>
  <div class="tk">
    <div class="tk__main">
      <div><div class="tk__brand"><?= e($site) ?></div><div class="tk__hint">اكشف تذكرتك واكتشف إذا ربحت!</div></div>
      <div class="tk__code"><?= e(format_code($c)) ?></div>
      <div class="tk__url"><?= e(preg_replace('#^https?://#', '', rtrim(base_url(''), '/'))) ?><?php if ($botNum): ?> · واتساب <?= e($botNum) ?><?php endif; ?></div>
    </div>
    <div class="tk__qr" data-url="<?= e(base_url('?c=' . $c)) ?>"></div>
  </div>
<?php endforeach; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  document.querySelectorAll('.tk__qr').forEach(el => new QRCode(el, { text: el.dataset.url, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M }));
  document.getElementById('st').textContent = 'جاهز للطباعة';
</script>
</body>
</html>

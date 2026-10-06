<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$stats = q(
    "SELECT
       (SELECT COUNT(*) FROM tickets) AS total,
       (SELECT COUNT(*) FROM tickets WHERE status = 'used') AS used,
       (SELECT COUNT(*) FROM tickets WHERE status = 'used' AND used_at >= CURDATE()) AS used_today,
       (SELECT COUNT(*) FROM winners) AS winners,
       (SELECT COUNT(*) FROM winners WHERE delivery_status = 'pending') AS pending,
       (SELECT COUNT(*) FROM winners WHERE created_at >= CURDATE()) AS winners_today,
       (SELECT COUNT(*) FROM attempts WHERE result = 'blocked' AND created_at > (NOW() - INTERVAL 1 DAY)) AS blocked,
       (SELECT COUNT(*) FROM attempts WHERE source = 'whatsapp' AND result IN ('win','lose') AND created_at >= CURDATE()) AS wa_today"
)->fetch();

$daily = q(
    "SELECT DATE(used_at) d, COUNT(*) n FROM tickets
      WHERE status = 'used' AND used_at >= (CURDATE() - INTERVAL 13 DAY)
      GROUP BY DATE(used_at)"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = (int)($daily[$d] ?? 0);
}
$maxDay = max(1, max($days));

$batches = q(
    "SELECT b.*, (SELECT COUNT(*) FROM tickets t WHERE t.batch_id = b.id AND t.status = 'used') AS used_count,
            (SELECT COALESCE(SUM(quantity),0) FROM prizes p WHERE p.batch_id = b.id) AS prize_total,
            (SELECT COALESCE(SUM(won_count),0) FROM prizes p WHERE p.batch_id = b.id) AS prize_won
       FROM batches b WHERE b.status <> 'ended' ORDER BY b.id DESC LIMIT 6"
)->fetchAll();

$recent = q(
    "SELECT w.id, w.child_name, w.guardian_phone, w.delivery_status, w.created_at, p.name AS prize
       FROM winners w JOIN tickets t ON t.id = w.ticket_id LEFT JOIN prizes p ON p.id = t.prize_id
      ORDER BY w.id DESC LIMIT 6"
)->fetchAll();

admin_header('الرئيسية', 'dashboard');
?>
<div class="stats">
  <div class="stat"><span>تذاكر مستخدمة اليوم</span><strong><?= number_format((int)$stats['used_today']) ?></strong><small><?= number_format((int)$stats['wa_today']) ?> منها عبر واتساب · إجمالي <?= number_format((int)$stats['used']) ?></small></div>
  <div class="stat"><span>رابحون اليوم</span><strong><?= number_format((int)$stats['winners_today']) ?></strong><small>إجمالي <?= number_format((int)$stats['winners']) ?></small></div>
  <div class="stat stat--accent"><span>جوائز بانتظار التسليم</span><strong><?= number_format((int)$stats['pending']) ?></strong><small><a href="winners.php?status=pending">عرض القائمة ←</a></small></div>
  <div class="stat"><span>إجمالي التذاكر</span><strong><?= number_format((int)$stats['total']) ?></strong><small><?= (int)$stats['blocked'] ?> محاولة محظورة آخر 24 ساعة</small></div>
</div>

<div class="grid-2">
  <section class="card">
    <h2>التذاكر المستخدمة · آخر 14 يوماً</h2>
    <div class="bars" role="img" aria-label="عدد التذاكر المستخدمة يومياً">
      <?php foreach ($days as $d => $n): ?>
        <div class="bars__col" title="<?= e($d) ?>: <?= $n ?>">
          <span class="bars__val"><?= $n ?: '' ?></span>
          <span class="bars__bar" style="height:<?= round($n / $maxDay * 100, 1) ?>%"></span>
          <span class="bars__lbl"><?= e(date('d/m', strtotime($d))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>آخر الرابحين</h2><a href="winners.php">الكل</a></div>
    <?php if (!$recent): ?><p class="muted">لا يوجد رابحون بعد.</p><?php else: ?>
    <table class="table table--compact">
      <tbody>
      <?php foreach ($recent as $w): ?>
        <tr>
          <td><strong><?= e($w['child_name'] ?: 'لم يكمل التسجيل') ?></strong><br><small class="muted"><?= e($w['prize']) ?></small></td>
          <td><?= status_badge($w['delivery_status']) ?></td>
          <td class="muted"><?= e(dt($w['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2>الدفعات الحالية</h2><a class="btn btn--primary btn--sm" href="batch-new.php">+ دفعة جديدة</a></div>
  <?php if (!$batches): ?>
    <div class="empty-state"><p>لا توجد دفعات بعد. ابدأ بإنشاء أول دفعة تذاكر.</p><a class="btn btn--primary" href="batch-new.php">إنشاء دفعة</a></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>الدفعة</th><th>الحالة</th><th>الاستخدام</th><th>الجوائز الموزّعة</th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): $pct = $b['total_count'] ? round($b['used_count'] / $b['total_count'] * 100) : 0; ?>
      <tr>
        <td><a href="batch.php?id=<?= (int)$b['id'] ?>"><strong><?= e($b['name']) ?></strong></a></td>
        <td><?= status_badge($b['status']) ?></td>
        <td><div class="progress"><span style="width:<?= $pct ?>%"></span></div><small class="muted"><?= number_format((int)$b['used_count']) ?> / <?= number_format((int)$b['total_count']) ?> (<?= $pct ?>%)</small></td>
        <td><?= number_format((int)$b['prize_won']) ?> / <?= number_format((int)$b['prize_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer();

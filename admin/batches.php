<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$batches = q(
    "SELECT b.*, (SELECT COUNT(*) FROM tickets t WHERE t.batch_id = b.id AND t.status = 'used') AS used_count,
            (SELECT COALESCE(SUM(quantity),0) FROM prizes p WHERE p.batch_id = b.id) AS prize_total,
            (SELECT COALESCE(SUM(won_count),0) FROM prizes p WHERE p.batch_id = b.id) AS prize_won
       FROM batches b ORDER BY b.id DESC"
)->fetchAll();

admin_header('دفعات التذاكر', 'batches');
?>
<div class="toolbar"><a class="btn btn--primary" href="batch-new.php">+ دفعة جديدة</a></div>
<section class="card">
  <?php if (!$batches): ?>
    <div class="empty-state"><p>لا توجد دفعات بعد.</p><a class="btn btn--primary" href="batch-new.php">إنشاء أول دفعة</a></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>#</th><th>الدفعة</th><th>الحالة</th><th>الفترة</th><th>الاستخدام</th><th>الجوائز</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): $pct = $b['total_count'] ? round($b['used_count'] / $b['total_count'] * 100) : 0; ?>
      <tr>
        <td class="muted"><?= (int)$b['id'] ?></td>
        <td><strong><?= e($b['name']) ?></strong><br><small class="muted">أُنشئت <?= e(dt($b['created_at'])) ?></small></td>
        <td><?= status_badge($b['status']) ?></td>
        <td class="muted"><small><?= e($b['starts_at'] ? date('Y-m-d', strtotime($b['starts_at'])) : 'فوراً') ?> ← <?= e($b['ends_at'] ? date('Y-m-d', strtotime($b['ends_at'])) : 'مفتوحة') ?></small></td>
        <td><div class="progress"><span style="width:<?= $pct ?>%"></span></div><small class="muted"><?= number_format((int)$b['used_count']) ?> / <?= number_format((int)$b['total_count']) ?></small></td>
        <td><?= number_format((int)$b['prize_won']) ?> / <?= number_format((int)$b['prize_total']) ?></td>
        <td><a class="btn btn--ghost btn--sm" href="batch.php?id=<?= (int)$b['id'] ?>">إدارة</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer();

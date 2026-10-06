<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$per = 50;
$page = max(1, (int)($_GET['p'] ?? 1));
$total = (int)q('SELECT COUNT(*) FROM message_logs')->fetchColumn();
$rows = q("SELECT m.*, w.child_name FROM message_logs m LEFT JOIN winners w ON w.id = m.winner_id ORDER BY m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per))->fetchAll();

admin_header('سجل الرسائل', 'messages');
?>
<?php if (!twilio_ready()): ?>
  <div class="alert alert--warn">Twilio غير مُعدّ بعد، لذلك لن تُرسل أي رسائل. أضف المفاتيح في ملف <code>config.php</code> (راجع ملف README).</div>
<?php endif; ?>
<section class="card">
  <?php if (!$rows): ?><p class="muted">لم تُرسل أي رسائل بعد.</p><?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>التاريخ</th><th>القناة</th><th>الرقم</th><th>الطفل</th><th>الرسالة</th><th>الحالة</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): $ok = !in_array($m['status'], ['failed', 'skipped'], true); $in = ($m['direction'] ?? 'out') === 'in'; ?>
      <tr>
        <td class="muted small"><?= e(dt($m['created_at'])) ?></td>
        <td><?= $m['channel'] === 'whatsapp' ? 'واتساب' : 'SMS' ?><br><small class="<?= $in ? 'dir-in' : 'dir-out' ?>"><?= $in ? '↙ واردة' : '↗ صادرة' ?></small></td>
        <td dir="ltr" class="ltr-cell small"><?= e($m['recipient']) ?></td>
        <td><?= e($m['child_name'] ?: '—') ?></td>
        <td class="small msg-body"><?= e($m['body']) ?></td>
        <td><span class="badge badge--<?= $ok ? 'green' : 'red' ?>"><?= e($m['status']) ?></span><?php if ($m['error']): ?><br><small class="text-warn"><?= e($m['error']) ?></small><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($total > $per): ?>
    <nav class="pager"><?php for ($i = 1; $i <= (int)ceil($total / $per); $i++): ?><a href="?p=<?= $i ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a><?php endfor; ?></nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php admin_footer();

<?php
declare(strict_types=1);
// بيانات تجريبية: دفعة واحدة فيها 100 تذكرة (دراجة 2، ألعاب 8، هدية 20، والباقي خاسر).
// احذف هذا الملف بعد الاستخدام.
require __DIR__ . '/_layout.php';
$admin = require_admin();

const DEMO_BATCH_NAME = 'حملة تجريبية';
const DEMO_TOTAL = 100;
const DEMO_PRIZES = [
    ['name' => 'دراجة', 'qty' => 2],
    ['name' => 'ألعاب', 'qty' => 8],
    ['name' => 'هدية', 'qty' => 20],
];

function demo_shuffle(array &$a): void
{
    for ($i = count($a) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
    }
}

function demo_codes(int $n): array
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $exists = (int)q('SELECT COUNT(*) FROM batches WHERE name = ?', [DEMO_BATCH_NAME])->fetchColumn();
    if ($exists) {
        flash('الدفعة التجريبية موجودة من قبل.', 'error');
        redirect('demo.php');
    }

    $pdo = db();
    try {
        $codes = demo_codes(DEMO_TOTAL);
        $pdo->beginTransaction();
        q('INSERT INTO batches (name, total_count, starts_at, ends_at, status, created_by) VALUES (?, ?, NULL, NULL, ?, ?)', [
            DEMO_BATCH_NAME, DEMO_TOTAL, 'active', $admin['id'],
        ]);
        $batchId = (int)$pdo->lastInsertId();

        $slots = array_fill(0, DEMO_TOTAL, null);
        $pos = 0;
        foreach (DEMO_PRIZES as $p) {
            q('INSERT INTO prizes (batch_id, name, image, quantity) VALUES (?, ?, NULL, ?)', [$batchId, $p['name'], $p['qty']]);
            $pid = (int)$pdo->lastInsertId();
            for ($k = 0; $k < $p['qty']; $k++) $slots[$pos++] = $pid;
        }
        demo_shuffle($slots);

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
        log_activity('batch_created', "#$batchId " . DEMO_BATCH_NAME . ': ' . DEMO_TOTAL . ' تذكرة (بيانات تجريبية)');
        flash('تم إنشاء الدفعة التجريبية: 100 تذكرة فيها 30 جائزة. احذف demo.php الحين.');
        redirect('batch.php?id=' . $batchId);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[demo] ' . $ex->getMessage());
        flash('تعذّر إنشاء البيانات التجريبية.', 'error');
        redirect('demo.php');
    }
}

admin_header('بيانات تجريبية', 'batches');
?>
<section class="card form" style="max-width:640px">
  <div class="card__head"><h2>إنشاء بيانات تجريبية</h2></div>
  <p class="muted">ينشئ دفعة واحدة باسم «<?= e(DEMO_BATCH_NAME) ?>» فيها 100 تذكرة: دراجة (2)، ألعاب (8)، هدية (20)، والباقي خاسر.</p>
  <form method="post"><?= csrf_field() ?>
    <button class="btn btn--primary">إنشاء البيانات التجريبية</button>
  </form>
  <p class="muted small">بعد ما تخلص، احذف هذا الملف من السيرفر.</p>
</section>
<?php admin_footer();

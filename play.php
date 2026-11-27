<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public.php';

$player = current_player();
$levels = levels_all();
$leaders = leaderboard(10);
$otp = otp_enabled();

public_head($player ? 'تحدي اليوم' : 'سجّل وابدأ التحدي');
?>
<body class="page-play">
<div class="bg" aria-hidden="true"></div>
<canvas class="sky" id="sky" aria-hidden="true"></canvas>
<?php public_nav('play'); ?>

<main class="wrap">
<?php if (!$player): ?>
  <!-- ===== التسجيل والدخول ===== -->
  <section class="auth card-x">
    <div class="auth__head">
      <img src="<?= e(media_slot_url('mascot')) ?>" alt="">
      <div><h1>انضم لأبطال أكوما مايند</h1><p>سجّل مرة وحدة، وجاوب تحدي كل يوم، واصعد للجائزة الذهبية. المشاركة مجانية.</p></div>
    </div>
    <div class="tabs" role="tablist">
      <button type="button" class="on" data-tab="reg" role="tab">تسجيل جديد</button>
      <button type="button" data-tab="login" role="tab">عندي حساب</button>
    </div>

    <form id="regForm" class="fields" novalidate>
      <label>الاسم الرباعي للطفل<span class="hint">مثال: سارة محمد عبدالله القحطاني. يظهر الاسم الأول فقط للناس.</span>
        <input name="full_name" maxlength="120" autocomplete="name" required></label>
      <label>جوال ولي الأمر<span class="hint"><?= $otp ? 'نرسل له رمز تحقق.' : 'نتواصل عليه عند الفوز فقط.' ?></span>
        <input name="phone" inputmode="tel" dir="ltr" placeholder="05XXXXXXXX" required></label>
      <label>العمر
        <select name="age" required><option value="">اختر</option><?php for ($a = 5; $a <= 17; $a++): ?><option value="<?= $a ?>"><?= $a ?> سنوات</option><?php endfor; ?></select></label>
      <div><span style="font-weight:700">اختر شخصيتك</span>
        <div class="avatars"><?php foreach (PLAYER_AVATARS as $i => $av): ?><label><input type="radio" name="avatar" value="<?= $av ?>" <?= $i === 0 ? 'checked' : '' ?>><img src="<?= e(avatar_url($av)) ?>" alt="شخصية <?= $i + 1 ?>"></label><?php endforeach; ?></div>
      </div>
      <label class="check"><input type="checkbox" name="consent" value="1"> أنا ولي الأمر، وأوافق على <a href="terms.php" target="_blank" style="color:var(--violet-2)">الشروط</a> و<a href="privacy.php" target="_blank" style="color:var(--violet-2)">سياسة الخصوصية</a></label>
      <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('bolt') ?>ابدأ المغامرة</button>
      <p class="form-err" role="alert"></p>
    </form>

    <form id="loginForm" class="fields" hidden novalidate>
      <label>جوال ولي الأمر<input name="phone" inputmode="tel" dir="ltr" placeholder="05XXXXXXXX" required></label>
      <?php if (!$otp): ?><label>الاسم الرباعي كما سجّلته<input name="full_name" maxlength="120" required></label><?php endif; ?>
      <button class="btn btn--primary btn--lg btn--block" type="submit">دخول</button>
      <p class="form-err" role="alert"></p>
    </form>

    <form id="otpForm" class="fields" hidden novalidate>
      <p>أرسلنا رمز تحقق إلى <bdi dir="ltr" data-phone></bdi></p>
      <label>رمز التحقق<input name="otp" inputmode="numeric" dir="ltr" maxlength="8" autocomplete="one-time-code" required></label>
      <button class="btn btn--primary btn--lg btn--block" type="submit">تحقق</button>
      <p class="form-err" role="alert"></p>
    </form>
  </section>

<?php else:
    $s = level_state((int)$player['points']);
    $left = max(0, daily_limit() - answered_today((int)$player['id'])); ?>
  <div class="play">
    <div>
      <!-- ===== لوحة المتسابق ===== -->
      <section class="card-x" id="me">
        <div class="me">
          <img class="me__av" src="<?= e(avatar_url($player['avatar'])) ?>" alt="">
          <div>
            <h1 class="me__name">أهلاً <?= e(first_name($player['full_name'])) ?>!</h1>
            <span class="me__lvl"><?= icon('medal') ?>المستوى <span data-level><?= e($s['current']['name'] ?? 'مبتدئ') ?></span></span>
          </div>
        </div>
        <div class="kpis">
          <div class="kpi kpi--gold"><b data-points><?= number_format((int)$player['points']) ?></b><span>نقطة</span></div>
          <div class="kpi"><b data-streak><?= (int)$player['streak'] ?></b><span>يوم متتالي 🔥</span></div>
          <div class="kpi"><b data-left><?= $left ?></b><span>سؤال باقي اليوم</span></div>
        </div>
        <div class="progress">
          <div class="progress__bar"><div class="progress__fill" data-pct="<?= $s['pct'] ?>"></div></div>
          <div class="progress__txt"><span data-next><?= $s['next'] ? 'باقي ' . number_format($s['to_next']) . ' نقطة للمستوى ' . e($s['next']['name']) : 'وصلت أعلى مستوى! 🏆' ?></span><span data-pcttxt><?= $s['pct'] ?>%</span></div>
        </div>
        <button type="button" class="btn btn--gold btn--lg btn--block" id="startBtn"><?= icon('bolt') ?><?= $left ? 'ابدأ تحدي اليوم' : 'خلصت تحديات اليوم' ?></button>
      </section>

      <!-- ===== السؤال ===== -->
      <section class="card-x quiz" id="quiz" hidden style="margin-top:18px">
        <div class="quiz__top">
          <span class="quiz__count" data-count></span>
          <span class="quiz__cat" data-cat></span>
          <div class="timer" id="timer"><svg viewBox="0 0 60 60"><circle class="t-bg" cx="30" cy="30" r="26"/><circle class="t-fg" cx="30" cy="30" r="26" stroke-dasharray="163.4" stroke-dashoffset="0"/></svg><b data-sec></b></div>
        </div>
        <h2 class="quiz__q" data-q></h2>
        <div class="opts" data-opts></div>
        <div class="feedback" data-fb hidden></div>
      </section>
    </div>

    <!-- ===== جانب: المتصدرون والمستويات ===== -->
    <aside class="card-x side-board">
      <h2><?= icon('trophy') ?>المتصدرون</h2>
      <ol class="rank">
        <?php if ($leaders): foreach ($leaders as $i => $l): ?>
        <li class="<?= (int)$l['id'] === (int)$player['id'] ? 'is-me' : '' ?>">
          <span class="rank__n"><?= $i + 1 ?></span>
          <img class="rank__thumb rank__thumb--round" src="<?= e(avatar_url($l['avatar'])) ?>" alt="">
          <span class="rank__body"><strong><?= e(first_name($l['full_name'])) ?></strong><small><?= e(level_name_for((int)$l['points'])) ?></small></span>
          <span class="rank__meta rank__meta--star"><?= icon('star') ?><?= number_format((int)$l['points']) ?></span>
        </li>
        <?php endforeach; else: ?><li class="rank__empty">كن أول المتصدرين!</li><?php endif; ?>
      </ol>
      <div class="mini-levels">
        <?php foreach ($levels as $lv): ?>
        <div class="<?= (int)$player['points'] >= (int)$lv['min_points'] ? 'on' : '' ?>"><span>المستوى <?= e($lv['name']) ?></span><span><?= number_format((int)$lv['min_points']) ?> نقطة</span></div>
        <?php endforeach; ?>
      </div>
      <p style="margin:14px 0 0"><button type="button" class="link-btn" id="logoutBtn">تسجيل خروج</button></p>
    </aside>
  </div>

  <div class="levelup" id="levelUp" hidden role="dialog" aria-modal="true">
    <div class="levelup__box">
      <img src="<?= e(media_slot_url('mascot')) ?>" alt="">
      <h2>مستوى جديد!</h2>
      <p data-lu></p>
      <button type="button" class="btn btn--gold" data-close-lu>يا سلام! كمّل</button>
    </div>
  </div>
<?php endif; ?>
</main>

<?php public_footer(); ?>
<script>window.KT = { base: <?= json_encode(rtrim(base_url(''), '/') . '/', JSON_UNESCAPED_SLASHES) ?>, mascot: <?= json_encode(media_slot_url('mascot'), JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="<?= e(asset('assets/js/fx.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/play.js')) ?>" defer></script>
</body>
</html>

<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$admin = require_admin();
$isOwner = $admin['role'] === 'owner';

$keys = ['site_name', 'site_tagline', 'support_whatsapp', 'msg_win', 'msg_delivered', 'wa_content_sid_win', 'wa_content_sid_delivered',
         'wa_bot_enabled', 'bot_welcome', 'bot_win', 'bot_lose', 'bot_done'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'site' && $isOwner) {
        foreach ($keys as $k) {
            if (isset($_POST[$k])) save_setting($k, mb_substr(trim((string)$_POST[$k]), 0, 1000));
        }
        log_activity('settings_saved');
        flash('تم حفظ الإعدادات.');
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $hash = q('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']])->fetchColumn();
        if (!password_verify($cur, (string)$hash)) flash('كلمة المرور الحالية غير صحيحة.', 'error');
        elseif (strlen($new) < 10) flash('كلمة المرور الجديدة يجب أن تكون 10 أحرف على الأقل.', 'error');
        else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            log_activity('password_changed');
            flash('تم تغيير كلمة المرور.');
        }
    } elseif ($action === 'test' && $isOwner) {
        $phone = normalize_sa_phone((string)($_POST['phone'] ?? ''));
        if (!$phone) flash('رقم غير صحيح.', 'error');
        else {
            $ok = send_notification(null, $phone, 'رسالة تجريبية من ' . setting('site_name') . ' ✅');
            flash($ok ? 'تم الإرسال! تحقق من واتساب.' : 'تعذّر الإرسال، راجع سجل الرسائل لمعرفة السبب.', $ok ? 'ok' : 'error');
        }
    }
    redirect('settings.php');
}

$v = [];
foreach ($keys as $k) $v[$k] = setting($k);

admin_header('الإعدادات', 'settings');
?>
<div class="grid-2">
  <form method="post" class="card form">
    <?= csrf_field() ?><input type="hidden" name="action" value="site">
    <h2>الموقع</h2>
    <fieldset <?= $isOwner ? '' : 'disabled' ?>>
      <label>اسم الموقع<input name="site_name" value="<?= e($v['site_name']) ?>" maxlength="60"></label>
      <label>الجملة التعريفية<input name="site_tagline" value="<?= e($v['site_tagline']) ?>" maxlength="140"></label>
      <label>واتساب الدعم <small class="muted">(يظهر في أسفل الموقع)</small><input name="support_whatsapp" dir="ltr" value="<?= e($v['support_whatsapp']) ?>" placeholder="9665XXXXXXXX"></label>

      <h2 class="form__section">رسائل واتساب</h2>
      <p class="muted small">المتغيرات: <code>{child}</code> اسم الطفل، <code>{prize}</code> الجائزة، <code>{claim}</code> رقم المطالبة.</p>
      <label>رسالة الفوز<textarea name="msg_win" rows="3"><?= e($v['msg_win']) ?></textarea></label>
      <label>رسالة التسليم<textarea name="msg_delivered" rows="2"><?= e($v['msg_delivered']) ?></textarea></label>

      <details class="advanced">
        <summary>قوالب واتساب المعتمدة من Meta (للإنتاج)</summary>
        <p class="muted small">بعد اعتماد القالب في Twilio Content Template Builder، ضع رقمه (يبدأ بـ HX). المتغيرات في القالب بالترتيب: {{1}} اسم الطفل، {{2}} الجائزة، {{3}} رقم المطالبة. اتركه فارغاً لإرسال النص أعلاه (يعمل في Sandbox).</p>
        <label>Content SID لرسالة الفوز<input name="wa_content_sid_win" dir="ltr" value="<?= e($v['wa_content_sid_win']) ?>" placeholder="HXxxxxxxxxxxxxxxxx"></label>
        <label>Content SID لرسالة التسليم<input name="wa_content_sid_delivered" dir="ltr" value="<?= e($v['wa_content_sid_delivered']) ?>" placeholder="HXxxxxxxxxxxxxxxxx"></label>
      </details>
      <h2 class="form__section" id="bot">بوت واتساب</h2>
      <input type="hidden" name="wa_bot_enabled" value="0">
      <label class="check"><input type="checkbox" name="wa_bot_enabled" value="1" <?= $v['wa_bot_enabled'] === '1' ? 'checked' : '' ?>> تفعيل البوت: الأطفال يرسلون رمز التذكرة على واتساب ويستلمون النتيجة فوراً</label>
      <p class="muted small">المتغيرات: <code>{site}</code> اسم الموقع، <code>{prize}</code>، <code>{claim}</code>، <code>{child}</code>. للخط العريض في واتساب ضع النص بين نجمتين <code>*هكذا*</code>.</p>
      <label>رسالة الترحيب <small class="muted">(لأي رسالة ليست رمزاً)</small><textarea name="bot_welcome" rows="3"><?= e($v['bot_welcome']) ?></textarea></label>
      <label>عند الفوز <small class="muted">(تُرسل معها صورة الجائزة)</small><textarea name="bot_win" rows="6"><?= e($v['bot_win']) ?></textarea></label>
      <label>عند عدم الفوز<textarea name="bot_lose" rows="3"><?= e($v['bot_lose']) ?></textarea></label>
      <label>بعد تسجيل اسم الطفل<textarea name="bot_done" rows="4"><?= e($v['bot_done']) ?></textarea></label>
      <button class="btn btn--primary">حفظ</button>
    </fieldset>
  </form>

  <div>
    <section class="card">
      <h2>حالة Twilio</h2>
      <ul class="checklist">
        <li class="<?= twilio_ready() ? 'ok' : 'no' ?>">مفاتيح الحساب (Account SID / Auth Token)</li>
        <li class="<?= tw_cfg('whatsapp_from') ? 'ok' : 'no' ?>">رقم واتساب المرسل</li>
        <li class="<?= otp_enabled() ? 'ok' : 'no' ?>">خدمة Verify لرمز التحقق OTP <?= otp_enabled() ? '' : '<small class="muted">(بدونها يُسجَّل الرابح بدون تحقق)</small>' ?></li>
        <li class="<?= tw_cfg('sms_from') ? 'ok' : 'no' ?>">رقم SMS احتياطي <small class="muted">(اختياري)</small></li>
      </ul>
      <p class="muted small">المفاتيح محفوظة في ملف <code>config.php</code> على السيرفر وليست في قاعدة البيانات، للأمان.</p>
      <?php if ($isOwner): ?>
      <form method="post" class="search">
        <?= csrf_field() ?><input type="hidden" name="action" value="test">
        <input name="phone" dir="ltr" placeholder="05XXXXXXXX">
        <button class="btn btn--ghost btn--sm">إرسال رسالة تجريبية</button>
      </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>ربط البوت مع Twilio</h2>
      <p class="muted small">البوت <?= bot_enabled() ? '<span class="badge badge--green">يعمل</span>' : '<span class="badge badge--gray">متوقف</span>' ?></p>
      <ol class="steps">
        <li>Twilio Console ← Messaging ← WhatsApp senders (أو Sandbox settings للتجربة).</li>
        <li>في خانة <strong>When a message comes in</strong> الصق هذا الرابط واختر <code>HTTP POST</code>:
          <div class="copy-row"><input readonly dir="ltr" value="<?= e(base_url('api/whatsapp.php')) ?>" id="hookUrl"><button type="button" class="btn btn--ghost btn--sm" onclick="navigator.clipboard.writeText(document.getElementById('hookUrl').value);this.textContent='تم النسخ ✓'">نسخ</button></div>
        </li>
        <li>فعّل البوت من الخيار في هذه الصفحة واحفظ.</li>
        <li>أرسل أي رسالة لرقم واتساب المنصة للتجربة.</li>
      </ol>
      <p class="muted small">الرابط يجب أن يكون عاماً بـ https. للتجربة على جهازك استخدم <code>ngrok http 8080</code> (التفاصيل في README).</p>
      <?php if (bot_enabled()): ?><a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="<?= e(bot_chat_link('مرحبا')) ?>">فتح محادثة البوت ↗</a><?php endif; ?>
    </section>

    <form method="post" class="card form">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2>تغيير كلمة المرور</h2>
      <label>كلمة المرور الحالية<input type="password" name="current" required dir="ltr" autocomplete="current-password"></label>
      <label>كلمة المرور الجديدة <small class="muted">(10 أحرف على الأقل)</small><input type="password" name="new" required minlength="10" dir="ltr" autocomplete="new-password"></label>
      <button class="btn btn--ghost">تغيير</button>
    </form>
  </div>
</div>
<?php admin_footer();

/* تأثيرات الواجهة: شاشة الافتتاح، سماء النجوم، العدّادات، الظهور عند التمرير، الإمالة، والألعاب النارية */
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (s, r = document) => r.querySelector(s);

  /* ---------- شاشة الافتتاح: نجمة تسقط وتنفجر (مرة واحدة لكل جلسة) ---------- */
  const intro = $('#intro');
  if (intro) {
    let seen = false;
    try { seen = sessionStorage.getItem('kt_intro') === '1'; } catch { /* خاص */ }
    if (seen || reduce) {
      intro.remove();
    } else {
      document.documentElement.classList.add('intro-on');
      try { sessionStorage.setItem('kt_intro', '1'); } catch { /* خاص */ }
      const end = () => {
        if (!intro.isConnected) return;
        intro.classList.add('is-out');
        document.documentElement.classList.remove('intro-on');
        setTimeout(() => intro.remove(), 700);
      };
      setTimeout(() => { intro.classList.add('is-burst'); burst(innerWidth / 2, innerHeight * 0.42, 90); }, 1050);
      setTimeout(end, 2900);
      $('#introSkip').addEventListener('click', end);
      intro.addEventListener('click', (e) => { if (e.target === intro) end(); });
    }
  }

  /* ---------- سماء النجوم: نجوم تلمع + شهب تسقط ---------- */
  const sky = $('#sky');
  if (sky && !reduce) {
    const ctx = sky.getContext('2d');
    let W = 0, H = 0, dpr = 1, stars = [], meteors = [], last = 0;
    const size = () => {
      dpr = Math.min(2, devicePixelRatio || 1);
      W = innerWidth; H = innerHeight;
      sky.width = W * dpr; sky.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const n = Math.round((W * H) / 9000);
      stars = Array.from({ length: n }, () => ({ x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.3 + 0.3, t: Math.random() * 6.28, s: 0.01 + Math.random() * 0.03 }));
    };
    size();
    addEventListener('resize', size);
    const spawn = () => {
      const fromRight = Math.random() < 0.7;
      meteors.push({ x: fromRight ? W * (0.5 + Math.random() * 0.6) : Math.random() * W, y: -20, vx: -(4 + Math.random() * 4), vy: 3 + Math.random() * 3, life: 0, len: 90 + Math.random() * 90, gold: Math.random() < 0.35 });
    };
    let visible = true;
    document.addEventListener('visibilitychange', () => { visible = !document.hidden; if (visible) requestAnimationFrame(tick); });
    function tick(t) {
      if (!visible) return;
      ctx.clearRect(0, 0, W, H);
      for (const s of stars) {
        s.t += s.s;
        ctx.globalAlpha = 0.35 + Math.sin(s.t) * 0.35;
        ctx.fillStyle = '#fff';
        ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 6.28); ctx.fill();
      }
      if (t - last > 1400 + Math.random() * 2200) { spawn(); last = t; }
      meteors = meteors.filter((m) => m.life < 160 && m.y < H + 60);
      for (const m of meteors) {
        m.x += m.vx; m.y += m.vy; m.life++;
        const sp = Math.hypot(m.vx, m.vy), tx = m.x - (m.vx / sp) * m.len, ty = m.y - (m.vy / sp) * m.len;
        const g = ctx.createLinearGradient(m.x, m.y, tx, ty);
        g.addColorStop(0, m.gold ? 'rgba(255,214,90,.95)' : 'rgba(255,255,255,.95)');
        g.addColorStop(1, 'rgba(192,132,252,0)');
        ctx.globalAlpha = Math.max(0, 1 - m.life / 160);
        ctx.strokeStyle = g; ctx.lineWidth = 2.2; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(tx, ty); ctx.stroke();
        ctx.fillStyle = m.gold ? '#ffe27a' : '#fff';
        ctx.beginPath(); ctx.arc(m.x, m.y, 2.4, 0, 6.28); ctx.fill();
      }
      ctx.globalAlpha = 1;
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  /* ---------- انفجار شرارات (يُستخدم في الافتتاح والنقر والاحتفال) ---------- */
  let fxCanvas = null;
  function burst(x, y, n = 40, colors) {
    if (reduce) return;
    if (!fxCanvas) {
      fxCanvas = document.createElement('canvas');
      fxCanvas.className = 'fx-layer';
      document.body.appendChild(fxCanvas);
    }
    const c = fxCanvas, ctx = c.getContext('2d');
    const dpr = Math.min(2, devicePixelRatio || 1);
    if (c.width !== innerWidth * dpr) { c.width = innerWidth * dpr; c.height = innerHeight * dpr; }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const pal = colors || ['#ffe27a', '#c084fc', '#ff7ad0', '#4fc3ff', '#ffffff'];
    const parts = Array.from({ length: n }, () => {
      const a = Math.random() * 6.28, v = 2 + Math.random() * 7;
      return { x, y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 0, max: 50 + Math.random() * 40, r: 1.5 + Math.random() * 2.5, col: pal[(Math.random() * pal.length) | 0], star: Math.random() < 0.3 };
    });
    burst.queue = (burst.queue || []).concat(parts);
    if (burst.running) return;
    burst.running = true;
    (function frame() {
      ctx.clearRect(0, 0, innerWidth, innerHeight);
      burst.queue = burst.queue.filter((p) => p.life < p.max);
      for (const p of burst.queue) {
        p.vx *= 0.96; p.vy = p.vy * 0.96 + 0.12; p.x += p.vx; p.y += p.vy; p.life++;
        ctx.globalAlpha = 1 - p.life / p.max; ctx.fillStyle = p.col;
        if (p.star) { drawStar(ctx, p.x, p.y, p.r * 2.2); } else { ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.28); ctx.fill(); }
      }
      if (burst.queue.length) requestAnimationFrame(frame); else { ctx.clearRect(0, 0, innerWidth, innerHeight); burst.running = false; }
    })();
  }
  function drawStar(ctx, x, y, r) {
    ctx.beginPath();
    for (let i = 0; i < 8; i++) { const rr = i % 2 ? r * 0.35 : r, a = (i * Math.PI) / 4; ctx.lineTo(x + Math.cos(a) * rr, y + Math.sin(a) * rr); }
    ctx.closePath(); ctx.fill();
  }
  function fireworks(times = 5) {
    for (let i = 0; i < times; i++) setTimeout(() => burst(innerWidth * (0.2 + Math.random() * 0.6), innerHeight * (0.2 + Math.random() * 0.35), 70), i * 320);
  }

  /* شرارة صغيرة عند الضغط على الأزرار */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('.btn--primary, .hcard, .lvl');
    if (b) burst(e.clientX, e.clientY, 18);
  });

  /* ---------- العدّادات ---------- */
  const fmt = new Intl.NumberFormat('en-US');
  function countUp(el) {
    const to = +el.dataset.num || 0;
    if (reduce || to === 0) { el.textContent = fmt.format(to); return; }
    const t0 = performance.now(), dur = 1400;
    (function step(t) {
      const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
      el.textContent = fmt.format(Math.round(to * e));
      if (k < 1) requestAnimationFrame(step);
    })(t0);
  }

  /* ---------- الظهور عند التمرير ---------- */
  const reveals = document.querySelectorAll('.reveal, [data-num]');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver((ents) => ents.forEach((en) => {
      if (!en.isIntersecting) return;
      en.target.classList.add('is-in');
      if (en.target.dataset.num !== undefined) countUp(en.target);
      io.unobserve(en.target);
    }), { threshold: 0.15 });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => { el.classList.add('is-in'); if (el.dataset.num !== undefined) el.textContent = fmt.format(+el.dataset.num || 0); });
  }

  /* ---------- إمالة البطاقات مع الماوس ---------- */
  if (!reduce && matchMedia('(hover: hover)').matches) {
    document.querySelectorAll('[data-tilt]').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = `perspective(700px) rotateY(${x * 10}deg) rotateX(${-y * 10}deg) translateY(-4px)`;
        card.style.setProperty('--gx', `${(x + 0.5) * 100}%`);
        card.style.setProperty('--gy', `${(y + 0.5) * 100}%`);
      });
      card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
  }

  /* ---------- المسكوت: يقفز أعلى عند الضغط عليه ---------- */
  const hop = $('.hero__jump');
  if (hop) hop.addEventListener('click', (e) => {
    hop.classList.remove('is-super'); void hop.offsetWidth; hop.classList.add('is-super');
    burst(e.clientX, e.clientY, 30);
  });

  /* ---------- نقاط الشريط العلوي ---------- */
  function bumpPoints(add) {
    const el = $('.nav__pts');
    if (!el) return;
    const n = (parseInt(el.textContent.replace(/\D/g, ''), 10) || 0) + add;
    el.textContent = fmt.format(n);
    el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
  }

  window.KTfx = { burst, fireworks, bumpPoints, countUp };
})();

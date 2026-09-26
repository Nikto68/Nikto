(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var root = document.documentElement;
  var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function fa(n) { n = Number(n) || 0; try { return n.toLocaleString('fa-IR'); } catch (e) { return String(Math.round(n)); } }
  function num(v) { return Number(String(v == null ? '' : v).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[^\d.]/g, '')) || 0; }

  var toastEl = null, toastT = 0;
  function toast(msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'alert a-ok g toast-pop'; document.body.appendChild(toastEl);
      toastEl.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:200;margin:0;transition:opacity .3s;opacity:0;pointer-events:none'; }
    toastEl.textContent = msg; toastEl.style.opacity = '1';
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.style.opacity = '0'; }, 2400);
  }

  var sky = (function () {
    var c = $('#sky'), ctx = c && c.getContext ? c.getContext('2d') : null, api = { theme: function () {}, mouse: function () {} };
    if (!ctx) return api;
    var dpr = Math.min(2, window.devicePixelRatio || 1), W = 0, H = 0, stars = [], shoots = [], mx = 0, my = 0, tx = 0, ty = 0, rgb = '255,255,255', raf = 0, next = 0;
    var cols = ['255,255,255', '255,214,120', '147,197,253', '216,180,254'];
    function readTheme() { rgb = getComputedStyle(root).getPropertyValue('--star').trim() || '255,255,255'; }
    function size() {
      W = window.innerWidth; H = window.innerHeight;
      c.width = W * dpr; c.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.round(Math.min(190, W * H / 7500)); stars = [];
      for (var i = 0; i < n; i++) stars.push({ x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.25 + .25, z: Math.random() * .9 + .1, p: Math.random() * 6.28, s: Math.random() * 1.6 + .4, c: cols[(Math.random() * cols.length) | 0] });
    }
    function draw(t) {
      tx += (mx - tx) * .04; ty += (my - ty) * .04;
      ctx.clearRect(0, 0, W, H);
      var sy = (window.scrollY || 0) * .06, light = rgb !== '255,255,255';
      for (var i = 0; i < stars.length; i++) {
        var s = stars[i], a = .25 + .75 * (.5 + .5 * Math.sin(s.p + t * .001 * s.s));
        var x = s.x + tx * s.z * 14, y = ((s.y - sy * s.z) % H + H) % H + ty * s.z * 10;
        ctx.globalAlpha = a * (light ? .35 : 1);
        ctx.fillStyle = 'rgb(' + (light ? rgb : s.c) + ')';
        ctx.beginPath(); ctx.arc(x, y, s.r, 0, 6.2832); ctx.fill();
        if (s.r > 1.2 && !light) { ctx.globalAlpha = a * .18; ctx.beginPath(); ctx.arc(x, y, s.r * 4, 0, 6.2832); ctx.fill(); }
      }
      if (!light && !REDUCE && t > next) {
        next = t + 2600 + Math.random() * 4200;
        shoots.push({ x: Math.random() * W * .8 + W * .2, y: Math.random() * H * .4, vx: -(6 + Math.random() * 4), vy: 2.2 + Math.random() * 1.5, l: 1 });
      }
      for (var j = shoots.length - 1; j >= 0; j--) {
        var m = shoots[j]; m.x += m.vx; m.y += m.vy; m.l -= .012;
        if (m.l <= 0) { shoots.splice(j, 1); continue; }
        var gr = ctx.createLinearGradient(m.x, m.y, m.x - m.vx * 16, m.y - m.vy * 16);
        gr.addColorStop(0, 'rgba(255,255,255,' + m.l + ')'); gr.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.globalAlpha = 1; ctx.strokeStyle = gr; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - m.vx * 16, m.y - m.vy * 16); ctx.stroke();
      }
      ctx.globalAlpha = 1;
    }
    function loop(t) { draw(t); raf = requestAnimationFrame(loop); }
    readTheme(); size();
    var rt = 0;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { size(); if (REDUCE) draw(0); }, 150); });
    if (REDUCE) draw(0);
    else {
      raf = requestAnimationFrame(loop);
      document.addEventListener('visibilitychange', function () { cancelAnimationFrame(raf); if (!document.hidden) raf = requestAnimationFrame(loop); });
    }
    api.theme = function () { readTheme(); if (REDUCE) draw(0); };
    api.mouse = function (x, y) { mx = (x / W - .5) * 2; my = (y / H - .5) * 2; };
    return api;
  })();

  var th = $('#theme');
  if (th) th.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('ns-theme', next); } catch (e) {}
    sky.theme();
  });

  function toggler(btnSel, boxSel) {
    var b = $(btnSel), box = $(boxSel);
    if (!b || !box) return;
    b.addEventListener('click', function (e) { e.stopPropagation(); var o = box.classList.toggle('open'); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    document.addEventListener('click', function (e) { if (box.classList.contains('open') && !box.contains(e.target)) box.classList.remove('open'); });
  }
  toggler('#menuBtn', '#drawer');
  toggler('#sideBtn', '#side');

  var topBtn = $('#toTop');
  if (topBtn) {
    window.addEventListener('scroll', function () { topBtn.classList.toggle('show', (window.scrollY || 0) > 700); }, { passive: true });
    topBtn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }

  var rv = $$('.rv');
  if ('IntersectionObserver' in window && !REDUCE) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -40px 0px', threshold: .06 });
    rv.forEach(function (el) { io.observe(el); });
  } else rv.forEach(function (el) { el.classList.add('in'); });

  var spot = $('#spot'), pend = 0;
  if (window.matchMedia && window.matchMedia('(hover: hover)').matches && !REDUCE) {
    document.addEventListener('pointermove', function (e) {
      if (pend) return;
      pend = requestAnimationFrame(function () {
        pend = 0;
        if (spot) { spot.style.setProperty('--cx', e.clientX + 'px'); spot.style.setProperty('--cy', e.clientY + 'px'); }
        var g = e.target && e.target.closest ? e.target.closest('.g') : null;
        if (g) { var r = g.getBoundingClientRect(); g.style.setProperty('--mx', (e.clientX - r.left) + 'px'); g.style.setProperty('--my', (e.clientY - r.top) + 'px'); }
        sky.mouse(e.clientX, e.clientY);
      });
    }, { passive: true });
  }

  $$('.rot[data-words]').forEach(function (el) {
    if (REDUCE) return;
    var words = []; try { words = JSON.parse(el.getAttribute('data-words')) || []; } catch (e) {}
    if (words.length < 2) return;
    var i = 0;
    setInterval(function () {
      el.classList.add('out');
      setTimeout(function () { i = (i + 1) % words.length; el.textContent = words[i]; el.classList.remove('out'); el.classList.add('pre'); void el.offsetWidth; el.classList.remove('pre'); }, 350);
    }, 2600);
  });

  function qtyForm(f, onChange) {
    var inp = $('input[name=qty]', f); if (!inp) return;
    var min = Number(inp.min) || 1, max = Number(inp.max) || 1e9, rng = $('[data-range]', f);
    function set(v, fromInput) {
      v = Math.max(min, Math.min(max, Math.round(v) || min));
      if (!fromInput) inp.value = v;
      if (rng) rng.value = Math.min(Number(rng.max), v);
      $$('[data-q]', f).forEach(function (b) { b.classList.toggle('on', Number(b.getAttribute('data-q')) === v); });
      onChange(v);
    }
    inp.addEventListener('input', function () { set(num(inp.value), true); });
    inp.addEventListener('blur', function () { set(num(inp.value)); });
    if (rng) rng.addEventListener('input', function () { set(Number(rng.value)); });
    $$('[data-step]', f).forEach(function (b) { b.addEventListener('click', function () { set(num(inp.value) + Number(b.getAttribute('data-step'))); }); });
    $$('[data-q]', f).forEach(function (b) { b.addEventListener('click', function () { set(Number(b.getAttribute('data-q'))); }); });
    set(num(inp.value), true);
  }
  $$('[data-calc],[data-buy]').forEach(function (f) {
    var price = Number(f.getAttribute('data-price')) || 0, per = Number(f.getAttribute('data-per')) || 1, out = $('[data-total]', f);
    if (f.hasAttribute('data-buy') && f.getAttribute('data-unit') !== '1') return;
    qtyForm(f, function (q) { if (out) out.textContent = fa(Math.ceil(price * q / per)); });
  });

  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]');
    if (c) {
      var t = c.getAttribute('data-copy');
      var ok = function () { toast('کپی شد'); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(ok, function () {});
      else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (x) {} ta.remove(); }
      return;
    }
    var s = e.target.closest('[data-set]');
    if (s) { var f = s.closest('form'), i = f && f.querySelector('[name="' + s.getAttribute('data-set') + '"]'); if (i) i.value = s.getAttribute('data-v'); return; }
    var tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button,input,form')) location.href = tr.getAttribute('data-href');
  });

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var b = f.querySelector('button[type=submit]:not([name])');
      if (b && !f.hasAttribute('data-noblock')) setTimeout(function () { b.disabled = true; b.setAttribute('data-busy', '1'); }, 0);
    });
  });
  window.addEventListener('pageshow', function () { $$('[data-busy]').forEach(function (b) { b.disabled = false; b.removeAttribute('data-busy'); }); });

  $$('.file input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () { var l = inp.parentNode.querySelector('[data-file]'); if (l && inp.files[0]) l.textContent = inp.files[0].name; });
  });

  $$('[data-filter]').forEach(function (inp) {
    var list = $(inp.getAttribute('data-filter')), none = $('#noResult');
    if (!list) return;
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase(), n = 0;
      Array.prototype.forEach.call(list.children, function (it) { var ok = !q || (it.getAttribute('data-name') || it.textContent).toLowerCase().indexOf(q) !== -1; it.style.display = ok ? '' : 'none'; if (ok) n++; });
      if (none) none.hidden = n > 0;
    });
  });

  var pf = $('[data-pform]');
  if (pf) {
    var sel = $('[data-pricing]', pf), box = $('.unit-only', pf);
    var sync = function () { if (box) box.style.display = sel.value === 'unit' ? '' : 'none'; };
    sel.addEventListener('change', sync); sync();
  }

  var poll = $('[data-poll]');
  if (poll) {
    var state = poll.getAttribute('data-state') || '', url = poll.getAttribute('data-poll'), tries = 0, hadCode = !!$('.code-box');
    var tick = function () {
      if (document.hidden) { setTimeout(tick, 4000); return; }
      fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j || !j.ok) return;
        if (state.split('|')[0] !== j.status || (j.code && !hadCode)) { location.reload(); return; }
        if (++tries < 400) setTimeout(tick, j.status === 'processing' && j.phone ? 5000 : 8000);
      }).catch(function () { if (++tries < 400) setTimeout(tick, 10000); });
    };
    setTimeout(tick, 5000);
  }
})();

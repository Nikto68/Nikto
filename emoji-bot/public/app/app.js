(() => {
  'use strict';

  const tg = window.Telegram && window.Telegram.WebApp;
  const $ = (id) => document.getElementById(id);
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const fa = (n) => Number(n || 0).toLocaleString('en-US');
  const ver = (v) => !!(tg && tg.isVersionAtLeast && tg.isVersionAtLeast(v));

  // ---------- storage (optional, per device) ----------
  const store = {
    get(k, d) { try { const v = localStorage.getItem('eb:' + k); return v === null ? d : v; } catch { return d; } },
    set(k, v) { try { localStorage.setItem('eb:' + k, v); } catch { /* ignore */ } },
  };

  // ---------- DOM helper (never uses innerHTML for data) ----------
  function h(tag, attrs, ...kids) {
    const el = document.createElement(tag);
    if (attrs) {
      for (const [k, v] of Object.entries(attrs)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else if (k === 'style') Object.assign(el.style, v);
        else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
        else if (k === 'value') el.value = v;
        else el.setAttribute(k, v === true ? '' : String(v));
      }
    }
    for (const kid of kids.flat(Infinity)) {
      if (kid === null || kid === undefined || kid === false) continue;
      el.append(kid instanceof Node ? kid : document.createTextNode(String(kid)));
    }
    return el;
  }

  /** replaceChildren() that also accepts nested arrays and skips null/false. */
  function fill(el, ...kids) {
    el.replaceChildren(...kids.flat(Infinity).filter((k) => k !== null && k !== undefined && k !== false));
  }

  const ICONS = {
    check: 'M5 12.5l4.5 4.5L19 7.5',
    checks: 'M2 12.5l4 4 9-9M11 16.5l1 1 9-9',
    chevron: 'M9 6l6 6-6 6',
    link: 'M10 14a5 5 0 0 0 7.1 0l3-3a5 5 0 0 0-7.1-7.1l-1 1M14 10a5 5 0 0 0-7.1 0l-3 3a5 5 0 0 0 7.1 7.1l1-1',
    plus: 'M12 5v14M5 12h14',
    trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3',
    x: 'M6 6l12 12M18 6L6 18',
    edit: 'M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4',
    copy: 'M9 9h10v10H9zM5 15V5h10',
    share: 'M12 15V3M7 8l5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4',
  };
  function icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', ICONS[name]);
    svg.append(p);
    return svg;
  }

  // ---------- haptics / toast ----------
  const hap = {
    sel() { try { tg.HapticFeedback.selectionChanged(); } catch { /* */ } },
    tap() { try { tg.HapticFeedback.impactOccurred('light'); } catch { /* */ } },
    ok() { try { tg.HapticFeedback.notificationOccurred('success'); } catch { /* */ } },
    err() { try { tg.HapticFeedback.notificationOccurred('error'); } catch { /* */ } },
  };
  let toastTimer = 0;
  function toast(msg, err = false) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast' + (err ? ' err' : '');
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.hidden = true; }, 2800);
    if (err) hap.err();
  }

  // ---------- API ----------
  class ApiErr extends Error {
    constructor(msg, code) { super(msg); this.code = code; }
  }
  async function request(body, isForm) {
    let res;
    try {
      res = await fetch('../api.php', {
        method: 'POST',
        headers: isForm ? { 'X-Init-Data': tg.initData } : { 'Content-Type': 'application/json', 'X-Init-Data': tg.initData },
        body,
      });
    } catch {
      throw new ApiErr('Check your internet connection.', 'network');
    }
    let j = null;
    try { j = await res.json(); } catch { /* */ }
    if (!j) throw new ApiErr('Could not reach the server.', 'network');
    if (!j.ok) throw new ApiErr(j.error || 'Error', j.code || 'error');
    return j;
  }
  const api = (a, data = {}) => request(JSON.stringify({ a, ...data }), false);
  function apiForm(a, fields) {
    const fd = new FormData();
    fd.append('a', a);
    for (const [k, v] of Object.entries(fields)) fd.append(k, v);
    return request(fd, true);
  }

  // ---------- state ----------
  const S = {
    boot: null,
    user: null,
    cfg: null,
    text: store.get('text', ''),
    font: store.get('font', 'montserrat'),
    tint: store.get('tint', '0') === '1',
    mode: store.get('mode', 'text') === 'logo' ? 'logo' : 'text',
    logo: store.get('logo', ''),
    logoMode: store.get('logoMode', 'original'),
    c1: store.get('c1', '#ff2d55'),
    c2: store.get('c2', '#ffffff'),
    size: parseFloat(store.get('size', '1')) || 1,
    dy: parseFloat(store.get('dy', '0')) || 0,
    selected: new Set(),
    collapsed: new Set(safeJson(store.get('collapsed', '[]'), [])),
    target: 0,
    tiles: new Map(),
    cats: [],
    cache: new Map(),
    queue: new Set(),
    inflight: 0,
    textError: '',
  };
  function safeJson(s, d) { try { return JSON.parse(s); } catch { return d; } }

  const PALETTE = ['#ff2d55', '#ff3b30', '#ff9500', '#ffcc00', '#34c759', '#00c7be', '#2aabee', '#007aff', '#5856d6', '#af52de', '#ff2dc0', '#a2845e', '#8e8e93', '#3a3a3c', '#1c1c1e', '#ffffff'];
  const COMBOS = [['#ff2d55', '#ffffff'], ['#2aabee', '#ffffff'], ['#1c1c1e', '#ffffff'], ['#ffffff', '#1c1c1e'], ['#ffcc00', '#1c1c1e'], ['#34c759', '#fff59d'], ['#af52de', '#ffd1f7'], ['#ff9500', '#fff3e0']];

  const placeholderText = () => 'Emoji';
  const currentParams = () => {
    const base = { font: S.font, c1: S.c1, c2: S.c2, size: S.size, dy: S.dy, tint: S.tint };
    if (S.mode === 'logo') return S.logo ? { ...base, logo: S.logo, logo_mode: S.logoMode } : { ...base, text: 'LOGO' };
    return { ...base, text: S.text.trim() || placeholderText() };
  };
  const hasContent = () => (S.mode === 'logo' ? !!S.logo : S.text.trim() !== '');
  const paramsKey = () => JSON.stringify(currentParams());

  // ---------- navigation (Telegram BackButton) ----------
  const navStack = [];
  function navPush(fn) {
    navStack.push(fn);
    try { tg.BackButton.show(); } catch { /* */ }
  }
  function navPop() {
    navStack.pop();
    if (!navStack.length) { try { tg.BackButton.hide(); } catch { /* */ } }
  }
  function navBack() {
    const fn = navStack[navStack.length - 1];
    if (fn) fn();
  }

  // ---------- sheet ----------
  let sheetClose = null;
  let sheetLocked = false;
  function openSheet(content, { onClose = null, locked = false } = {}) {
    const wasOpen = !$('sheet').hidden;
    fill($('sheetBody'), content);
    $('sheet').hidden = false;
    sheetLocked = locked;
    sheetClose = onClose;
    if (!wasOpen) navPush(() => closeSheet());
  }
  function closeSheet(force = false) {
    if (sheetLocked && !force) return;
    if ($('sheet').hidden) return;
    $('sheet').hidden = true;
    $('sheetBody').replaceChildren();
    navPop();
    const cb = sheetClose;
    sheetClose = null;
    sheetLocked = false;
    if (cb) cb();
  }
  $('sheet').addEventListener('click', (e) => { if (e.target.hasAttribute('data-close')) closeSheet(); });

  // ---------- theme ----------
  function applyTheme() {
    const light = tg && tg.colorScheme === 'light';
    document.body.classList.toggle('light', !!light);
    const bg = getComputedStyle(document.body).getPropertyValue('--bg').trim();
    try {
      if (ver('6.1')) { tg.setHeaderColor(bg); tg.setBackgroundColor(bg); }
      if (ver('7.10')) tg.setBottomBarColor(bg);
    } catch { /* */ }
  }

  // ---------- gates ----------
  function showGate(emoji, title, text, button) {
    $('loader').hidden = true;
    $('app').hidden = true;
    const g = $('gate');
    fill(g, h('div', { class: 'big', text: emoji }), h('h2', { text: title }), h('p', { text }), button || null);
    g.hidden = false;
  }

  function showJoinGate() {
    const url = S.boot.join.url;
    const check = h('button', { class: 'main-btn secondary', type: 'button', text: "✅ I've joined" });
    check.onclick = async () => {
      check.disabled = true;
      try {
        const r = await api('check_join');
        if (r.joined) { hap.ok(); $('gate').hidden = true; S.boot.join = null; start(); } else toast("You haven't joined the channel yet", true);
      } catch (e) { toast(e.message, true); }
      check.disabled = false;
    };
    const join = h('button', { class: 'main-btn', type: 'button', text: '📢 Join channel', onclick: () => tg.openTelegramLink(url) });
    showGate('🔒', 'Join our channel', 'Please join our channel to use the bot.', h('div', { class: 'stack', style: { width: '100%', maxWidth: '340px' } }, join, check));
  }

  // ---------- boot ----------
  async function boot() {
    if (!tg || !tg.initData) {
      showGate('📱', 'Open in Telegram', 'Please open this page from the bot inside Telegram.');
      return;
    }
    tg.ready();
    tg.expand();
    try { if (ver('7.7')) tg.disableVerticalSwipes(); } catch { /* */ }
    applyTheme();
    tg.onEvent('themeChanged', applyTheme);
    tg.BackButton.onClick(navBack);
    try {
      S.boot = await api('boot');
    } catch (e) {
      showGate('⚠️', 'Error', e.message, h('button', { class: 'main-btn', type: 'button', text: 'Try again', onclick: () => location.reload() }));
      return;
    }
    S.user = S.boot.user;
    S.cfg = S.boot.config;
    if (S.cfg.maintenance) {
      showGate('🛠', 'Under maintenance', "We're updating the bot. Please check back soon.");
      return;
    }
    if (S.boot.join) {
      showJoinGate();
      return;
    }
    start();
  }

  function start() {
    if (!S.boot.fonts.some((f) => f.id === S.font)) S.font = S.boot.fonts[0].id;
    $('loader').hidden = true;
    $('app').hidden = false;
    renderHeader();
    renderEditor();
    renderCats();
    refreshSel();
    onParamsChange(true);
    const hashRoute = /^#(\w+)$/.test(location.hash) ? location.hash.slice(1) : '';
    const route = new URLSearchParams(location.search).get('p') || hashRoute || (tg.initDataUnsafe && tg.initDataUnsafe.start_param) || '';
    if (route === 'packs') openPacks();
    else if (route === 'shop') openShop();
    else if (route === 'gift') openGift();
    else if (route === 'admin' && S.user.admin) openAdmin();
    if (S.boot.job) showProgress(S.boot.job);
    const u = tg.initDataUnsafe && tg.initDataUnsafe.user;
    if (u && u.allows_write_to_pm === false && ver('6.9')) {
      try { tg.requestWriteAccess(); } catch { /* */ }
    }
  }

  // ---------- header ----------
  function renderHeader() {
    updateCoins();
    const u = tg.initDataUnsafe && tg.initDataUnsafe.user;
    const av = $('avatar');
    if (u && u.photo_url && /^https:\/\//.test(u.photo_url)) av.style.backgroundImage = `url("${u.photo_url.replace(/"/g, '')}")`;
    else av.textContent = (S.user.name || '?').trim().charAt(0).toUpperCase();
    $('adminBtn').hidden = !S.user.admin;
    $('coinsBtn').onclick = () => { hap.tap(); openShop(); };
    $('giftBtn').onclick = () => { hap.tap(); openGift(); };
    $('packsBtn').onclick = () => { hap.tap(); openPacks(); };
    $('adminBtn').onclick = () => { hap.tap(); openAdmin(); };
  }
  function updateCoins() {
    $('coins').textContent = fa(S.user.coins);
    $('giftDot').hidden = !(S.cfg.daily_gift > 0 && S.user.gift_in === 0);
  }

  // ---------- editor ----------
  let paramsTimer = 0;
  function renderEditor() {
    const input = $('textInput');
    input.value = S.text;
    input.maxLength = S.cfg.text_max + 8;
    input.addEventListener('input', () => {
      let v = input.value.replace(/\s+/g, ' ');
      const chars = [...v];
      if (chars.length > S.cfg.text_max) { v = chars.slice(0, S.cfg.text_max).join(''); input.value = v; }
      S.text = v;
      store.set('text', v);
      updateCounter();
      schedule();
    });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') input.blur(); });
    updateCounter();

    const size = $('sizeInput');
    const dy = $('dyInput');
    size.value = S.size;
    dy.value = S.dy;
    const sync = () => {
      $('sizeVal').textContent = S.size.toFixed(1);
      $('dyVal').textContent = (S.dy > 0 ? '+' : '') + S.dy.toFixed(1).replace('.0', '').replace('-0', '0');
    };
    size.addEventListener('input', () => { S.size = parseFloat(size.value); store.set('size', S.size); sync(); schedule(); });
    dy.addEventListener('input', () => { S.dy = parseFloat(dy.value); store.set('dy', S.dy); sync(); schedule(); });
    sync();
    paintSwatches();
    $('fontBtn').onclick = () => { hap.tap(); openFonts(); };
    for (const b of $('modeTabs').querySelectorAll('button')) {
      b.onclick = () => { hap.sel(); setMode(b.dataset.mode); };
    }
    $('logoInput').addEventListener('change', uploadLogo);
    if (!(S.boot.logos || []).some((l) => l.ref === S.logo)) S.logo = '';
    setMode(S.mode, true);
    $('tintBtn').onclick = () => {
      hap.sel();
      S.tint = !S.tint;
      store.set('tint', S.tint ? '1' : '0');
      paintSwatches();
      toast(S.tint ? '🎨 Trending templates now use your main color' : 'Templates are back to their original colors');
      schedule();
    };
    $('c1Btn').onclick = () => { hap.tap(); openColors(1); };
    $('c2Btn').onclick = () => { hap.tap(); openColors(2); };
    $('targetBtn').onclick = () => { hap.tap(); openTarget(); };
    $('createBtn').onclick = () => { hap.tap(); openCreate(); };
    renderTarget();
  }
  // ---------- logo mode ----------
  const LOGO_MODES = [['original', 'Original'], ['c1', 'Color 1'], ['c2', 'Color 2'], ['duo', 'Two-tone']];
  function setMode(mode, initial = false) {
    S.mode = mode === 'logo' ? 'logo' : 'text';
    store.set('mode', S.mode);
    for (const b of $('modeTabs').querySelectorAll('button')) b.classList.toggle('on', b.dataset.mode === S.mode);
    const logo = S.mode === 'logo';
    $('textBox').hidden = logo;
    $('logoRow').hidden = !logo;
    $('logoModes').hidden = !logo;
    if (logo) setTextError('');
    renderLogos();
    refreshSel();
    if (!initial) schedule();
  }
  function renderLogos() {
    const list = $('logoList');
    const items = (S.boot.logos || []).map((l) => {
      const b = h('button', { type: 'button', class: l.ref === S.logo ? 'on' : '', 'aria-label': 'Logo' });
      b.style.backgroundImage = `url("${l.src}")`;
      b.onclick = () => { hap.sel(); S.logo = l.ref; store.set('logo', S.logo); renderLogos(); refreshSel(); schedule(); };
      return b;
    });
    fill(list, items);
    const cur = (S.boot.logos || []).find((l) => l.ref === S.logo);
    const thumb = $('logoThumb');
    thumb.style.backgroundImage = cur ? `url("${cur.src}")` : '';
    thumb.textContent = cur ? '' : '+';
    const modes = LOGO_MODES.map(([id, label]) => {
      const dot = id === 'original' ? null : h('i', { style: { background: id === 'c1' ? S.c1 : id === 'c2' ? S.c2 : `linear-gradient(90deg, ${S.c1} 50%, ${S.c2} 50%)` } });
      return h('button', { type: 'button', class: S.logoMode === id ? 'on' : '', onclick: () => { hap.sel(); S.logoMode = id; store.set('logoMode', id); renderLogos(); schedule(); } }, dot, label);
    });
    fill($('logoModes'), modes);
  }
  async function uploadLogo() {
    const input = $('logoInput');
    const f = input.files && input.files[0];
    input.value = '';
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) { toast('The logo must be 5 MB or smaller', true); return; }
    toast('Uploading logo…');
    try {
      const r = await apiForm('logo_upload', { image: f });
      S.boot.logos = r.logos;
      S.logo = r.logo;
      store.set('logo', S.logo);
      hap.ok();
      toast('✅ Logo ready — plain background removed automatically');
      renderLogos();
      refreshSel();
      schedule();
    } catch (e) { toast(e.message, true); }
  }

  function schedule() {
    clearTimeout(paramsTimer);
    paramsTimer = setTimeout(() => onParamsChange(false), 380);
    markStale();
  }
  function updateCounter() {
    const n = [...S.text].length;
    const c = $('counter');
    c.textContent = `${n}/${S.cfg.text_max}`;
    c.classList.toggle('over', n >= S.cfg.text_max);
    refreshSel();
  }
  function paintSwatches() {
    $('c1Btn').style.background = S.c1;
    $('c2Btn').style.background = S.c2;
    $('fontBtn').style.fontFamily = (S.boot.fonts.find((f) => f.id === S.font) || {}).family || '';
    $('tintBtn').classList.toggle('on', S.tint);
    if (S.mode === 'logo') renderLogos();
    document.documentElement.style.setProperty('--c1', S.c1);
  }
  function setTextError(msg) {
    S.textError = msg || '';
    const el = $('textError');
    el.textContent = S.textError;
    el.hidden = !S.textError;
    refreshSel();
  }
  function localTextError() {
    if (S.mode === 'logo') return '';
    if (/\p{Extended_Pictographic}/u.test(S.text)) return 'Emoji are not allowed in the text — letters and numbers only.';
    if (S.cfg.latin_only && /[^\x20-\x7E]/.test(S.text)) return 'English letters, numbers and basic symbols only (e.g. Sina)';
    return '';
  }

  // ---------- previews ----------
  let observer = null;
  function markStale() {
    if (localTextError()) return;
    const key = paramsKey();
    for (const t of S.tiles.values()) {
      if (t.key !== key && t.key !== null) t.el.classList.add('loading');
    }
  }
  function clearLoading() {
    for (const t of S.tiles.values()) t.el.classList.remove('loading');
  }
  function onParamsChange() {
    const localErr = localTextError();
    setTextError(localErr);
    if (localErr) { S.queue.clear(); clearLoading(); return; }
    const key = paramsKey();
    S.queue.clear();
    for (const [id, t] of S.tiles) {
      const hit = S.cache.get(key + '|' + id);
      if (hit) applyPreview(t, hit, key);
      else if (t.key !== key) {
        if (t.key !== null) t.el.classList.add('loading');
        if (t.visible) S.queue.add(id);
      }
    }
    pump();
  }
  function applyPreview(t, p, key) {
    t.img.style.backgroundImage = `url("${p.src}")`;
    t.img.classList.toggle('sprite', p.frames > 1);
    t.el.classList.remove('loading', 'empty');
    t.key = key;
  }
  function pump() {
    if (S.textError) return;
    while (S.inflight < 2 && S.queue.size) {
      const ids = [...S.queue].slice(0, 12);
      ids.forEach((i) => S.queue.delete(i));
      const key = paramsKey();
      const params = currentParams();
      S.inflight++;
      api('preview', { ids, params })
        .then((r) => {
          for (const [id, p] of Object.entries(r.items || {})) {
            S.cache.set(key + '|' + id, p);
            if (S.cache.size > 300) S.cache.delete(S.cache.keys().next().value);
            const t = S.tiles.get(id);
            if (t && key === paramsKey()) applyPreview(t, p, key);
          }
          if (key === paramsKey()) setTextError('');
        })
        .catch((e) => {
          if (e.code === 'invalid') { setTextError(e.message); clearLoading(); }
          else if (e.code === 'rate') { ids.forEach((i) => S.queue.add(i)); setTimeout(pump, 3000); }
          else toast(e.message, true);
        })
        .finally(() => { S.inflight--; pump(); });
    }
  }

  // ---------- categories ----------
  function renderCats() {
    const root = $('cats');
    root.replaceChildren();
    S.tiles.clear();
    if (observer) observer.disconnect();
    observer = new IntersectionObserver((entries) => {
      let added = false;
      const key = paramsKey();
      for (const en of entries) {
        const t = S.tiles.get(en.target.dataset.id);
        if (!t) continue;
        t.visible = en.isIntersecting;
        if (en.isIntersecting && t.key !== key) {
          const hit = S.cache.get(key + '|' + t.it.id);
          if (hit) applyPreview(t, hit, key);
          else { S.queue.add(t.it.id); added = true; }
        }
      }
      if (added) pump();
    }, { rootMargin: '300px 0px' });

    const ids = new Set();
    let n = 0;
    S.cats = S.boot.categories;
    for (const cat of S.cats) {
      const grid = h('div', { class: 'grid' });
      const selBtn = h('button', { class: 'sel-all', type: 'button' }, icon('checks'), h('span', { text: 'Select all' }));
      const section = h('section', { class: 'cat' + (S.collapsed.has(cat.id) ? ' collapsed' : '') });
      const toggleBtn = h('button', { class: 'cat-toggle', type: 'button' }, icon('chevron'), h('span', { class: 'cat-title', text: cat.title }));
      toggleBtn.onclick = () => {
        hap.sel();
        section.classList.toggle('collapsed');
        if (section.classList.contains('collapsed')) S.collapsed.add(cat.id); else S.collapsed.delete(cat.id);
        store.set('collapsed', JSON.stringify([...S.collapsed]));
      };
      selBtn.onclick = () => {
        hap.sel();
        const all = cat.items.every((i) => S.selected.has(i.id));
        for (const i of cat.items) {
          if (all) S.selected.delete(i.id);
          else if (S.selected.size < S.cfg.max_per_pack) S.selected.add(i.id);
        }
        if (!all && cat.items.some((i) => !S.selected.has(i.id))) toast(`Up to ${fa(S.cfg.max_per_pack)} emoji per pack`);
        refreshSel();
      };
      cat.selBtn = selBtn;
      section.append(h('div', { class: 'cat-head' }, toggleBtn, selBtn, h('span', { class: 'cat-count', text: fa(cat.items.length) })), grid);
      for (const it of cat.items) {
        ids.add(it.id);
        n++;
        const img = h('div', { class: 'img' });
        const el = h('button', { class: 'tile empty', type: 'button', 'data-id': it.id, 'aria-label': it.title },
          h('span', { class: 'num', text: '#' + n }),
          h('span', { class: 'check' }, icon('check')),
          img,
          h('div', { class: 'spin' }, h('i')),
          it.animated ? h('span', { class: 'anim-badge', text: '✨' }) : null);
        el.onclick = () => toggleTile(it.id);
        const t = { el, img, it, key: null, visible: false };
        S.tiles.set(it.id, t);
        grid.append(el);
        observer.observe(el);
      }
      root.append(section);
    }
    for (const id of [...S.selected]) if (!ids.has(id)) S.selected.delete(id);
    if (!n) root.append(h('div', { class: 'empty-state' }, h('span', { class: 'big', text: '🧩' }), 'No templates are enabled yet.'));
  }

  function toggleTile(id) {
    if (S.selected.has(id)) S.selected.delete(id);
    else if (S.selected.size >= S.cfg.max_per_pack) { toast(`Up to ${fa(S.cfg.max_per_pack)} emoji per pack`, true); return; } else S.selected.add(id);
    hap.sel();
    refreshSel();
  }

  function refreshSel() {
    if (!S.cfg) return;
    for (const [id, t] of S.tiles) t.el.classList.toggle('sel', S.selected.has(id));
    for (const cat of S.cats) {
      if (cat.selBtn) cat.selBtn.classList.toggle('on', cat.items.length > 0 && cat.items.every((i) => S.selected.has(i.id)));
    }
    const n = S.selected.size;
    const btn = $('createBtn');
    const hasText = hasContent();
    btn.disabled = !hasText || n === 0 || !!S.textError;
    btn.replaceChildren();
    if (!hasText) btn.textContent = S.mode === 'logo' ? 'Upload your logo first' : 'Type your text first';
    else if (!n) btn.textContent = 'Select templates';
    else {
      btn.append((S.target ? 'Add to pack' : 'Create pack') + ` (${fa(n)})`);
      if (S.cfg.price > 0) btn.append(h('small', { text: `  •  🪙 ${fa(n * S.cfg.price)}` }));
    }
  }

  // ---------- target (new pack / add to existing) ----------
  function renderTarget() {
    const info = $('targetInfo');
    const pack = S.boot.packs.find((p) => p.id === S.target);
    if (!pack) { S.target = 0; info.hidden = true; $('targetBtn').classList.remove('active'); refreshSel(); return; }
    fill(info, h('span', { text: `➕ Adding to: ${pack.title}` }), h('button', { type: 'button', text: 'Cancel', onclick: () => { S.target = 0; renderTarget(); } }));
    info.hidden = false;
    $('targetBtn').classList.add('active');
    refreshSel();
  }
  function openTarget() {
    const list = h('div', { class: 'chips' });
    const mk = (id, label) => h('button', { class: 'chip' + (S.target === id ? ' on' : ''), type: 'button', text: label, onclick: () => { S.target = id; renderTarget(); closeSheet(); } });
    list.append(mk(0, '✨ New pack'));
    for (const p of S.boot.packs) {
      if (p.count < S.cfg.max_set) list.append(mk(p.id, `${p.title} (${fa(p.count)})`));
    }
    openSheet(h('div', null, h('h2', { text: 'Where should the emoji go?' }), h('p', { text: 'Create a new pack or add to one of your packs.' }), list));
  }

  // ---------- fonts ----------
  function openFonts() {
    const grid = h('div', { class: 'font-grid' });
    for (const f of S.boot.fonts) {
      const b = h('button', { class: 'font-opt' + (f.id === S.font ? ' on' : ''), type: 'button', style: { fontFamily: `"${f.family}", Vazirmatn`, fontSize: /PressStart2P|RubikMonoOne/.test(f.family) ? '12px' : '' } },
        h('span', { text: S.text.trim() || 'Emoji' }), h('small', { text: f.title }));
      b.onclick = () => { hap.sel(); S.font = f.id; store.set('font', f.id); paintSwatches(); schedule(); closeSheet(); };
      grid.append(b);
    }
    openSheet(h('div', null, h('h2', { text: 'Font' }), grid));
  }

  // ---------- colors ----------
  function openColors(which) {
    let cur = which;
    const body = h('div');
    const draw = () => {
      const val = cur === 1 ? S.c1 : S.c2;
      const tabs = h('div', { class: 'tabs' },
        h('button', { type: 'button', class: cur === 1 ? 'on' : '', onclick: () => { cur = 1; draw(); } }, h('i', { style: { background: S.c1 } }), 'Main color'),
        h('button', { type: 'button', class: cur === 2 ? 'on' : '', onclick: () => { cur = 2; draw(); } }, h('i', { style: { background: S.c2 } }), 'Second color'));
      const pal = h('div', { class: 'palette' });
      for (const c of PALETTE) {
        pal.append(h('button', { type: 'button', class: c === val ? 'on' : '', style: { background: c }, 'aria-label': c, onclick: () => setColor(cur, c) }));
      }
      const combos = h('div', { class: 'combos' });
      for (const [a, b] of COMBOS) {
        combos.append(h('button', { class: 'combo', type: 'button', onclick: () => { S.c1 = a; S.c2 = b; saveColors(); } }, h('span', { style: { background: a } }), h('span', { style: { background: b } })));
      }
      const picker = h('input', { type: 'color', value: val });
      picker.addEventListener('input', () => setColor(cur, picker.value.toLowerCase(), false));
      fill(body,
        h('h2', { text: 'Colors' }), tabs, pal,
        h('div', { class: 'custom-color' }, picker, h('span', { text: 'Custom color' })),
        h('h3', { text: 'Presets' }), combos);
    };
    const setColor = (n, c, redraw = true) => {
      if (n === 1) S.c1 = c; else S.c2 = c;
      saveColors(redraw);
    };
    const saveColors = (redraw = true) => {
      hap.sel();
      store.set('c1', S.c1);
      store.set('c2', S.c2);
      paintSwatches();
      schedule();
      if (redraw) draw();
    };
    draw();
    openSheet(body);
  }

  // ---------- create ----------
  function openCreate() {
    const n = S.selected.size;
    if (!n || !hasContent()) return;
    const cost = n * S.cfg.price;
    const pack = S.boot.packs.find((p) => p.id === S.target);
    const title = h('input', { class: 'input', maxlength: '48', value: S.mode === 'logo' ? (store.get('packTitle', '') || 'My Emoji') : S.text.trim(), placeholder: 'Pack name' });
    title.addEventListener('input', () => store.set('packTitle', title.value));
    const enough = S.user.coins >= cost;
    const go = h('button', { class: 'main-btn', type: 'button', text: enough ? (pack ? 'Add emoji' : 'Create pack') : 'Buy coins' });
    go.onclick = async () => {
      if (!enough) { openShop(); return; }
      go.disabled = true;
      go.textContent = '…';
      try {
        const r = await api('create', { params: currentParams(), ids: [...S.selected], title: title.value, pack_id: S.target || 0 });
        S.user.coins = r.coins;
        updateCoins();
        showProgress(r.job_id);
      } catch (e) {
        go.disabled = false;
        go.textContent = pack ? 'Add emoji' : 'Create pack';
        if (e.code === 'coins') openShop();
        else if (e.code === 'busy' && S.boot.job) showProgress(S.boot.job);
        else toast(e.message, true);
      }
    };
    openSheet(h('div', null,
      h('h2', { text: pack ? 'Add to pack' : 'New pack' }),
      pack ? h('div', { class: 'note', text: `Emoji will be added to “${pack.title}”.` }) : h('label', { class: 'field' }, h('span', { text: 'Pack name' }), title),
      h('div', { class: 'summary' },
        h('div', null, h('span', { text: 'Emoji' }), h('b', { text: fa(n) })),
        h('div', null, h('span', { text: 'Cost' }), h('b', { text: cost ? `🪙 ${fa(cost)}` : 'Free' })),
        h('div', null, h('span', { text: 'Your balance' }), h('b', { text: `🪙 ${fa(S.user.coins)}` }))),
      !S.user.premium ? h('div', { class: 'note warn', text: '💎 Using custom emoji in messages requires Telegram Premium.' }) : null,
      go));
  }

  function showProgress(jobId) {
    S.boot.job = jobId;
    const C = 2 * Math.PI * 60;
    const fg = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    const bg = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    for (const c of [bg, fg]) { c.setAttribute('cx', '70'); c.setAttribute('cy', '70'); c.setAttribute('r', '60'); }
    bg.setAttribute('class', 'bg');
    fg.setAttribute('class', 'fg');
    fg.style.strokeDasharray = String(C);
    fg.style.strokeDashoffset = String(C);
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 140 140');
    svg.append(bg, fg);
    const pct = h('div', { class: 'pct', text: '0%' });
    const stage = h('h2', { text: 'In queue…' });
    const sub = h('p', { text: "You can close the app — the bot will message you when it's ready." });
    openSheet(h('div', { class: 'progress-wrap' }, h('div', { class: 'ring' }, svg, pct), stage, sub));
    let alive = true;
    const prevClose = sheetClose;
    sheetClose = () => { alive = false; if (prevClose) prevClose(); };
    const tick = async () => {
      if (!alive) return;
      let j;
      try { j = await api('job', { id: jobId }); } catch (e) { if (alive) setTimeout(tick, 2500); return; }
      if (!alive) return;
      S.user.coins = j.coins;
      updateCoins();
      if (j.status === 'done') { S.boot.job = null; showDone(j.result); return; }
      if (j.status === 'failed') { S.boot.job = null; showFailed(j.error); return; }
      const ratio = j.total ? j.progress / j.total : 0;
      let p = 0.03;
      if (j.stage === 'render') { p = 0.05 + ratio * 0.6; stage.textContent = `Rendering emoji… ${fa(j.progress)}/${fa(j.total)}`; }
      else if (j.stage === 'upload') { p = 0.65 + ratio * 0.35; stage.textContent = `Uploading to Telegram… ${fa(j.progress)}/${fa(j.total)}`; }
      else stage.textContent = 'In queue…';
      fg.style.strokeDashoffset = String(C * (1 - p));
      pct.textContent = Math.round(p * 100) + '%';
      setTimeout(tick, 1200);
    };
    tick();
  }

  async function showDone(r) {
    hap.ok();
    S.selected.clear();
    refreshSel();
    const add = h('button', { class: 'main-btn', type: 'button', text: '➕ Add to Telegram', onclick: () => tg.openTelegramLink(r.link) });
    const again = h('button', { class: 'main-btn secondary', type: 'button', text: 'Make another pack', onclick: () => closeSheet() });
    const done = h('div', { class: 'done-icon' });
    done.append(icon('check'));
    openSheet(h('div', { class: 'progress-wrap' }, done, h('h2', { text: 'Your pack is ready! 🎉' }), h('p', { text: `“${r.title}” — ${fa(r.added)} emoji` }), h('div', { class: 'stack' }, add, again)));
    try {
      const p = await api('packs');
      S.boot.packs = p.packs;
      S.target = r.pack_id && S.target ? r.pack_id : 0;
      renderTarget();
    } catch { /* */ }
  }

  function showFailed(msg) {
    hap.err();
    const x = h('div', { class: 'done-icon err' });
    x.append(icon('x'));
    openSheet(h('div', { class: 'progress-wrap' }, x, h('h2', { text: 'Something went wrong' }), h('p', { text: msg || 'Please try again.' }),
      h('button', { class: 'main-btn secondary', type: 'button', text: 'OK', onclick: () => closeSheet() })));
  }

  // ---------- packs ----------
  function openPacks() {
    const body = h('div');
    const draw = () => {
      const list = h('div');
      if (!S.boot.packs.length) {
        list.append(h('div', { class: 'empty-state' }, h('span', { class: 'big', text: '📦' }), "You haven't made any packs yet."));
      }
      for (const p of S.boot.packs) {
        const cover = h('div', { class: 'cover' + (p.cover && p.cover.frames > 1 ? ' sprite' : '') });
        if (p.cover) cover.style.backgroundImage = `url("${p.cover.src}")`;
        const open = h('button', { type: 'button', 'aria-label': 'Open', onclick: () => tg.openTelegramLink(p.link) }, icon('link'));
        const more = h('button', { class: 'add', type: 'button', 'aria-label': 'Add emoji', onclick: () => { S.target = p.id; renderTarget(); closeSheet(); toast('Select templates, then tap “Add to pack”'); } }, icon('plus'));
        const del = h('button', { class: 'del', type: 'button', 'aria-label': 'Delete', onclick: () => confirmDelete(p, draw) }, icon('trash'));
        list.append(h('div', { class: 'pack' }, cover, h('div', { class: 'info' }, h('b', { text: p.title }), h('small', { text: `${fa(p.count)} emoji` })), h('div', { class: 'acts' }, open, p.count < S.cfg.max_set ? more : null, del)));
      }
      fill(body, h('h2', { text: 'My packs' }), list);
    };
    draw();
    openSheet(body);
  }
  function confirmDelete(p, redraw) {
    const run = async (ok) => {
      if (!ok) return;
      try {
        const r = await api('pack_delete', { id: p.id });
        S.boot.packs = r.packs;
        if (S.target === p.id) { S.target = 0; renderTarget(); }
        hap.ok();
        toast('Pack deleted');
        redraw();
      } catch (e) { toast(e.message, true); }
    };
    const msg = `Delete “${p.title}” from Telegram permanently?`;
    if (ver('6.2')) tg.showConfirm(msg, run); else run(window.confirm(msg));
  }

  // ---------- shop ----------
  function openShop() {
    const list = h('div');
    if (!S.boot.packages.length) list.append(h('div', { class: 'empty-state', text: 'No coin packages are available yet.' }));
    for (const pkg of S.boot.packages) {
      const b = h('button', { class: 'pkg', type: 'button' }, h('span', { class: 'coins-amt', text: `🪙 ${fa(pkg.coins)} coins` }), h('span', { class: 'price', text: `⭐ ${fa(pkg.stars)}` }));
      b.onclick = () => buy(pkg, b);
      list.append(b);
    }
    openSheet(h('div', null,
      h('h2', { text: 'Buy coins' }),
      h('div', { class: 'balance' }, h('b', { text: fa(S.user.coins) }), h('span', { text: 'Current balance (coins)' })),
      S.cfg.price > 0 ? h('p', { text: `${fa(S.cfg.price)} coin(s) per emoji — secure payment with Telegram Stars ⭐` }) : h('p', { text: 'Secure payment with Telegram Stars ⭐' }),
      list));
  }
  async function buy(pkg, btn) {
    if (!ver('6.1')) { toast('Please update Telegram to use payments.', true); return; }
    btn.disabled = true;
    try {
      const r = await api('invoice', { pkg: pkg.id });
      tg.openInvoice(r.link, async (status) => {
        btn.disabled = false;
        if (status === 'paid') {
          hap.ok();
          toast('✅ Payment successful');
          const before = S.user.coins;
          for (let i = 0; i < 8; i++) {
            await sleep(1500);
            try {
              const m = await api('me');
              S.user = m.user;
              updateCoins();
              if (m.user.coins !== before) break;
            } catch { /* */ }
          }
          if (!$('sheet').hidden) openShop();
        } else if (status === 'failed') toast('Payment failed', true);
      });
    } catch (e) {
      btn.disabled = false;
      toast(e.message, true);
    }
  }

  // ---------- gift & referral ----------
  let giftTimer = 0;
  function openGift() {
    const body = h('div');
    const draw = () => {
      clearInterval(giftTimer);
      const parts = [h('h2', { text: 'Gifts & invites' })];
      if (S.cfg.daily_gift > 0) {
        const card = h('div', { class: 'card', style: { textAlign: 'center', marginBottom: '14px' } });
        if (S.user.gift_in === 0) {
          const b = h('button', { class: 'main-btn', type: 'button', text: `🎁 Claim ${fa(S.cfg.daily_gift)} coins` });
          b.onclick = async () => {
            b.disabled = true;
            try {
              const r = await api('gift');
              S.user = r.user;
              updateCoins();
              if (r.granted) { hap.ok(); toast(`🎁 You got ${fa(r.granted)} coins`); }
              draw();
            } catch (e) { b.disabled = false; toast(e.message, true); }
          };
          card.append(h('p', { text: 'Your daily gift is ready!' }), b);
        } else {
          const left = h('b', { style: { fontSize: '24px', direction: 'ltr', display: 'block' } });
          let end = Date.now() + S.user.gift_in * 1000;
          const upd = () => {
            const s = Math.max(0, Math.round((end - Date.now()) / 1000));
            if (s === 0) { S.user.gift_in = 0; updateCoins(); draw(); return; }
            const pad = (x) => String(x).padStart(2, '0');
            left.textContent = `${pad(Math.floor(s / 3600))}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}`;
          };
          upd();
          giftTimer = setInterval(upd, 1000);
          card.append(h('p', { text: 'Next daily gift in:' }), left);
        }
        parts.push(card);
      }
      const link = S.boot.ref_link;
      const copy = h('button', { class: 'main-btn secondary', type: 'button', text: 'Copy link', onclick: async () => { try { await navigator.clipboard.writeText(link); toast('Copied'); } catch { toast(link); } } });
      const share = h('button', { class: 'main-btn', type: 'button', text: 'Share with friends', onclick: () => tg.openTelegramLink('https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent('✨ Create your own custom premium emoji with this bot!')) });
      parts.push(h('div', { class: 'card' },
        h('b', { text: '👥 Invite friends' }),
        h('p', { style: { marginTop: '6px' }, text: S.cfg.referral_bonus > 0 ? `Get ${fa(S.cfg.referral_bonus)} coins for every friend who joins.` : 'Share the bot with your friends.' }),
        h('div', { class: 'input', style: { direction: 'ltr', display: 'flex', alignItems: 'center', overflow: 'hidden', whiteSpace: 'nowrap', fontSize: '13px' }, text: link }),
        h('div', { class: 'row', style: { marginTop: '10px' } }, copy, share)));
      fill(body, ...parts);
    };
    draw();
    openSheet(body, { onClose: () => clearInterval(giftTimer) });
  }

  // ======================================================================
  //  Admin: template manager
  // ======================================================================
  const A = { list: null };

  async function openAdmin() {
    if (!S.user.admin) return;
    $('app').hidden = true;
    $('adminView').hidden = false;
    window.scrollTo(0, 0);
    navPush(closeAdmin);
    await loadAdminList();
  }
  async function closeAdmin() {
    navPop();
    $('adminView').hidden = true;
    $('adminView').replaceChildren();
    $('app').hidden = false;
    try {
      const b = await api('boot');
      S.boot.categories = b.categories;
      renderCats();
      refreshSel();
      onParamsChange();
    } catch { /* */ }
  }
  async function loadAdminList() {
    const v = $('adminView');
    fill(v, h('div', { class: 'empty-state' }, h('div', { class: 'spinner', style: { margin: '30px auto' } })));
    try { A.list = await api('adm_list'); } catch (e) { toast(e.message, true); return; }
    renderAdminList();
  }
  function renderAdminList() {
    const v = $('adminView');
    const L = A.list;
    const upload = h('input', { type: 'file', accept: 'image/png,image/webp,image/jpeg' });
    upload.onchange = async () => {
      const f = upload.files && upload.files[0];
      if (!f) return;
      toast('Uploading…');
      try {
        const r = await apiForm('adm_upload', { image: f, kind: 'base' });
        await loadAdminList();
        openEditor(r.template);
      } catch (e) { toast(e.message, true); }
    };
    const rows = L.custom.map((t) => {
      const thumb = h('div', { class: 'thumb' });
      api('adm_image', { id: t.id }).then((r) => { thumb.style.backgroundImage = `url("${r.src}")`; }).catch(() => {});
      const sw = h('button', { class: 'switch' + (t.enabled ? ' on' : ''), type: 'button', 'aria-label': 'Enabled' });
      sw.onclick = async () => {
        try {
          await api('adm_save', { id: t.id, enabled: !t.enabled, config: t.config });
          t.enabled = !t.enabled;
          sw.classList.toggle('on', t.enabled);
          hap.sel();
        } catch (e) { toast(e.message, true); }
      };
      return h('div', { class: 'tpl-row' }, thumb,
        h('div', { class: 'info' }, h('b', { text: `${t.emoji} ${t.title}` }), h('small', { text: `${t.category || '—'} • ${L.effects[t.config.effect] || ''}` })),
        sw, h('button', { class: 'icon-btn', type: 'button', onclick: () => openEditor(t) }, icon('edit')));
    });
    const builtins = L.builtins.map((b) => {
      const sw = h('button', { class: 'switch' + (b.enabled ? ' on' : ''), type: 'button' });
      sw.onclick = async () => {
        try { await api('adm_builtin', { id: b.id, enabled: !b.enabled }); b.enabled = !b.enabled; sw.classList.toggle('on', b.enabled); hap.sel(); } catch (e) { toast(e.message, true); }
      };
      return h('div', { class: 'tpl-row' }, h('div', { class: 'info' }, h('b', { text: b.title }), h('small', { text: b.category + (b.animated ? ' • animated' : '') })), sw);
    });
    fill(v,
      h('h2', { text: '🧩 Templates' }),
      !L.video ? h('div', { class: 'note warn', text: 'ffmpeg was not found on the server; animated templates are disabled.' }) : null,
      h('div', { class: 'note', text: 'Upload a character or design image (ideally a square PNG with a transparent background), then mark where the text goes. “Two-tone” coloring repaints the template with each user\'s colors.' }),
      h('label', { class: 'main-btn file-btn', style: { display: 'grid', placeItems: 'center', marginBottom: '16px' } }, '➕ Add template', upload),
      h('h3', { text: `Custom templates (${fa(L.custom.length)})` }),
      rows.length ? rows : h('div', { class: 'empty-state', text: 'No custom templates yet.' }),
      h('h3', { text: 'Built-in templates' }),
      builtins);
  }

  function openEditor(t) {
    const v = $('adminView');
    const cfg = JSON.parse(JSON.stringify(t.config));
    const meta = { title: t.title, category: t.category, emoji: t.emoji, sort: t.sort, enabled: t.enabled };
    navPush(() => { navPop(); renderAdminList(); });

    // stage with draggable text box
    const img = h('img', { alt: '' });
    api('adm_image', { id: t.id }).then((r) => { img.src = r.src; }).catch((e) => toast(e.message, true));
    const label = h('span', { text: 'Text' });
    const handle = h('div', { class: 'handle' });
    const box = h('div', { class: 'tbox' }, label, handle);
    const stage = h('div', { class: 'editor-stage' }, img, box);
    const placeBox = () => {
      const [cx, cy, w, hh] = cfg.box;
      Object.assign(box.style, { left: `${(cx - w / 2) * 100}%`, top: `${(cy - hh / 2) * 100}%`, width: `${w * 100}%`, height: `${hh * 100}%`, transform: `rotate(${cfg.angle}deg)` });
    };
    placeBox();
    let drag = null;
    const pt = (e) => { const r = stage.getBoundingClientRect(); return [(e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height]; };
    box.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      box.setPointerCapture(e.pointerId);
      const [x, y] = pt(e);
      drag = { mode: e.target === handle ? 'resize' : 'move', x, y, box: [...cfg.box] };
    });
    box.addEventListener('pointermove', (e) => {
      if (!drag) return;
      const [x, y] = pt(e);
      const clamp = (n, a, b) => Math.min(b, Math.max(a, n));
      if (drag.mode === 'move') {
        cfg.box[0] = clamp(drag.box[0] + x - drag.x, 0, 1);
        cfg.box[1] = clamp(drag.box[1] + y - drag.y, 0, 1);
      } else {
        cfg.box[2] = clamp(Math.abs(x - cfg.box[0]) * 2, 0.05, 1);
        cfg.box[3] = clamp(Math.abs(y - cfg.box[1]) * 2, 0.03, 1);
      }
      placeBox();
    });
    const endDrag = () => { if (drag) { drag = null; refreshPreview(); } };
    box.addEventListener('pointerup', endDrag);
    box.addEventListener('pointercancel', endDrag);

    // live preview
    const preview = h('div', { class: 'preview-box' });
    let pvTimer = 0;
    let pvSeq = 0;
    const refreshPreview = () => {
      clearTimeout(pvTimer);
      pvTimer = setTimeout(async () => {
        const seq = ++pvSeq;
        preview.style.opacity = '.5';
        try {
          const r = await api('adm_preview', { id: t.id, config: cfg, params: currentParams() });
          if (seq !== pvSeq) return;
          preview.style.backgroundImage = `url("${r.src}")`;
          preview.classList.toggle('sprite', r.frames > 1);
        } catch (e) { toast(e.message, true); }
        preview.style.opacity = '1';
      }, 450);
    };

    // fields
    const field = (label, el) => h('label', { class: 'field' }, h('span', { text: label }), el);
    const input = (key, attrs = {}) => {
      const el = h('input', { class: 'input', value: meta[key], ...attrs });
      el.addEventListener('input', () => { meta[key] = attrs.type === 'number' ? parseInt(el.value || '0', 10) : el.value; });
      return el;
    };
    const select = (options, value, onChange) => {
      const el = h('select', { class: 'select' });
      for (const [k, lab] of Object.entries(options)) el.append(h('option', { value: k, text: lab }));
      el.value = value;
      el.addEventListener('change', () => { onChange(el.value); refreshPreview(); });
      return el;
    };
    const color = (key) => {
      const el = h('input', { type: 'color', value: cfg[key], style: { width: '52px', height: '46px', border: '0', background: 'none' } });
      el.addEventListener('input', () => { cfg[key] = el.value; refreshPreview(); });
      return el;
    };
    const range = (key, min, max, step) => {
      const out = h('b', { text: String(cfg[key]) });
      const el = h('input', { type: 'range', min, max, step, value: cfg[key] });
      el.addEventListener('input', () => { cfg[key] = parseFloat(el.value); out.textContent = el.value; if (key === 'angle') placeBox(); });
      el.addEventListener('change', refreshPreview);
      return h('label', { class: 'slider' }, h('span', { class: 'slider-head' }, h('span', { text: key === 'angle' ? 'Text rotation (°)' : 'Outline width' }), out), el);
    };
    const catList = h('datalist', { id: 'catlist' }, (A.list.categories || []).map((c) => h('option', { value: c })));
    const catInput = input('category', { list: 'catlist', maxlength: '64' });
    const shadowSw = h('button', { class: 'switch' + (cfg.shadow ? ' on' : ''), type: 'button', onclick: () => { cfg.shadow = !cfg.shadow; shadowSw.classList.toggle('on', cfg.shadow); refreshPreview(); } });
    const enabledSw = h('button', { class: 'switch' + (meta.enabled ? ' on' : ''), type: 'button', onclick: () => { meta.enabled = !meta.enabled; enabledSw.classList.toggle('on', meta.enabled); } });

    const overlayIn = h('input', { type: 'file', accept: 'image/png,image/webp' });
    overlayIn.onchange = async () => {
      const f = overlayIn.files && overlayIn.files[0];
      if (!f) return;
      try { const r = await apiForm('adm_upload', { image: f, kind: 'overlay', id: t.id }); cfg.overlay = r.template.config.overlay; toast('Overlay added'); refreshPreview(); } catch (e) { toast(e.message, true); }
    };
    const baseIn = h('input', { type: 'file', accept: 'image/png,image/webp,image/jpeg' });
    baseIn.onchange = async () => {
      const f = baseIn.files && baseIn.files[0];
      if (!f) return;
      try {
        const r = await apiForm('adm_upload', { image: f, kind: 'base', id: t.id });
        cfg.file = r.template.config.file;
        const im = await api('adm_image', { id: t.id });
        img.src = im.src;
        refreshPreview();
      } catch (e) { toast(e.message, true); }
    };

    const save = h('button', { class: 'main-btn', type: 'button', text: '💾 Save' });
    save.onclick = async () => {
      save.disabled = true;
      try {
        const r = await api('adm_save', { id: t.id, ...meta, config: cfg });
        Object.assign(t, r.template);
        hap.ok();
        toast('Saved');
        navPop();
        await loadAdminList();
      } catch (e) { toast(e.message, true); }
      save.disabled = false;
    };
    const del = h('button', { class: 'main-btn danger', type: 'button', text: '🗑 Delete template' });
    del.onclick = () => {
      const run = async (ok) => {
        if (!ok) return;
        try { A.list = await api('adm_delete', { id: t.id }); navPop(); renderAdminList(); toast('Deleted'); } catch (e) { toast(e.message, true); }
      };
      if (ver('6.2')) tg.showConfirm('Delete this template?', run); else run(window.confirm('Delete?'));
    };

    fill(v,
      h('h2', { text: '✏️ Edit template' }),
      h('p', { class: 'note', text: 'Drag the blue box to where the text goes; drag the corner dot to resize. The preview uses your current text and colors.' }),
      stage,
      h('h3', { text: 'Preview' }), preview,
      h('div', { class: 'grid2' }, field('Title', input('title', { maxlength: '64' })), field('Emoji', input('emoji', { maxlength: '8' }))),
      h('div', { class: 'grid2' }, field('Category', catInput), field('Order', input('sort', { type: 'number' }))),
      catList,
      field('Image coloring', select(A.list.recolor, cfg.recolor, (x) => { cfg.recolor = x; })),
      h('div', { class: 'grid2' },
        field('Text color', select({ c2: "User's second color", c1: "User's main color", fixed: 'Fixed color' }, cfg.text_color, (x) => { cfg.text_color = x; })),
        field('Fixed text color', color('text_fixed'))),
      h('div', { class: 'grid2' },
        field('Text outline', select({ none: 'None', c1: 'Main color', c2: 'Second color', fixed: 'Fixed color' }, cfg.stroke, (x) => { cfg.stroke = x; })),
        field('Fixed outline color', color('stroke_fixed'))),
      range('stroke_width', 0, 0.3, 0.01),
      range('angle', -45, 45, 1),
      h('div', { class: 'grid2' },
        field('Max lines', select({ 1: '1 line', 2: '2 lines', 3: '3 lines' }, String(cfg.max_lines), (x) => { cfg.max_lines = parseInt(x, 10); })),
        field('Animation', select(A.list.video ? A.list.effects : { none: A.list.effects.none }, cfg.effect, (x) => { cfg.effect = x; }))),
      h('div', { class: 'check-row' }, h('span', { text: 'Text shadow' }), shadowSw),
      h('div', { class: 'check-row' }, h('span', { text: 'Visible to users' }), enabledSw),
      h('div', { class: 'grid2', style: { marginBottom: '12px' } },
        h('label', { class: 'main-btn secondary file-btn', style: { display: 'grid', placeItems: 'center', fontSize: '14px' } }, 'Replace image', baseIn),
        h('label', { class: 'main-btn secondary file-btn', style: { display: 'grid', placeItems: 'center', fontSize: '14px' } }, cfg.overlay ? 'Replace overlay' : 'Add overlay', overlayIn)),
      h('div', { class: 'stack' }, save, del));
    window.scrollTo(0, 0);
    refreshPreview();
  }

  boot();
})();

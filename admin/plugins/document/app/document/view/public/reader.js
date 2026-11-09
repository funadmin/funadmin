(() => {
  'use strict';
  const root = document.documentElement;
  const themeButton = document.querySelector('[data-theme-toggle]');
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
  let savedTheme;
  try { savedTheme = window.localStorage.getItem('FUNADMIN_DOCS_THEME'); } catch {}
  const setTheme = (theme) => {
    root.dataset.theme = theme;
    themeButton?.setAttribute('aria-label', theme === 'dark' ? '切换至浅色模式' : '切换至深色模式');
  };
  setTheme(savedTheme === 'dark' || savedTheme === 'light' ? savedTheme : prefersDark ? 'dark' : 'light');
  themeButton?.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    setTheme(next);
    try { window.localStorage.setItem('FUNADMIN_DOCS_THEME', next); } catch {}
  });

  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const setSidebar = (collapsed) => {
    if (collapsed) root.dataset.sidebar = 'collapsed'; else delete root.dataset.sidebar;
    sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
    sidebarToggle?.setAttribute('aria-label', collapsed ? '展开目录' : '收起目录');
  };
  let savedSidebar;
  try { savedSidebar = window.localStorage.getItem('FUNADMIN_DOCS_SIDEBAR'); } catch {}
  setSidebar(savedSidebar === 'collapsed');
  sidebarToggle?.addEventListener('click', () => {
    const collapsed = root.dataset.sidebar !== 'collapsed';
    setSidebar(collapsed);
    try { window.localStorage.setItem('FUNADMIN_DOCS_SIDEBAR', collapsed ? 'collapsed' : 'open'); } catch {}
  });

  const searches = [...document.querySelectorAll('[data-docs-search], #docs-search')];
  const search = document.querySelector('#docs-search') ?? searches[0];
  const focusSearch = () => {
    if (!searches.length) return;
    const navigation = document.querySelector('[data-navigation]');
    if (navigation) navigation.open = true;
    const target = searches.find((input) => input.getClientRects().length > 0) ?? search;
    target.focus(); target.select();
  };
  document.querySelector('[data-open-search]')?.addEventListener('click', focusSearch);
  document.addEventListener('keydown', (event) => {
    const editing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]');
    if (((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') || (event.key === '/' && !editing && !event.ctrlKey && !event.metaKey && !event.altKey)) {
      if (!search) return;
      event.preventDefault();
      focusSearch();
    }
  });

  const navigation = document.querySelector('[data-navigation]');
  const smallScreen = window.matchMedia?.('(max-width: 820px)');
  if (navigation && smallScreen) {
    const resizeNavigation = () => { navigation.open = !smallScreen.matches; };
    resizeNavigation();
    smallScreen.addEventListener?.('change', resizeNavigation);
  }

  const categoryStateKey = 'FUNADMIN_DOCS_CATEGORIES';
  const categoryGroups = [...document.querySelectorAll('[data-category-id]')];
  let categoryState = {};
  try {
    const stored = window.sessionStorage.getItem(categoryStateKey);
    const parsed = stored ? JSON.parse(stored) : null;
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) categoryState = parsed;
  } catch {}
  const saveCategoryState = () => {
    try {
      const state = Object.fromEntries(categoryGroups.map((group) => [group.dataset.categoryId, group.open]));
      window.sessionStorage.setItem(categoryStateKey, JSON.stringify(state));
    } catch {}
  };
  categoryGroups.forEach((group) => {
    const id = group.dataset.categoryId;
    if (id && Object.prototype.hasOwnProperty.call(categoryState, id)) group.open = categoryState[id] === true;
    group.addEventListener('toggle', saveCategoryState);
  });

  const feedback = document.querySelector('[data-copy-feedback]');
  let feedbackTimer;
  const notify = (message) => {
    if (!feedback) return;
    feedback.textContent = message;
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => { feedback.textContent = ''; }, 2600);
  };
  const copy = async (button, text) => {
    try {
      if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(text);
      button.dataset.copied = 'true';
      const label = button.querySelector('[data-copy-label]');
      const original = label?.textContent;
      if (label) label.textContent = '已复制';
      notify('已复制到剪贴板');
      setTimeout(() => { delete button.dataset.copied; if (label) label.textContent = original; }, 2200);
    } catch { notify('无法自动复制，请手动选择文字或复制地址栏链接。'); }
  };
  document.querySelectorAll('[data-copy-code]').forEach(button => button.addEventListener('click', () => {
    const code = button.closest('[data-code-block]')?.querySelector('pre code');
    if (code) void copy(button, code.textContent ?? '');
  }));
  document.querySelectorAll('[data-copy-page]').forEach(button => button.addEventListener('click', () => void copy(button, window.location.href)));

  // —— 读者反馈：有帮助直接提交；无帮助展开原因与说明。右栏与文末两处组件状态同步。——
  const feedbackWidgets = [...document.querySelectorAll('[data-feedback]')];
  if (feedbackWidgets.length) {
    const slug = feedbackWidgets[0].dataset.slug ?? '';
    const storage = (() => { try { return window.localStorage; } catch { return null; } })();
    const read = (key) => { try { return storage?.getItem(key) ?? null; } catch { return null; } };
    const write = (key, value) => { try { storage?.setItem(key, value); } catch {} };
    const visitor = (() => {
      let id = read('FUNADMIN_DOCS_VISITOR');
      if (!id || !/^[A-Za-z0-9_-]{16,64}$/.test(id)) {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        id = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
        write('FUNADMIN_DOCS_VISITOR', id);
      }
      return id;
    })();
    const votesKey = 'FUNADMIN_DOCS_FEEDBACK';
    const votes = (() => { try { const v = JSON.parse(read(votesKey) ?? '{}'); return v && typeof v === 'object' && !Array.isArray(v) ? v : {}; } catch { return {}; } })();
    const setStatus = (message, tone = '') => feedbackWidgets.forEach((widget) => {
      const status = widget.querySelector('[data-feedback-status]');
      if (!status) return;
      status.textContent = message;
      if (tone) status.dataset.tone = tone; else delete status.dataset.tone;
    });
    const markVote = (value) => feedbackWidgets.forEach((widget) => widget.querySelectorAll('[data-feedback-vote]').forEach((button) => {
      button.setAttribute('aria-pressed', String(value !== null && button.dataset.feedbackVote === value));
    }));
    const setBusy = (busy) => feedbackWidgets.forEach((widget) => widget.querySelectorAll('button').forEach((button) => { button.disabled = busy; }));
    const closeForms = () => feedbackWidgets.forEach((widget) => { const form = widget.querySelector('[data-feedback-form]'); if (form) form.hidden = true; });

    if (votes[slug] === '1' || votes[slug] === '0') {
      markVote(votes[slug]);
      setStatus('你已反馈过，可以随时修改选择。');
    }

    const send = async (payload) => {
      setBusy(true);
      setStatus('正在提交…');
      try {
        const response = await fetch(`/docs/${encodeURIComponent(slug)}/feedback`, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ visitor, ...payload }),
        });
        const result = await response.json().catch(() => null);
        if (!response.ok) throw new Error(result?.msg || '反馈暂时无法提交，请稍后重试');
        const value = payload.helpful ? '1' : '0';
        votes[slug] = value;
        write(votesKey, JSON.stringify(votes));
        markVote(value);
        closeForms();
        setStatus(payload.helpful ? '感谢你的反馈！' : '感谢反馈，我们会尽快完善这篇文档。', 'success');
        return true;
      } catch (error) {
        setStatus(error instanceof Error && error.message !== 'Failed to fetch' ? error.message : '网络异常，反馈未提交，请稍后重试', 'error');
        return false;
      } finally {
        setBusy(false);
      }
    };

    feedbackWidgets.forEach((widget) => {
      const form = widget.querySelector('[data-feedback-form]');
      widget.querySelectorAll('[data-feedback-vote]').forEach((button) => button.addEventListener('click', () => {
        if (button.dataset.feedbackVote === '1') { void send({ helpful: true }); return; }
        if (!form) { void send({ helpful: false, reasons: [], comment: '' }); return; }
        closeForms();
        form.hidden = false;
        setStatus('');
        form.querySelector('input,textarea')?.focus();
      }));
      widget.querySelector('[data-feedback-cancel]')?.addEventListener('click', () => { if (form) form.hidden = true; });
      form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const reasons = [...form.querySelectorAll('input[name="reasons"]:checked')].map((input) => input.value);
        const comment = form.querySelector('textarea[name="comment"]')?.value.trim() ?? '';
        void send({ helpful: false, reasons, comment }).then((ok) => { if (ok) form.reset(); });
      });
    });
  }

  const headings = document.querySelectorAll('[data-reader-content] :is(h1,h2,h3,h4,h5,h6)[id]');
  const tocLinks = [...document.querySelectorAll('[data-toc-link]')];
  if (headings.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      const active = entries.find(entry => entry.isIntersecting);
      if (!active) return;
      tocLinks.forEach(link => {
        if (link.hash === `#${active.target.id}`) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      });
    }, { rootMargin: '-120px 0px -60% 0px' });
    headings.forEach(heading => observer.observe(heading));
  }

  const progress = document.querySelector('[data-reading-progress]');
  if (progress) {
    let queued = false;
    const update = () => {
      const total = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.transform = `scaleX(${total > 0 ? Math.min(1, Math.max(0, window.scrollY / total)) : 0})`;
      queued = false;
    };
    const schedule = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    update();
  }
})();

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

  const search = document.querySelector('#docs-search');
  const focusSearch = () => {
    if (!search) return;
    const navigation = document.querySelector('[data-navigation]');
    if (navigation) navigation.open = true;
    search.focus(); search.select();
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

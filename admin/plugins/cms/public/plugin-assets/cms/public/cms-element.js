/* Mount the literal <el-*> declarations in cms/view. No template compiler or page markup here. */
(() => {
  'use strict';
  const root = document.querySelector('[data-cms-ui]');
  const V = window.Vue, E = window.ElementPlus;
  if (!root || root.dataset.cmsUiReady || !V?.createApp || !E?.ElContainer) return;
  const originals = Array.from(root.childNodes, node => node.cloneNode(true));
  const components = new Map(Object.entries(E).filter(([name]) => /^El[A-Z]/.test(name))
    .map(([name, component]) => [name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase(), component]));
  const state = new Map();
  const camel = name => name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
  function model(name, initial = false) {
    if (!state.has(name)) state.set(name, V.ref(initial));
    return state.get(name);
  }
  function attributes(node, component) {
    const result = {};
    for (const { name, value } of node.attributes) {
      // Values are literal data: never compile Vue directives, events or CMS expressions.
      if (/^on|^v-|^[:@#]|^(?:innerhtml|textcontent)$/i.test(name)) continue;
      const key = component && !/^(data-|aria-)/.test(name) ? camel(name) : name;
      const rule = component?.props?.[key];
      const types = [].concat(rule?.type || rule || []);
      if (types.includes(Boolean) && ['', 'true', 'false'].includes(value)) result[key] = value !== 'false';
      else if (types.includes(Number) && !types.includes(String) && /^-?\d+(?:\.\d+)?$/.test(value)) result[key] = Number(value);
      else result[key] = value;
    }
    return result;
  }
  function activateLink(event) {
    if (!['Enter', ' '].includes(event.key)) return;
    event.stopImmediatePropagation();
    if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
    const target = event.currentTarget;
    const anchor = target?.matches?.('a') ? target : target?.querySelector?.('a');
    if (anchor) { event.preventDefault(); anchor.click(); }
  }
  function paginationUrl(node, page) {
    const base = node.dataset.basePath;
    if (!Number.isSafeInteger(page) || page < 1 || !base?.startsWith('/') || base.startsWith('//')) return null;
    const path = base.split('/').map(part => {
      try { return encodeURIComponent(decodeURIComponent(part)); }
      catch { return encodeURIComponent(part); }
    }).join('/');
    if (['true', 'search'].includes(node.dataset.search)) return path + '?q=' + encodeURIComponent(node.dataset.query || '') + '&page=' + page;
    return page === 1 ? path : path.replace(/\/$/, '') + '/page/' + page + '/';
  }
  function children(node, active) {
    return Array.from(node.childNodes, child => renderNode(child, active));
  }
  function renderNode(node, active = true) {
    if (node.nodeType === 3) return node.nodeValue;
    if (node.nodeType === 8) return V.createCommentVNode(node.nodeValue);
    if (node.nodeType !== 1) return null;
    const tag = node.tagName.toLowerCase();
    if (tag === 'script') return V.createCommentVNode('Scripts stay outside the CMS mount');
    // Rich content remains ordinary HTML, even when it contains component-like tags or markers.
    active = active && !node.hasAttribute('data-cms-static');
    if (active && node.hasAttribute('data-cms-fallback')) return V.createCommentVNode('Native fallback');
    const component = active ? components.get(tag) : null;
    const props = attributes(node, component);
    if (active) {
      const open = node.dataset.cmsOpen, close = node.dataset.cmsClose, binding = node.dataset.cmsModel;
      if (open) props.onClick = () => { model(open).value = true; };
      if (close) props.onClick = event => { if (event.target?.closest?.('a[href]')) model(close).value = false; };
      if (binding) {
        const value = model(binding, props.modelValue ?? false);
        props.modelValue = value.value;
        props['onUpdate:modelValue'] = next => { value.value = next; };
      }
      if (tag === 'el-menu-item' || tag === 'el-dropdown-item') props.onKeydown = activateLink;
      if (node.dataset.cmsChange === 'pagination') props.onCurrentChange = page => {
        const url = paginationUrl(node, page);
        if (url) window.location.assign(url);
      };
    }
    if (!component) return V.h(tag, props, children(node, active));
    // Named slot wrappers live in the view as visible HTML, so the initial page stays readable.
    const groups = { default: [] };
    for (const child of node.childNodes) {
      if (child.nodeType === 3 && !child.nodeValue.trim()) continue;
      const slot = child.nodeType === 1 ? child.getAttribute('data-cms-slot') : null;
      if (slot && /^[a-z][a-z0-9-]*$/.test(slot)) (groups[slot] ||= []).push(...Array.from(child.childNodes));
      else groups.default.push(child);
    }
    return V.h(component, props, Object.fromEntries(Object.entries(groups).map(([name, nodes]) => [name,
      () => nodes.map(child => renderNode(child, active))
    ])));
  }
  let app, mounted = false, failed = false, restoring = false;
  function restore(error) {
    if (restoring) return;
    restoring = true;
    try { if (mounted) app?.unmount(); }
    finally {
      root.replaceChildren(...originals.map(node => node.cloneNode(true)));
      delete root.dataset.cmsUiReady;
    }
    console.error('CMS components failed; the server page was restored.', error);
  }
  try {
    app = V.createApp({ render: () => V.h(V.Fragment, null, originals.map(node => renderNode(node))) });
    app.config.errorHandler = error => {
      failed = true;
      if (mounted) Promise.resolve().then(() => restore(error));
    };
    const locale = (document.documentElement.lang || 'zh-cn').startsWith('zh') ? window.ElementPlusLocaleZhCn : undefined;
    app.use(E, { locale });
    app.mount(root);
    mounted = true;
    if (failed) restore(new Error('CMS component initialization failed'));
    else root.dataset.cmsUiReady = 'true';
  } catch (error) { restore(error); }
})();

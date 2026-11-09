/* Native fallback is kept for pages where the Element runtime is unavailable. */
(() => {
if (document.querySelector('[data-cms-ui-ready="true"]')) return;
/* Mouse hover enhances the native details toggle; touch and keyboard retain native clicks. */
(() => {
  const hover = window.matchMedia?.('(hover: hover) and (pointer: fine)');
  document.querySelectorAll('.cms-menu-dropdown').forEach(dropdown => {
    const summary = dropdown.querySelector(':scope > summary');
    if (!summary || dropdown.dataset.navigationReady) return;
    dropdown.dataset.navigationReady = 'true';
    dropdown.addEventListener('pointerenter', event => {
      if (event.pointerType === 'mouse' && hover?.matches) dropdown.open = true;
    });
    dropdown.addEventListener('pointerleave', event => {
      if (event.pointerType === 'mouse' && hover?.matches) dropdown.open = false;
    });
    summary.addEventListener('click', event => {
      // Hover may already have opened the menu before the mouse click arrives.
      if (event.pointerType === 'mouse' && hover?.matches) {
        event.preventDefault();
        dropdown.open = true;
      }
    });
    dropdown.addEventListener('keydown', event => {
      if (event.key !== 'Escape' || !dropdown.open) return;
      dropdown.open = false;
      summary.focus();
      event.preventDefault();
      event.stopPropagation();
    });
  });
})();

/* Native mobile and language disclosures close with Escape and return focus to their toggle. */
(() => {
  document.querySelectorAll('details.mobile-navigation, details.language-switch').forEach(details => {
    const summary = details.querySelector(':scope > summary');
    if (!summary || details.dataset.disclosureReady) return;
    details.dataset.disclosureReady = 'true';
    details.addEventListener('keydown', event => {
      if (event.key !== 'Escape' || !details.open) return;
      details.open = false;
      summary.focus();
      event.preventDefault();
      event.stopPropagation();
    });
  });
})();

/* Slides remain a native horizontal scroll region without JavaScript. */
(() => {
  document.querySelectorAll('[data-cms-slider]').forEach(root => {
    const track = root.querySelector('[data-slides-track]');
    const controls = root.querySelector('[data-slider-controls]');
    if (!track || !controls || track.children.length < 2 || root.dataset.sliderReady) return;
    root.dataset.sliderReady = 'true';
    const status = root.querySelector('[data-slider-status]');
    const buttons = Array.from(root.querySelectorAll('[data-slide-direction]'));
    const position = () => Math.min(track.children.length - 1, Math.max(0, Math.round(track.scrollLeft / (track.clientWidth || 1))));
    const refresh = () => {
      const index = position();
      if (status) status.textContent = `${index + 1} / ${track.children.length}`;
      buttons.forEach(button => { button.disabled = Number(button.dataset.slideDirection) < 0 ? index === 0 : index === track.children.length - 1; });
    };
    buttons.forEach(button => button.addEventListener('click', () => {
      if (button.disabled) return;
      const index = Math.min(track.children.length - 1, Math.max(0, position() + Number(button.dataset.slideDirection)));
      const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
      track.scrollTo({ left: index * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
    }));
    track.addEventListener('scroll', refresh, { passive: true });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(refresh).observe(track);
    controls.hidden = false;
    refresh();
  });
})();

})();

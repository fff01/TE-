(() => {
  const header = document.getElementById('protoHeader');
  const triggers = Array.from(document.querySelectorAll('[data-status-trigger]'));
  const items = Array.from(document.querySelectorAll('[data-status-item]'));

  function syncHeader() {
    if (window.scrollY > 12) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  }

  function toggleItem(name) {
    items.forEach((item) => {
      const isTarget = item.dataset.statusItem === name;
      const shouldOpen = isTarget && !item.classList.contains('is-open');
      item.classList.toggle('is-open', shouldOpen);
    });
    triggers.forEach((trigger) => {
      const expanded = trigger.dataset.statusTrigger === name && !trigger.closest('[data-status-item]').classList.contains('is-open') ? 'true' : 'false';
      trigger.setAttribute('aria-expanded', expanded);
    });
  }

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const item = trigger.closest('[data-status-item]');
      const wasOpen = item.classList.contains('is-open');
      items.forEach((entry) => entry.classList.remove('is-open'));
      triggers.forEach((entry) => entry.setAttribute('aria-expanded', 'false'));
      if (!wasOpen) {
        item.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });
  });

  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();
})();

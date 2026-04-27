(() => {
  const header = document.getElementById('protoHeader');
  const triggers = Array.from(document.querySelectorAll('[data-status-trigger]'));
  const items = Array.from(document.querySelectorAll('[data-status-item]'));
  const tooltip = document.getElementById('statusTooltip');
  const tooltipTitle = tooltip ? tooltip.querySelector('.status-tooltip-title') : null;
  const tooltipMeta = tooltip ? tooltip.querySelector('.status-tooltip-meta') : null;
  const ringChart = document.querySelector('.status-ring-chart');

  function syncHeader() {
    if (!header) {
      return;
    }
    if (window.scrollY > 12) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  }

  function hideTooltip() {
    if (!tooltip) {
      return;
    }
    tooltip.hidden = true;
    tooltip.classList.remove('is-visible');
  }

  function showTooltip(segment, event) {
    if (!tooltip || !tooltipTitle || !tooltipMeta) {
      return;
    }

    tooltipTitle.textContent = segment.dataset.label || '';
    tooltipMeta.textContent = `${segment.dataset.count || ''} items · ${segment.dataset.percentage || ''}%`;
    tooltip.hidden = false;
    tooltip.classList.add('is-visible');

    const orbit = tooltip.closest('.status-orbit');
    if (!orbit) {
      return;
    }
    const orbitRect = orbit.getBoundingClientRect();
    tooltip.style.left = `${event.clientX - orbitRect.left}px`;
    tooltip.style.top = `${event.clientY - orbitRect.top - 10}px`;
  }

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const item = trigger.closest('[data-status-item]');
      if (!item) {
        return;
      }
      const wasOpen = item.classList.contains('is-open');
      items.forEach((entry) => entry.classList.remove('is-open'));
      triggers.forEach((entry) => entry.setAttribute('aria-expanded', 'false'));
      if (!wasOpen) {
        item.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });
  });

  if (ringChart) {
    ringChart.addEventListener('mousemove', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        hideTooltip();
        return;
      }
      const segment = target.closest('.status-ring-segment');
      if (!segment) {
        hideTooltip();
        return;
      }
      showTooltip(segment, event);
    });

    ringChart.addEventListener('mouseleave', () => {
      hideTooltip();
    });
  }

  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();
})();

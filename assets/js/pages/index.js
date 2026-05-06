(() => {
  const header = document.getElementById('protoHeader');
  const triggers = Array.from(document.querySelectorAll('[data-status-trigger]'));
  const items = Array.from(document.querySelectorAll('[data-status-item]'));
  const tooltip = document.getElementById('statusTooltip');
  const tooltipTitle = tooltip ? tooltip.querySelector('.status-tooltip-title') : null;
  const tooltipMeta = tooltip ? tooltip.querySelector('.status-tooltip-meta') : null;
  const ringChart = document.querySelector('.status-ring-chart');
  const ringCount = document.querySelector('[data-ring-count]');
  const ringLabel = document.querySelector('[data-ring-label]');

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

  function buildSegmentPath(cx, cy, outerR, innerR, startAngle, endAngle) {
    const largeArc = endAngle - startAngle > 180 ? 1 : 0;
    const toPoint = (radius, angle) => {
      const rad = (angle * Math.PI) / 180;
      return {
        x: cx + radius * Math.cos(rad),
        y: cy + radius * Math.sin(rad),
      };
    };

    const p1 = toPoint(outerR, startAngle);
    const p2 = toPoint(outerR, endAngle);
    const p3 = toPoint(innerR, endAngle);
    const p4 = toPoint(innerR, startAngle);

    return [
      `M ${p1.x.toFixed(4)} ${p1.y.toFixed(4)}`,
      `A ${outerR.toFixed(4)} ${outerR.toFixed(4)} 0 ${largeArc} 1 ${p2.x.toFixed(4)} ${p2.y.toFixed(4)}`,
      `L ${p3.x.toFixed(4)} ${p3.y.toFixed(4)}`,
      `A ${innerR.toFixed(4)} ${innerR.toFixed(4)} 0 ${largeArc} 0 ${p4.x.toFixed(4)} ${p4.y.toFixed(4)}`,
      'Z',
    ].join(' ');
  }

  function renderChart() {
    if (!ringChart) {
      return;
    }

    let chartViews = {};
    try {
      chartViews = JSON.parse(ringChart.dataset.chart || '{}');
    } catch (error) {
      chartViews = {};
    }

    const view = chartViews.root;
    if (!view) {
      return;
    }

    ringChart.replaceChildren();
    let startAngle = -90;
    view.segments.forEach((segment) => {
      const sweep = 360 * ((Number(segment.percentage) || 0) / 100);
      const endAngle = startAngle + sweep;
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('class', 'status-ring-segment');
      path.setAttribute('d', buildSegmentPath(180, 180, 168, 102, startAngle, endAngle));
      path.setAttribute('fill', segment.color || '#4f86df');
      path.dataset.label = segment.label || '';
      path.dataset.count = String(segment.count || '');
      path.dataset.percentage = Number(segment.percentage || 0).toFixed(1);
      ringChart.appendChild(path);
      startAngle = endAngle;
    });

    if (ringCount) {
      ringCount.textContent = new Intl.NumberFormat().format(Number(view.count || 0));
    }
    if (ringLabel) {
      ringLabel.textContent = view.label || '';
    }
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

  renderChart();

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

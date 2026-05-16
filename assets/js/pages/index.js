(() => {
  const header = document.getElementById('protoHeader');
  const tooltip = document.getElementById('statusTooltip');
  const tooltipTitle = tooltip ? tooltip.querySelector('.status-tooltip-title') : null;
  const tooltipMeta = tooltip ? tooltip.querySelector('.status-tooltip-meta') : null;
  const ringChart = document.querySelector('.status-ring-chart');
  const ringCount = document.querySelector('[data-ring-count]');
  const ringLabel = document.querySelector('[data-ring-label]');
  const ringCenter = document.querySelector('[data-ring-center]');
  let chartViews = {};
  let activeChartView = 'root';
  let activeSegments = [];
  const ringOuterRadius = 168;
  const ringInnerRadius = 102;

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

  function normalizeAngle(angle) {
    let value = angle;
    while (value < -90) {
      value += 360;
    }
    while (value >= 270) {
      value -= 360;
    }
    return value;
  }

  function resolveHoveredSegment(event) {
    if (!ringChart || !activeSegments.length) {
      return null;
    }
    const rect = ringChart.getBoundingClientRect();
    const scaleX = rect.width / 360;
    const scaleY = rect.height / 360;
    const x = (event.clientX - rect.left) / scaleX;
    const y = (event.clientY - rect.top) / scaleY;
    const dx = x - 180;
    const dy = y - 180;
    const radius = Math.sqrt(dx * dx + dy * dy);
    if (radius < ringInnerRadius || radius > ringOuterRadius) {
      return null;
    }

    let angle = (Math.atan2(dy, dx) * 180) / Math.PI;
    angle = normalizeAngle(angle);

    return activeSegments.find((segment) => angle >= segment.startAngle && angle < segment.endAngle) || null;
  }

  function renderChart(viewKey = 'root') {
    if (!ringChart) {
      return;
    }

    const view = chartViews[viewKey] || chartViews.root;
    if (!view) {
      return;
    }
    activeChartView = chartViews[viewKey] ? viewKey : 'root';

    ringChart.replaceChildren();
    activeSegments = [];
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
      path.dataset.nextView = segment.nextView || '';
      ringChart.appendChild(path);
      activeSegments.push({
        startAngle,
        endAngle,
        label: path.dataset.label,
        count: path.dataset.count,
        percentage: path.dataset.percentage,
        nextView: path.dataset.nextView,
      });
      startAngle = endAngle;
    });

    if (ringCount) {
      ringCount.textContent = new Intl.NumberFormat().format(Number(view.count || 0));
    }
    if (ringLabel) {
      ringLabel.textContent = view.label || '';
    }
  }

  if (ringChart) {
    try {
      chartViews = JSON.parse(ringChart.dataset.chart || '{}');
    } catch (error) {
      chartViews = {};
    }
  }

  renderChart();

  if (ringChart) {
    ringChart.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      const segment = target.closest('.status-ring-segment');
      if (!segment) {
        return;
      }
      const nextView = segment.dataset.nextView || '';
      if (nextView && chartViews[nextView]) {
        renderChart(nextView);
      }
    });

    ringChart.addEventListener('mousemove', (event) => {
      const segment = resolveHoveredSegment(event);
      if (!segment) {
        hideTooltip();
        return;
      }
      showTooltip({ dataset: segment }, event);
    });

    ringChart.addEventListener('mouseleave', () => {
      hideTooltip();
    });
  }

  if (ringCenter) {
    ringCenter.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (activeChartView !== 'root') {
        renderChart('root');
      }
    });
  }

  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();
})();

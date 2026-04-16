(() => {
  const header = document.getElementById('protoHeader');
  function syncHeader() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 12);
  }
  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();

  const dataNode = document.getElementById('download-page-data');
  const rows = dataNode ? JSON.parse(dataNode.textContent || '[]') : [];
  const body = document.getElementById('download-table-body');
  const summary = document.getElementById('download-summary');
  const pagination = document.getElementById('download-pagination');
  const searchInput = document.getElementById('download-search');
  const sizeSelect = document.getElementById('download-page-size');
  const emptyState = document.getElementById('download-empty');
  if (!body || !summary || !pagination || !searchInput || !sizeSelect || !emptyState) return;

  let currentPage = 1;

  function filteredRows() {
    const query = (searchInput.value || '').trim().toLowerCase();
    if (!query) return rows;
    return rows.filter((row) => [row.dataset, row.filename, row.used_in, row.format, row.description].some((value) => String(value || '').toLowerCase().includes(query)));
  }

  function renderPagination(totalPages) {
    pagination.innerHTML = '';
    if (totalPages <= 1) return;
    const makeButton = (label, page, active = false) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'download-page-btn' + (active ? ' is-active' : '');
      button.textContent = label;
      button.addEventListener('click', () => {
        currentPage = page;
        render();
      });
      return button;
    };
    pagination.appendChild(makeButton('‹', Math.max(1, currentPage - 1), false));
    for (let page = 1; page <= totalPages; page += 1) {
      pagination.appendChild(makeButton(String(page), page, page === currentPage));
    }
    pagination.appendChild(makeButton('›', Math.min(totalPages, currentPage + 1), false));
  }

  function render() {
    const pageSize = Number(sizeSelect.value || 10);
    const items = filteredRows();
    const total = items.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
    const pageItems = items.slice(start, start + pageSize);

    body.innerHTML = '';
    emptyState.hidden = pageItems.length !== 0;

    pageItems.forEach((row) => {
      const tr = document.createElement('tr');
      tr.className = 'dataset-row';
      tr.innerHTML = `
        <td class="dataset-cell">
          <button class="dataset-toggle" type="button" aria-expanded="false">
            <span class="dataset-title-line">
              <svg class="dataset-caret" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.2 2.8 10.4 8l-5.2 5.2-.9-.9L8.6 8 4.3 3.7z"/></svg>
              <em>${row.dataset}</em>
            </span>
          </button>
          <div class="dataset-description">
            <div class="dataset-description-inner">${row.description}</div>
          </div>
        </td>
        <td><a class="file-link" href="${row.path}" download>${row.filename}</a></td>
        <td>${row.used_in}</td>
        <td>${row.format}</td>
      `;
      const toggle = tr.querySelector('.dataset-toggle');
      toggle.addEventListener('click', () => {
        const isOpen = tr.classList.contains('is-open');
        body.querySelectorAll('.dataset-row').forEach((rowEl) => {
          rowEl.classList.remove('is-open');
          const btn = rowEl.querySelector('.dataset-toggle');
          if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
          tr.classList.add('is-open');
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
      body.appendChild(tr);
    });

    const shownFrom = total === 0 ? 0 : start + 1;
    const shownTo = total === 0 ? 0 : start + pageItems.length;
    summary.textContent = `Showing ${shownFrom} to ${shownTo} of ${total} entries`;
    renderPagination(totalPages);
  }

  searchInput.addEventListener('input', () => { currentPage = 1; render(); });
  sizeSelect.addEventListener('change', () => { currentPage = 1; render(); });
  render();
})();

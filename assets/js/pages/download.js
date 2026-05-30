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
  const cardList = document.getElementById('download-card-list');
  const summary = document.getElementById('download-summary');
  const pagination = document.getElementById('download-pagination');
  const searchInput = document.getElementById('download-search');
  const sizeSelect = document.getElementById('download-page-size');
  const emptyState = document.getElementById('download-empty');
  const categoryButtons = Array.from(document.querySelectorAll('[data-download-category]'));
  if (!cardList || !summary || !pagination || !searchInput || !sizeSelect || !emptyState) return;

  let currentPage = 1;
  let currentCategory = 'All';

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function filteredRows() {
    const query = (searchInput.value || '').trim().toLowerCase();
    return rows.filter((row) => {
      const categoryMatch = currentCategory === 'All' || row.category === currentCategory;
      if (!categoryMatch) return false;
      if (!query) return true;
      return [
        row.category,
        row.dataset,
        row.filename,
        row.used_in,
        row.format,
        row.description,
        row.size_label,
      ].some((value) => String(value || '').toLowerCase().includes(query));
    });
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
    pagination.appendChild(makeButton('<', Math.max(1, currentPage - 1), false));
    for (let page = 1; page <= totalPages; page += 1) {
      pagination.appendChild(makeButton(String(page), page, page === currentPage));
    }
    pagination.appendChild(makeButton('>', Math.min(totalPages, currentPage + 1), false));
  }

  function cardHtml(row) {
    const available = row.available === true;
    const action = available
      ? `<a class="download-card-action" href="${escapeHtml(row.href)}" download>Download</a>`
      : '<span class="download-card-action is-disabled" aria-disabled="true">Unavailable</span>';
    const status = available ? 'Available' : 'Unavailable';
    const statusClass = available ? ' is-available' : '';

    return `
      <article class="download-card">
        <div>
          <div class="download-card-top">
            <div>
              <span class="download-card-category">${escapeHtml(row.category)}</span>
              <h3>${escapeHtml(row.dataset)}</h3>
            </div>
            <span class="download-format">${escapeHtml(row.format)}</span>
          </div>
          <p class="download-card-description">${escapeHtml(row.description)}</p>
        </div>
        <div class="download-card-meta">
          <div class="download-meta-item">
            <span>File</span>
            <strong title="${escapeHtml(row.filename)}">${escapeHtml(row.filename)}</strong>
          </div>
          <div class="download-meta-item">
            <span>Size</span>
            <strong class="download-file-size">${escapeHtml(row.size_label)}</strong>
          </div>
          <div class="download-meta-item">
            <span>Used In</span>
            <strong title="${escapeHtml(row.used_in)}">${escapeHtml(row.used_in)}</strong>
          </div>
          <div class="download-meta-item">
            <span>Status</span>
            <strong>${escapeHtml(status)}</strong>
          </div>
        </div>
        <div class="download-card-footer">
          <span class="download-status${statusClass}">${escapeHtml(status)}</span>
          ${action}
        </div>
      </article>
    `;
  }

  function render() {
    const pageSize = Number(sizeSelect.value || 6);
    const items = filteredRows();
    const total = items.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
    const pageItems = items.slice(start, start + pageSize);

    emptyState.hidden = pageItems.length !== 0;
    cardList.innerHTML = pageItems.map(cardHtml).join('');

    const shownFrom = total === 0 ? 0 : start + 1;
    const shownTo = total === 0 ? 0 : start + pageItems.length;
    summary.textContent = `Showing ${shownFrom} to ${shownTo} of ${total} datasets`;
    renderPagination(totalPages);
  }

  categoryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      currentCategory = button.dataset.downloadCategory || 'All';
      categoryButtons.forEach((item) => item.classList.toggle('is-active', item === button));
      currentPage = 1;
      render();
    });
  });

  searchInput.addEventListener('input', () => {
    currentPage = 1;
    render();
  });
  sizeSelect.addEventListener('change', () => {
    currentPage = 1;
    render();
  });
  render();
})();

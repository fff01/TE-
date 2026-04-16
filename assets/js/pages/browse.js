(() => {
  const dataNode = document.getElementById('browse-page-data');
  const config = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
  const browseSearchBase = String(config.browseSearchBase || '');
  const browseRows = Array.isArray(config.browseRows) ? config.browseRows : [];
  let pageSize = 10;
  let currentPage = 1;
  let filteredRows = browseRows.slice();

  const keywordInput = document.getElementById('browseKeyword');
  const classSelect = document.getElementById('browseClass');
  const familySelect = document.getElementById('browseFamily');
  const subtypeSelect = document.getElementById('browseSubtype');
  const applyBtn = document.getElementById('browseApplyBtn');
  const resetBtn = document.getElementById('browseResetBtn');
  const prevBtn = document.getElementById('browsePrevBtn');
  const nextBtn = document.getElementById('browseNextBtn');
  const pageSizeSelect = document.getElementById('browsePageSize');
  const pageJumpInput = document.getElementById('browsePageJump');
  const pageStatus = document.getElementById('browsePageStatus');
  const tableBody = document.getElementById('browseTableBody');
  const emptyState = document.getElementById('browseEmpty');
  if (!keywordInput || !classSelect || !familySelect || !subtypeSelect || !applyBtn || !resetBtn || !prevBtn || !nextBtn || !pageSizeSelect || !pageJumpInput || !pageStatus || !tableBody || !emptyState) return;

  function fillSelect(select, values) {
    values.forEach((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
  }

  function uniqueValues(key) {
    return [...new Set(browseRows.map((row) => row[key]).filter(Boolean))].sort((a, b) => a.localeCompare(b));
  }

  function createCell(text, className = '') {
    const td = document.createElement('td');
    td.textContent = text;
    if (className) td.className = className;
    td.title = text || '';
    return td;
  }

  function createDescriptionCell(text) {
    const td = document.createElement('td');
    td.className = 'browse-description-cell';
    td.textContent = text || '-';
    td.title = text || '';
    return td;
  }

  function renderRows() {
    tableBody.innerHTML = '';
    const total = filteredRows.length;
    const totalPages = total === 0 ? 1 : Math.ceil(total / pageSize);
    currentPage = Math.max(1, Math.min(currentPage, totalPages));
    const startIndex = (currentPage - 1) * pageSize;
    const pageRows = filteredRows.slice(startIndex, startIndex + pageSize);

    pageRows.forEach((row) => {
      const tr = document.createElement('tr');
      const targetUrl = new URL(browseSearchBase, window.location.origin);
      targetUrl.searchParams.set('q', row.name);
      targetUrl.searchParams.set('type', 'TE');

      const nameTd = document.createElement('td');
      const link = document.createElement('a');
      link.className = 'browse-row-link';
      link.href = targetUrl.toString();
      link.textContent = row.name;
      nameTd.appendChild(link);
      nameTd.className = 'browse-name-cell';
      nameTd.title = row.name || '';
      tr.appendChild(nameTd);
      tr.appendChild(createCell(row.className || '-', 'browse-meta-cell'));
      tr.appendChild(createCell(row.family || '-', 'browse-meta-cell'));
      tr.appendChild(createCell(row.subtype || '-', 'browse-meta-cell'));
      tr.appendChild(createDescriptionCell(row.description || '-'));
      tr.appendChild(createCell(row.lengthBp ? `${row.lengthBp} bp` : '-', 'browse-meta-cell'));
      tableBody.appendChild(tr);
    });

    emptyState.classList.toggle('is-visible', total === 0);
    if (total === 0) {
      pageStatus.textContent = '0 - 0 of 0';
      pageJumpInput.value = '1';
    } else {
      const from = startIndex + 1;
      const to = Math.min(startIndex + pageSize, total);
      pageStatus.textContent = `${from} - ${to} of ${total}`;
      pageJumpInput.value = String(currentPage);
    }
    prevBtn.disabled = currentPage <= 1 || total === 0;
    nextBtn.disabled = currentPage >= totalPages || total === 0;
    pageJumpInput.disabled = total === 0;
  }

  function applyFilters() {
    const keyword = (keywordInput.value || '').trim().toLowerCase();
    const classValue = classSelect.value;
    const familyValue = familySelect.value;
    const subtypeValue = subtypeSelect.value;

    filteredRows = browseRows.filter((row) => {
      const haystack = [row.name, row.className, row.family, row.subtype, row.description, ...(row.keywords || [])].join(' ').toLowerCase();
      if (keyword && !haystack.includes(keyword)) return false;
      if (classValue && row.className !== classValue) return false;
      if (familyValue && row.family !== familyValue) return false;
      if (subtypeValue && row.subtype !== subtypeValue) return false;
      return true;
    });

    currentPage = 1;
    renderRows();
  }

  function resetFilters() {
    keywordInput.value = '';
    classSelect.value = '';
    familySelect.value = '';
    subtypeSelect.value = '';
    filteredRows = browseRows.slice();
    currentPage = 1;
    renderRows();
  }

  function jumpToPage() {
    const totalPages = filteredRows.length === 0 ? 1 : Math.ceil(filteredRows.length / pageSize);
    const requestedPage = Number.parseInt(pageJumpInput.value || '1', 10);
    if (Number.isNaN(requestedPage)) {
      pageJumpInput.value = String(currentPage);
      return;
    }
    currentPage = Math.max(1, Math.min(requestedPage, totalPages));
    renderRows();
  }

  fillSelect(classSelect, uniqueValues('className'));
  fillSelect(familySelect, uniqueValues('family'));
  fillSelect(subtypeSelect, uniqueValues('subtype'));
  renderRows();

  applyBtn.addEventListener('click', applyFilters);
  resetBtn.addEventListener('click', resetFilters);
  prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; renderRows(); } });
  nextBtn.addEventListener('click', () => {
    const totalPages = filteredRows.length === 0 ? 1 : Math.ceil(filteredRows.length / pageSize);
    if (currentPage < totalPages) { currentPage += 1; renderRows(); }
  });
  pageSizeSelect.addEventListener('change', () => {
    const nextSize = Number.parseInt(pageSizeSelect.value || '10', 10);
    pageSize = Number.isNaN(nextSize) ? 10 : nextSize;
    currentPage = 1;
    renderRows();
  });
  pageJumpInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') jumpToPage(); });
  keywordInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') applyFilters(); });
  [classSelect, familySelect, subtypeSelect].forEach((select) => {
    select.addEventListener('change', applyFilters);
  });
})();

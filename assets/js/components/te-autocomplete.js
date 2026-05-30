(() => {
  const cache = new Map();
  const maxVisible = 180;

  function apiUrl(path) {
    const paths = window.__TEKG_PATHS;
    return paths && typeof paths.apiUrl === 'function'
      ? paths.apiUrl(path)
      : `/TE-/api/${path}`;
  }

  function taxonomyUrl() {
    return apiUrl('taxonomy.php?view=items');
  }

  function typeControl(root) {
    const selector = String(root.dataset.teAutocompleteTypeSource || '').trim();
    return selector ? document.querySelector(selector) : null;
  }

  function currentEntityType(root) {
    const control = typeControl(root);
    return String((control && control.value) || root.dataset.teAutocompleteType || 'TE').trim() || 'TE';
  }

  function sourceConfig(root, input) {
    const source = String(root.dataset.teAutocompleteSource || 'taxonomy').trim();
    if (source === 'path-finder-entities') {
      const entityType = currentEntityType(root);
      const query = String(input.value || '').trim();
      const params = new URLSearchParams({
        view: 'entities',
        type: entityType,
        q: query,
        limit: String(maxVisible),
      });
      return {
        key: `path-finder-entities:${entityType}:${query.toLowerCase()}`,
        url: apiUrl(`path_finder.php?${params.toString()}`),
        label: entityType,
      };
    }

    return {
      key: 'taxonomy:items',
      url: taxonomyUrl(),
      label: 'TE',
    };
  }

  function loadNames(root, input) {
    const config = sourceConfig(root, input);
    if (cache.has(config.key)) {
      return cache.get(config.key);
    }
    const promise = fetch(config.url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`autocomplete API HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (payload && payload.ok === false) {
          throw new Error(String(payload.error || 'autocomplete API failed'));
        }
        const names = Array.isArray(payload.items)
          ? payload.items.map((item) => String(item && item.name ? item.name : '').trim()).filter(Boolean)
          : [];
        return [...new Set(names)].sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));
      });
    cache.set(config.key, promise);
    return promise;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function optionHtml(name, query) {
    const text = String(name || '');
    const prefix = String(query || '').trim();
    if (!prefix || !text.toLowerCase().startsWith(prefix.toLowerCase())) {
      return `<span class="te-autocomplete-rest">${escapeHtml(text)}</span>`;
    }
    return [
      `<span class="te-autocomplete-match">${escapeHtml(text.slice(0, prefix.length))}</span>`,
      `<span class="te-autocomplete-rest">${escapeHtml(text.slice(prefix.length))}</span>`,
    ].join('');
  }

  function filteredNames(names, query) {
    const prefix = String(query || '').trim().toLowerCase();
    if (!prefix) {
      return names.slice(0, maxVisible);
    }
    return names.filter((name) => name.toLowerCase().startsWith(prefix)).slice(0, maxVisible);
  }

  function initAutocomplete(root) {
    const input = root.querySelector('[data-te-autocomplete]');
    const button = root.querySelector('[data-te-autocomplete-toggle]');
    const menu = root.querySelector('[data-te-autocomplete-menu]');
    if (!input || !button || !menu || root.dataset.teAutocompleteReady === '1') {
      return;
    }
    root.dataset.teAutocompleteReady = '1';

    let isOpen = false;
    let requestSerial = 0;

    function closeMenu() {
      isOpen = false;
      root.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
      menu.innerHTML = '';
    }

    function dispatchSelection() {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new CustomEvent('te-autocomplete-select', { bubbles: true, detail: { value: input.value } }));
      if (input.dataset.teAutocompleteSubmit === 'true') {
        const form = input.form;
        if (form && typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else if (form) {
          form.submit();
        }
      }
    }

    function renderOptions(names) {
      const query = input.value;
      const matches = filteredNames(names, query);
      if (!matches.length) {
        menu.innerHTML = `<div class="te-autocomplete-empty">No ${escapeHtml(sourceConfig(root, input).label)} names match this prefix.</div>`;
        return;
      }
      menu.innerHTML = matches.map((name) => (
        `<button type="button" class="te-autocomplete-option" data-te-name="${escapeHtml(name)}">${optionHtml(name, query)}</button>`
      )).join('');
    }

    function loadAndRender() {
      const serial = ++requestSerial;
      loadNames(root, input)
        .then((names) => {
          if (serial === requestSerial) {
            renderOptions(names);
          }
        })
        .catch(() => {
          if (serial === requestSerial) {
            menu.innerHTML = `<div class="te-autocomplete-empty">${escapeHtml(sourceConfig(root, input).label)} name list is unavailable.</div>`;
          }
        });
    }

    function openMenu() {
      isOpen = true;
      root.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      menu.innerHTML = `<div class="te-autocomplete-empty">Loading ${escapeHtml(sourceConfig(root, input).label)} names...</div>`;
      loadAndRender();
    }

    function refreshMenu() {
      if (!isOpen) {
        return;
      }
      loadAndRender();
    }

    button.addEventListener('click', () => {
      if (isOpen) {
        closeMenu();
      } else {
        input.focus();
        openMenu();
      }
    });
    input.addEventListener('focus', openMenu);
    input.addEventListener('input', refreshMenu);
    const control = typeControl(root);
    if (control) {
      control.addEventListener('change', () => {
        if (root.dataset.teAutocompleteClearOnTypeChange === 'true') {
          input.value = '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (isOpen) {
          menu.innerHTML = `<div class="te-autocomplete-empty">Loading ${escapeHtml(sourceConfig(root, input).label)} names...</div>`;
          refreshMenu();
        }
      });
    }
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMenu();
      }
    });
    menu.addEventListener('click', (event) => {
      const option = event.target.closest('[data-te-name]');
      if (!option) {
        return;
      }
      input.value = option.dataset.teName || '';
      closeMenu();
      dispatchSelection();
    });
    document.addEventListener('click', (event) => {
      if (!root.contains(event.target)) {
        closeMenu();
      }
    });
  }

  function initAll() {
    document.querySelectorAll('[data-te-autocomplete-root]').forEach(initAutocomplete);
  }

  window.TEKGTeAutocomplete = { initAll };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll, { once: true });
  } else {
    initAll();
  }
})();

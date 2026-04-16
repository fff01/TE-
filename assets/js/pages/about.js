(() => {
  const header = document.getElementById('protoHeader');
  function syncHeader() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 12);
  }
  const links = Array.from(document.querySelectorAll('.about-nav a'));
  const panes = {
    introduction: document.getElementById('pane-introduction'),
    search: document.getElementById('pane-search'),
    preview: document.getElementById('pane-preview'),
    browse: document.getElementById('pane-browse'),
    download: document.getElementById('pane-download')
  };

  function setActivePane(name) {
    Object.entries(panes).forEach(([key, pane]) => {
      if (!pane) return;
      pane.classList.toggle('is-active', key === name);
    });
    links.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.pane === name);
    });
  }

  links.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      const paneName = link.dataset.pane;
      if (!paneName) return;
      setActivePane(paneName);
      const url = new URL(window.location.href);
      url.hash = paneName;
      window.history.replaceState({}, '', url.toString());
    });
  });

  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();
  setActivePane(window.location.hash ? window.location.hash.replace('#', '') : 'introduction');
})();

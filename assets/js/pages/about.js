(() => {
  const header = document.getElementById('protoHeader');
  function syncHeader() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 12);
  }
  const links = Array.from(document.querySelectorAll('.about-nav a'));
  const panes = Array.from(document.querySelectorAll('.about-pane'));

  function setActivePane(name) {
    const activeName = links.some((link) => link.dataset.pane === name) ? name : 'home';
    panes.forEach((pane) => {
      pane.classList.toggle('is-active', pane.id === `pane-${activeName}`);
    });
    links.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.pane === activeName);
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
  setActivePane(window.location.hash ? window.location.hash.replace('#', '') : 'home');
})();

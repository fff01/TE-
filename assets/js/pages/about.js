(() => {
  const header = document.getElementById('protoHeader');
  function syncHeader() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 12);
  }
  const links = Array.from(document.querySelectorAll('.about-nav a'));
  const sections = Array.from(document.querySelectorAll('.about-doc-section'));

  function setActivePane(name) {
    const activeName = links.some((link) => link.dataset.pane === name) ? name : 'resource';
    links.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.pane === activeName);
    });
    return activeName;
  }

  links.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      const paneName = link.dataset.pane;
      if (!paneName) return;
      const activeName = setActivePane(paneName);
      const target = document.getElementById(`section-${activeName}`);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.history.replaceState({}, '', `#section-${activeName}`);
    });
  });

  if ('IntersectionObserver' in window && sections.length) {
    const observer = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) return;
      const name = visible.target.dataset.section;
      if (name) setActivePane(name);
    }, { rootMargin: '-18% 0px -62% 0px', threshold: [0.12, 0.24, 0.4] });
    sections.forEach((section) => observer.observe(section));
  }

  window.addEventListener('scroll', syncHeader, { passive: true });
  syncHeader();
  const hashName = window.location.hash ? window.location.hash.replace(/^#section-/, '').replace('#', '') : 'resource';
  setActivePane(hashName);
})();

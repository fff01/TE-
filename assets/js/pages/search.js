(() => {
          const header = document.getElementById('protoHeader');
          function syncHeader() {
            if (!header) return;
            header.classList.toggle('is-scrolled', window.scrollY > 12);
          }
          window.addEventListener('scroll', syncHeader, { passive: true });
          syncHeader();
        })();

(function () {
        const panel = document.getElementById('search-karyotype-panel');
        const view = document.getElementById('search-karyotype-view');
        const status = document.getElementById('search-karyotype-status');

        if (!panel || !view) {
          return;
        }

        const dataPath = view.dataset.karyotypePath || '';
        if (!dataPath || typeof window.Karyotype !== 'function') {
          if (status) {
            status.textContent = 'Genome annotation distribution is unavailable right now.';
          }
          return;
        }

        fetch(dataPath, { cache: 'no-store' })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Failed to load karyotype data');
            }
            return response.json();
          })
          .then(function (data) {
            view.innerHTML = '';
            new window.Karyotype(view, data);
            if (status) {
              status.hidden = true;
            }
          })
          .catch(function (error) {
            console.error(error);
            if (status) {
              status.textContent = 'Genome annotation distribution is unavailable right now.';
              status.hidden = false;
            }
          });
      }());

(function () {
        const mount = document.getElementById('search_jbrowse_linear_genome_view');
        const controls = Array.from(document.querySelectorAll('#searchJBrowseTrackControls input[data-track-id]'));
        const hitSelect = document.getElementById('searchJBrowseHitSelect');
        const restoreHitsBtn = document.getElementById('searchJBrowseRestoreHits');
        const hitScopeEl = document.getElementById('searchJBrowseHitScope');
        const karyotypeView = document.getElementById('search-karyotype-view');
        const repeatCountEl = document.getElementById('searchJBrowseRepeatCount');
        const refseqCountEl = document.getElementById('searchJBrowseRefseqCount');
        const defaultLocEl = document.getElementById('searchJBrowseDefaultLoc');
        const openLink = document.getElementById('searchJBrowseOpenLink');
        const configNode = document.getElementById('search-page-config');
        const pageConfig = configNode ? JSON.parse(configNode.textContent || '{}') : {};
        const browserBaseUrl = String(pageConfig.browserBaseUrl || '');
        const configUrl = String(pageConfig.configUrl || '');
        const karyotypeHitMap = pageConfig && typeof pageConfig.karyotypeHitMap === 'object' ? pageConfig.karyotypeHitMap : {};

        if (!mount || !configUrl || typeof window.JBrowseReactLinearGenomeView === 'undefined') {
          return;
        }

        const { React, createRoot, createViewState, JBrowseLinearGenomeView } = window.JBrowseReactLinearGenomeView;
        const root = createRoot(mount);
        let runtimeConfig = null;

        function hitKeyFromParts(chrom, start, end) {
          const safeChrom = String(chrom || '').trim();
          const safeStart = String(start ?? '').trim();
          const safeEnd = String(end ?? '').trim();
          return safeChrom && safeStart && safeEnd ? `${safeChrom}:${safeStart}-${safeEnd}` : '';
        }

        function buildHitOptionData(hit, index, prefix) {
          const chrom = String(hit && hit.chrom ? hit.chrom : '').trim();
          const start = String(hit && hit.start !== undefined ? hit.start : '').trim();
          const end = String(hit && hit.end !== undefined ? hit.end : '').trim();
          return {
            value: `${prefix || 'hit'}-${index}`,
            chrom,
            start,
            end,
            strand: String(hit && hit.strand ? hit.strand : '+'),
            length: String(hit && hit.length !== undefined ? hit.length : ''),
            score: String(hit && hit.score !== undefined ? hit.score : ''),
            label: String(hit && hit.label ? hit.label : (chrom && start && end ? `${chrom}:${Number(start) + 1}-${end}` : `${prefix || 'hit'}-${index}`)),
          };
        }

        const sampledHitOptions = hitSelect
          ? Array.from(hitSelect.options).map((option, index) => ({
              value: String(option.value || `sample-${index}`),
              chrom: String(option.dataset.chrom || ''),
              start: String(option.dataset.start || ''),
              end: String(option.dataset.end || ''),
              strand: String(option.dataset.strand || '+'),
              length: String(option.dataset.length || ''),
              score: String(option.dataset.score || ''),
              label: String(option.textContent || '').trim(),
            }))
          : [];

        function getSelectedTrackIds() {
          return controls.filter(input => input.checked).map(input => input.dataset.trackId);
        }

        function getSelectedHitParams() {
          const option = hitSelect && hitSelect.selectedOptions.length ? hitSelect.selectedOptions[0] : null;
          return {
            chrom: option ? String(option.dataset.chrom || '') : '',
            start: option ? String(option.dataset.start || '') : '',
            end: option ? String(option.dataset.end || '') : '',
          };
        }

        function setHitScope(text, showRestore) {
          if (hitScopeEl) {
            const normalized = String(text || '').trim();
            hitScopeEl.textContent = normalized;
            hitScopeEl.hidden = normalized === '';
          }
          if (restoreHitsBtn) {
            restoreHitsBtn.hidden = !showRestore;
          }
        }

        function renderHitOptions(options, preferredKey) {
          if (!hitSelect) {
            return;
          }
          const normalizedOptions = Array.isArray(options) ? options : [];
          hitSelect.innerHTML = '';
          if (normalizedOptions.length === 0) {
            return;
          }
          let selectedIndex = 0;
          normalizedOptions.forEach((item, index) => {
            const option = document.createElement('option');
            option.value = String(item.value || `hit-${index}`);
            option.dataset.chrom = String(item.chrom || '');
            option.dataset.start = String(item.start || '');
            option.dataset.end = String(item.end || '');
            if (item.strand !== undefined) option.dataset.strand = String(item.strand || '+');
            if (item.length !== undefined) option.dataset.length = String(item.length || '');
            if (item.score !== undefined) option.dataset.score = String(item.score || '');
            option.textContent = String(item.label || option.value);
            const optionKey = hitKeyFromParts(option.dataset.chrom, option.dataset.start, option.dataset.end);
            if (preferredKey && optionKey === preferredKey) {
              selectedIndex = index;
            }
            hitSelect.appendChild(option);
          });
          if (hitSelect.options.length > 0) {
            hitSelect.selectedIndex = selectedIndex;
          }
        }

        function restoreSampledHitOptions() {
          const currentHit = getSelectedHitParams();
          const preferredKey = hitKeyFromParts(currentHit.chrom, currentHit.start, currentHit.end);
          renderHitOptions(sampledHitOptions, preferredKey);
          setHitScope('', false);
          loadConfig(buildConfigUrl());
        }

        function buildBrowserUrl() {
          const url = new URL(browserBaseUrl, window.location.origin);
          const hit = getSelectedHitParams();
          if (hit.chrom) {
            url.searchParams.set('chr', hit.chrom);
          }
          if (hit.start) {
            url.searchParams.set('start', hit.start);
          }
          if (hit.end) {
            url.searchParams.set('end', hit.end);
          }
          url.searchParams.delete('format');
          return url.toString();
        }

        function buildConfigUrl() {
          const url = new URL(buildBrowserUrl());
          url.searchParams.set('format', 'config');
          return url.toString();
        }

        function renderBrowser() {
          if (!runtimeConfig) {
            return;
          }
          const selectedTrackIds = getSelectedTrackIds();
          const trackConfigs = [
            {
              type: 'FeatureTrack',
              trackId: 'repeats_hg38',
              name: 'Repeats',
              assemblyNames: ['hg38'],
              category: ['Annotation'],
              adapter: {
                type: 'Gff3Adapter',
                gffLocation: { uri: runtimeConfig.repeatTrackUrl },
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'ncbi_refseq_window',
              name: 'NCBI RefSeq',
              assemblyNames: ['hg38'],
              category: ['Annotation'],
              adapter: {
                type: 'Gff3Adapter',
                gffLocation: { uri: runtimeConfig.refseqTrackUrl },
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'clinvar_variants',
              name: 'ClinVar variants',
              assemblyNames: ['hg38'],
              category: ['ClinVar'],
              adapter: {
                type: 'BigBedAdapter',
                uri: runtimeConfig.clinvarMainUrl,
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'clinvar_cnv',
              name: 'ClinVar CNV',
              assemblyNames: ['hg38'],
              category: ['ClinVar'],
              adapter: {
                type: 'BigBedAdapter',
                uri: runtimeConfig.clinvarCnvUrl,
              },
            },
          ];
          const selectedTracks = trackConfigs.filter(track => selectedTrackIds.includes(track.trackId));
          const state = new createViewState({
            assembly: {
              name: 'hg38',
              sequence: {
                type: 'ReferenceSequenceTrack',
                trackId: 'hg38_reference',
                name: 'Reference sequence',
                assemblyNames: ['hg38'],
                adapter: {
                  type: 'IndexedFastaAdapter',
                  fastaLocation: { uri: runtimeConfig.fastaUrl },
                  faiLocation: { uri: runtimeConfig.faiUrl },
                },
              },
            },
            tracks: selectedTracks,
            defaultSession: {
              name: runtimeConfig.pageMeta && runtimeConfig.pageMeta.te ? `JBrowse - ${runtimeConfig.pageMeta.te}` : 'JBrowse locus session',
              view: {
                id: 'linearGenomeView',
                type: 'LinearGenomeView',
                init: {
                  assembly: 'hg38',
                  loc: runtimeConfig.pageMeta.defaultLoc,
                  tracks: selectedTrackIds,
                },
              },
            },
          });
          root.render(React.createElement(JBrowseLinearGenomeView, { viewState: state }));
        }

        function applyConfig(config) {
          runtimeConfig = config;
          if (defaultLocEl && config.pageMeta) {
            defaultLocEl.textContent = String(config.pageMeta.defaultLoc ?? '-');
          }
          if (repeatCountEl && config.pageMeta) {
            repeatCountEl.textContent = String(config.pageMeta.repeatFeatureCount ?? '-');
          }
          if (refseqCountEl && config.pageMeta) {
            refseqCountEl.textContent = String(config.pageMeta.refseqFeatureCount ?? '-');
          }
          if (openLink) {
            openLink.href = buildBrowserUrl();
          }
          renderBrowser();
          window.requestAnimationFrame(renderBrowser);
          window.setTimeout(renderBrowser, 120);
        }

        function loadConfig(url) {
          root.render(React.createElement('div', { className: 'jbrowse-loading' }, 'Loading selected genomic hit...'));
          fetch(url, { cache: 'no-store' })
            .then(function (response) {
              if (!response.ok) {
                throw new Error('Failed to load JBrowse config');
              }
              return response.json();
            })
            .then(applyConfig)
            .catch(function (error) {
              console.error(error);
              mount.innerHTML = '<div class="jbrowse-loading">Genome browser is unavailable right now.</div>';
            });
        }

        function handleKaryotypeClick(event) {
          const detail = event && event.detail ? event.detail : {};
          const chrom = String(detail.contig || '').trim();
          const start = Number(detail.start || 0);
          const end = Number(detail.end || 0);
          if (!chrom || !start || !end) {
            return;
          }
          const key = `${chrom}:${start}-${end}`;
          const bins = karyotypeHitMap && typeof karyotypeHitMap === 'object' && karyotypeHitMap.bins ? karyotypeHitMap.bins : {};
          const bin = bins[key];
          if (!bin || !Array.isArray(bin.hits) || bin.hits.length === 0) {
            return;
          }

          const filteredHitOptions = bin.hits.map((hit, index) => buildHitOptionData(hit, index, 'bin'));
          renderHitOptions(filteredHitOptions, '');
          setHitScope(`Showing ${filteredHitOptions.length} hit${filteredHitOptions.length === 1 ? '' : 's'} in ${key}`, true);
          loadConfig(buildConfigUrl());
        }

        controls.forEach(input => {
          input.addEventListener('change', renderBrowser);
        });
        if (hitSelect) {
          hitSelect.addEventListener('change', function () {
            loadConfig(buildConfigUrl());
          });
        }
        if (restoreHitsBtn) {
          restoreHitsBtn.addEventListener('click', restoreSampledHitOptions);
        }
        if (karyotypeView && karyotypeHitMap && karyotypeHitMap.available) {
          karyotypeView.addEventListener('karyotypeclicked', handleKaryotypeClick);
        }

        loadConfig(configUrl);
      })();
(function () {
        const graphPanelEl = document.getElementById('search-graph-panel');
        const graphToggleBtn = document.getElementById('search-graph-toggle');
        const graphToggleIconEl = document.getElementById('search-graph-toggle-icon');
        const navLinks = Array.from(document.querySelectorAll('[data-detail-nav-link]'));

        function setGraphExpanded(expanded) {
          if (!graphPanelEl || !graphToggleBtn) return;
          graphPanelEl.classList.toggle('is-collapsed', !expanded);
          graphToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          if (graphToggleIconEl) {
            graphToggleIconEl.innerHTML = expanded ? '&#9652;' : '&#9662;';
          }
          graphToggleBtn.title = expanded ? 'Collapse local graph' : 'Expand local graph';
        }

        function setActiveSection(id) {
          navLinks.forEach((link) => {
            const isActive = link.getAttribute('href') === `#${id}`;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
              link.setAttribute('aria-current', 'location');
            } else {
              link.removeAttribute('aria-current');
            }
          });
        }

        if (graphToggleBtn) {
          graphToggleBtn.addEventListener('click', function () {
            const expanded = graphPanelEl ? graphPanelEl.classList.contains('is-collapsed') : false;
            setGraphExpanded(expanded);
          });
        }

        navLinks.forEach((link) => {
          link.addEventListener('click', function () {
            const targetId = (link.getAttribute('href') || '').replace('#', '');
            if (targetId === 'search-graph-panel') {
              setGraphExpanded(true);
            }
            if (targetId) {
              setActiveSection(targetId);
            }
          });
        });

        const sections = navLinks
          .map((link) => document.getElementById((link.getAttribute('href') || '').replace('#', '')))
          .filter(Boolean);

        if (sections.length > 0) {
          setActiveSection(sections[0].id);
          if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
              const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
              if (visible.length > 0 && visible[0].target && visible[0].target.id) {
                setActiveSection(visible[0].target.id);
              }
            }, {
              rootMargin: '-15% 0px -65% 0px',
              threshold: [0.05, 0.15, 0.35, 0.6],
            });
            sections.forEach((section) => observer.observe(section));
          }
        }

        setGraphExpanded(false);
      }());

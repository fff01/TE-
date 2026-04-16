(function () {
          const resultsId = 'expressionResults';
          const resultsRoot = document.getElementById(resultsId);
          if (!resultsRoot) return;

          async function refreshExpressionResults(url) {
            const currentScrollY = window.scrollY;
            try {
              const response = await fetch(url, {
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
              });
              if (!response.ok) throw new Error(`HTTP ${response.status}`);
              const html = await response.text();
              const parser = new DOMParser();
              const doc = parser.parseFromString(html, 'text/html');
              const nextResults = doc.getElementById(resultsId);
              if (!nextResults) throw new Error('Expression results fragment was not found in the response.');
              const liveResults = document.getElementById(resultsId);
              if (!liveResults) return;
              liveResults.outerHTML = nextResults.outerHTML;
              window.history.pushState({ expressionUrl: url }, '', url);
              window.scrollTo(0, currentScrollY);
            } catch (error) {
              console.error('Expression results refresh failed:', error);
              window.location.href = url;
            }
          }

          document.addEventListener('click', function (event) {
            const target = event.target.closest('.expression-page-btn, .expression-value-mode-btn');
            if (!target) return;
            if (!(target instanceof HTMLAnchorElement)) return;
            if (target.classList.contains('is-disabled')) {
              event.preventDefault();
              return;
            }
            event.preventDefault();
            refreshExpressionResults(target.href);
          });

          document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.closest('#' + resultsId)) return;
            event.preventDefault();
            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);
            url.search = '';
            for (const [key, value] of formData.entries()) {
              if (typeof value === 'string' && value !== '') {
                url.searchParams.set(key, value);
              }
            }
            refreshExpressionResults(url.toString());
          });

          document.addEventListener('keydown', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (!target.classList.contains('expression-page-jump-input')) return;
            if (event.key !== 'Enter') return;
            const form = target.form;
            if (!form) return;
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
              form.requestSubmit();
            } else {
              form.submit();
            }
          });

          window.addEventListener('popstate', function () {
            refreshExpressionResults(window.location.href);
          });
        })();

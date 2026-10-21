(() => {
    if (window.__instantNavigationInstalled) return;
    window.__instantNavigationInstalled = true;

    const nativeDocumentAdd = document.addEventListener.bind(document);
    const nativeWindowAdd = window.addEventListener.bind(window);
    const loadedScripts = new Set(
        Array.from(document.querySelectorAll('script[src]'))
            .map((script) => new URL(script.src, window.location.href).href)
    );
    let pageScope = new AbortController();
    let pageIntervals = new Set();
    let activeRequest = null;

    function addPageListener(addListener, type, listener, options) {
        if (!pageScope || (options && typeof options === 'object' && options.signal)) {
            addListener(type, listener, options);
            return;
        }

        const scopedOptions = typeof options === 'boolean'
            ? { capture: options, signal: pageScope.signal }
            : { ...(options || {}), signal: pageScope.signal };
        addListener(type, listener, scopedOptions);
    }

    document.addEventListener = function(type, listener, options) {
        addPageListener(nativeDocumentAdd, type, listener, options);
    };
    window.addEventListener = function(type, listener, options) {
        addPageListener(nativeWindowAdd, type, listener, options);
    };

    const nativeSetInterval = window.setInterval.bind(window);
    const nativeClearInterval = window.clearInterval.bind(window);
    window.setInterval = function(callback, delay, ...args) {
        const intervalId = nativeSetInterval(callback, delay, ...args);
        pageIntervals.add(intervalId);
        return intervalId;
    };
    window.clearInterval = function(intervalId) {
        pageIntervals.delete(intervalId);
        nativeClearInterval(intervalId);
    };

    function resetPageScope() {
        pageScope.abort();
        pageIntervals.forEach(nativeClearInterval);
        pageIntervals = new Set();
        pageScope = new AbortController();
    }

    function setNavigationBusy(busy) {
        let indicator = document.getElementById('instantNavigationProgress');
        if (!indicator && document.body) {
            indicator = document.createElement('div');
            indicator.id = 'instantNavigationProgress';
            indicator.setAttribute('role', 'progressbar');
            indicator.setAttribute('aria-label', 'Loading page');
            indicator.style.cssText = 'position:fixed;z-index:2147483647;top:0;left:0;width:0;height:3px;pointer-events:none;opacity:0;transition:width .25s ease,opacity .15s;background:linear-gradient(90deg,#0284c7,#38bdf8,#0284c7);';
            document.body.appendChild(indicator);
        }
        if (indicator) {
            indicator.style.opacity = busy ? '1' : '0';
            indicator.style.width = busy ? '35%' : '100%';
        }
        if (document.body) document.body.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    async function executePageScripts(sourceDocument, responseUrl) {
        const scripts = Array.from(sourceDocument.body.querySelectorAll('script'));
        const eventHandlerNames = new Set();
        sourceDocument.body.querySelectorAll('*').forEach((element) => {
            Array.from(element.attributes)
                .filter((attribute) => /^on(?:click|submit|change|input|error|load)$/i.test(attribute.name))
                .forEach((attribute) => {
                    const calledNames = attribute.value.matchAll(/\b([A-Za-z_$][\w$]*)\s*\(/g);
                    for (const match of calledNames) eventHandlerNames.add(match[1]);
                });
        });

        for (const sourceScript of scripts) {
            const script = document.createElement('script');
            Array.from(sourceScript.attributes).forEach((attribute) => {
                script.setAttribute(attribute.name, attribute.value);
            });

            if (sourceScript.src) {
                const scriptUrl = new URL(sourceScript.getAttribute('src'), responseUrl).href;
                const scriptPath = new URL(scriptUrl).pathname;
                if (scriptPath.endsWith('/instant-navigation.js')) continue;
                if (loadedScripts.has(scriptUrl) &&
                    /\/(?:jquery(?:\.min)?|bootstrap(?:\.bundle)?(?:\.min)?)\.js$/i.test(scriptPath)) continue;
                script.src = scriptUrl;
                script.async = false;
                await new Promise((resolve, reject) => {
                    script.addEventListener('load', resolve, { once: true });
                    script.addEventListener('error', () => reject(new Error(`Could not load ${scriptUrl}`)), { once: true });
                    document.body.appendChild(script);
                });
                loadedScripts.add(scriptUrl);
                continue;
            }

            const scriptType = (sourceScript.getAttribute('type') || '').toLowerCase();
            if (scriptType && !['text/javascript', 'application/javascript', 'module'].includes(scriptType)) {
                script.textContent = sourceScript.textContent;
                document.body.appendChild(script);
                continue;
            }

            if (scriptType === 'module') {
                script.textContent = sourceScript.textContent;
            } else {
                const exposedHandlers = Array.from(eventHandlerNames)
                    .map((name) => `window[${JSON.stringify(name)}] = typeof ${name} === 'undefined' ? window[${JSON.stringify(name)}] : ${name};`)
                    .join('\n');
                script.textContent = `(() => {\n${sourceScript.textContent}\n${exposedHandlers}\n})();`;
            }
            document.body.appendChild(script);
        }

        document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true }));
        window.dispatchEvent(new Event('load'));
    }

    async function navigate(url, options = {}) {
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        const request = activeRequest;
        setNavigationBusy(true);

        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'Accept': 'text/html' },
                signal: request.signal,
            });
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('text/html')) {
                window.location.assign(response.url || url.href);
                return;
            }

            const html = await response.text();
            if (request !== activeRequest) return;

            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            if (!nextDocument.body || !nextDocument.head) {
                window.location.assign(response.url || url.href);
                return;
            }

            if (response.redirected && new URL(response.url).pathname !== url.pathname) {
                window.location.assign(response.url);
                return;
            }

            if (options.addHistory !== false) {
                history.replaceState(
                    { ...(history.state || {}), scrollY: window.scrollY },
                    '',
                    window.location.href
                );
                history.pushState({}, '', url.href);
            }

            resetPageScope();
            document.head.innerHTML = nextDocument.head.innerHTML;
            Array.from(document.body.attributes).forEach((attribute) => {
                document.body.removeAttribute(attribute.name);
            });
            Array.from(nextDocument.body.attributes).forEach((attribute) => {
                document.body.setAttribute(attribute.name, attribute.value);
            });
            document.body.innerHTML = nextDocument.body.innerHTML;
            setNavigationBusy(true);

            await executePageScripts(nextDocument, response.url || url.href);
            if (options.scrollY !== undefined) {
                window.scrollTo(0, options.scrollY);
            } else if (url.hash) {
                const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
                if (target) target.scrollIntoView();
            } else {
                window.scrollTo(0, 0);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Instant navigation failed; using regular navigation.', error);
                window.location.assign(url.href);
            }
        } finally {
            if (request === activeRequest) {
                activeRequest = null;
                setNavigationBusy(false);
            }
        }
    }

    nativeDocumentAdd('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.target && link.target !== '_self' ||
            link.hasAttribute('download') || link.hasAttribute('data-no-instant')) return;

        const url = new URL(link.href, window.location.href);
        if (url.href === window.location.href) return;
        if (url.origin !== window.location.origin ||
            url.pathname === window.location.pathname && url.hash ||
            /\/logout\/?$/.test(url.pathname) ||
            /(?:export|download|excel|csv)/i.test(url.pathname) ||
            /\.(?:pdf|xlsx?|csv|docx?|zip|png|jpe?g|gif|webp|svg|mp4|mp3)$/i.test(url.pathname)) return;

        event.preventDefault();
        navigate(url);
    });

    nativeWindowAdd('popstate', (event) => {
        navigate(new URL(window.location.href), {
            addHistory: false,
            scrollY: event.state && Number.isFinite(event.state.scrollY) ? event.state.scrollY : 0,
        });
    });
})();

'use strict';

/* Top loading bar for normal admin/vendor/landing page navigation.
   Runs in the BUBBLE phase and honors event.defaultPrevented, so AJAX links,
   modal/dropdown toggles and AJAX form submits (which preventDefault) never
   start a bar that would have nothing to clear it. PJAX handles fullscreen. */
(function () {
    var bar = document.getElementById('page-progress-bar');
    if (!bar) return;

    var safetyTimer = null;

    function inFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function startProgress() {
        clearTimeout(safetyTimer);
        bar.classList.remove('done');
        // force reflow so the width transition restarts from 0
        void bar.offsetWidth;
        bar.classList.add('active');
        // handlers that preventDefault after us (registered on document.ready)
        // would otherwise leave the bar parked at 92% forever
        safetyTimer = setTimeout(finishProgress, 15000);
    }

    function finishProgress() {
        clearTimeout(safetyTimer);
        if (!bar.classList.contains('active')) return;
        bar.classList.remove('active');
        bar.classList.add('done');
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (inFullscreen()) return;

        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (link.dataset.bsToggle || link.hasAttribute('data-no-progress')) return;
        if (link.hasAttribute('download')) return;
        if (link.target && link.target !== '' && link.target !== '_self') return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
        if (link.origin && link.origin !== window.location.origin) return;

        try {
            var url = new URL(link.href, window.location.href);
            if (url.pathname === window.location.pathname && url.search === window.location.search) return;
        } catch (error) {
            return;
        }

        startProgress();
    });

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented || inFullscreen()) return;
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.getAttribute('target') === '_blank') return;
        startProgress();
    });

    // Reset when returning via browser back/forward (bfcache)
    window.addEventListener('pageshow', finishProgress);
})();

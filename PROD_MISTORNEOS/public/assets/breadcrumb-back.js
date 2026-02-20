/**
 * Añade botón "Volver al lugar de procedencia" en la línea del breadcrumb.
 * Se ejecuta automáticamente en todas las páginas que tengan breadcrumb.
 */
(function() {
    'use strict';

    function injectStyles() {
        if (document.getElementById('breadcrumb-back-styles')) return;
        var style = document.createElement('style');
        style.id = 'breadcrumb-back-styles';
        style.textContent = [
            '.breadcrumb-with-back .btn-breadcrumb-back {',
            '  background-color: #0d6efd; color: #fff; border-color: #0d6efd; font-weight: 500;',
            '}',
            '.breadcrumb-with-back .btn-breadcrumb-back:hover {',
            '  background-color: #0b5ed7; border-color: #0a58ca; color: #fff;',
            '}',
            '.breadcrumb-with-back .breadcrumb, .breadcrumb-with-back .breadcrumb-item, .breadcrumb-with-back .breadcrumb-item a, .breadcrumb-with-back .breadcrumb-item.active { color: #000 !important; }',
            '.breadcrumb-with-back nav[aria-label="breadcrumb"], .breadcrumb-with-back nav[aria-label="breadcrumb"] ol, .breadcrumb-with-back nav[aria-label="breadcrumb"] li, .breadcrumb-with-back nav[aria-label="breadcrumb"] a { color: #000 !important; }',
            '.breadcrumb-with-back .breadcrumb-item a:hover, .breadcrumb-with-back nav[aria-label="breadcrumb"] a:hover { color: #333 !important; text-decoration: underline; }',
            '.breadcrumb-with-back .breadcrumb-item + .breadcrumb-item::before { color: #000 !important; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function initBreadcrumbBack() {
        injectStyles();
        var selectors = [
            'nav[aria-label="breadcrumb"]',
            '.breadcrumb-modern'
        ];
        var seen = new Set();

        selectors.forEach(function(selector) {
            var elements = document.querySelectorAll(selector);
            elements.forEach(function(el) {
                if (seen.has(el)) return;
                seen.add(el);

                if (el.closest('.breadcrumb-with-back')) return;

                var btn = document.createElement('a');
                btn.href = 'javascript:void(0)';
                btn.className = 'btn btn-outline-secondary btn-sm btn-breadcrumb-back flex-shrink-0';
                btn.title = 'Volver al lugar de procedencia';
                btn.setAttribute('aria-label', 'Volver');
                btn.innerHTML = '<i class="fas fa-arrow-left me-1"></i> Volver';

                btn.addEventListener('click', function() {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else if (document.referrer) {
                        window.location.href = document.referrer;
                    } else {
                        window.location.href = 'index.php' + (window.location.search || '');
                    }
                });

                var wrap = document.createElement('div');
                wrap.className = 'breadcrumb-with-back d-flex align-items-center gap-2 flex-wrap';

                el.parentNode.insertBefore(wrap, el);
                wrap.appendChild(btn);
                wrap.appendChild(el);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBreadcrumbBack);
    } else {
        initBreadcrumbBack();
    }
})();

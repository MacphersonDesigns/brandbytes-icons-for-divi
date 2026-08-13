(function () {
    'use strict';

    var icons = window.cifdBuilderIcons || [];
    if (!icons.length) {
        return;
    }

    var byValue = {};
    icons.forEach(function (icon) {
        byValue[icon.decodedUnicode] = icon;
        byValue[icon.unicode] = icon;
    });

    function findIconForText(text) {
        text = (text || '').trim();
        return byValue[text] || null;
    }

    function applyThumb(el, url) {
        var img = el.querySelector('img.cifd-icon-img');
        if (!img) {
            img = document.createElement('img');
            img.className = 'cifd-icon-img';
            img.alt = '';
            img.style.position = 'absolute';
            img.style.left = '50%';
            img.style.top = '50%';
            img.style.transform = 'translate(-50%, -50%)';
            img.style.width = '70%';
            img.style.height = '70%';
            img.style.objectFit = 'contain';
            el.style.position = 'relative';
            el.appendChild(img);
        }
        if (img.getAttribute('src') !== url) {
            img.src = url;
        }
    }

    function patchPickerButtons(root) {
        var buttons = root.querySelectorAll('.et-vb-icon-picker-list button');
        buttons.forEach(function (btn) {
            var icon = findIconForText(btn.textContent);
            if (icon) {
                applyThumb(btn, icon.url);
            }
        });
    }

    function patchPreviewIcons(root) {
        var els = root.querySelectorAll('.et-pb-icon, .et_pb_icon_wrap');
        els.forEach(function (el) {
            var icon = findIconForText(el.textContent) || findIconForText(el.getAttribute('data-icon'));
            if (!icon) {
                if (el.classList.contains('cifd-custom-icon')) {
                    var stale = el.querySelector('img.cifd-icon-img');
                    if (stale) {
                        stale.remove();
                    }
                    el.classList.remove('cifd-custom-icon');
                }
                return;
            }
            el.classList.add('cifd-custom-icon');
            applyThumb(el, icon.url);
        });
    }

    function patch(root) {
        root = root || document;
        patchPickerButtons(root);
        patchPreviewIcons(root);
    }

    function registerIconListFilter() {
        var hooks = (window.vendor && window.vendor.wp && window.vendor.wp.hooks) || (window.wp && window.wp.hooks);
        if (!hooks || !hooks.addFilter) {
            window.setTimeout(registerIconListFilter, 100);
            return;
        }
        if (window.__cifdIconListFilterAdded) {
            return;
        }
        window.__cifdIconListFilterAdded = true;

        hooks.addFilter('iconList', 'customIconsForDivi.customIcons', function (existing) {
            return (icons || []).concat(existing || []);
        });
    }

    function watchForChanges() {
        if (!window.MutationObserver || window.__cifdIconObserver) {
            return;
        }
        var pending = false;
        var schedule = function () {
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                patch(document);
            });
        };

        window.__cifdIconObserver = new MutationObserver(schedule);
        window.__cifdIconObserver.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function boot() {
        registerIconListFilter();
        watchForChanges();
        patch(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();

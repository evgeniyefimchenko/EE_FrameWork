(function ($) {
    "use strict";

    function applyBackgroundSources() {
        $('[data-bg-src]').each(function () {
            var src = ($(this).attr('data-bg-src') || '').trim();
            if (src !== '') {
                $(this).css('background-image', 'url(' + src + ')');
            }
        });
    }

    function initSwipers() {
        if (typeof Swiper === 'undefined') {
            return;
        }

        document.querySelectorAll('.allbriz-swiper').forEach(function (node) {
            if (node.swiper) {
                return;
            }

            var options = {
                slidesPerView: 1,
                spaceBetween: 24,
                speed: 900,
                loop: false
            };

            var rawOptions = node.getAttribute('data-swiper-options');
            if (rawOptions) {
                try {
                    options = Object.assign(options, JSON.parse(rawOptions));
                } catch (error) {
                    console.error('Invalid Swiper options', error, rawOptions);
                }
            }

            new Swiper(node, options);
        });
    }

    function initImagePopups() {
        if (!$.fn.magnificPopup) {
            return;
        }

        $('.popup-image').magnificPopup({
            type: 'image',
            gallery: {
                enabled: true
            }
        });
    }

    function renderIframeMap(node) {
        var embedUrl = (node.dataset.embedUrl || '').trim();
        var canvas = node.querySelector('.allbriz-map-canvas');
        if (!canvas || embedUrl === '') {
            return;
        }

        canvas.innerHTML = '';
        var frame = document.createElement('iframe');
        frame.className = 'allbriz-map-iframe';
        frame.loading = 'lazy';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.allowFullscreen = true;
        frame.src = embedUrl;
        frame.title = (node.dataset.title || 'Карта').trim();
        canvas.appendChild(frame);
    }

    function createMapRegistry() {
        var providers = {};

        function registerProvider(name, handler) {
            if (!name || typeof handler !== 'function') {
                return;
            }
            providers[name] = handler;
        }

        function renderNode(node) {
            if (!node || node.dataset.mapReady === '1') {
                return;
            }

            var provider = (node.dataset.provider || '').trim();
            var renderMode = (node.dataset.renderMode || '').trim();
            var renderer = providers[provider] || providers[renderMode] || providers.iframe;
            if (typeof renderer !== 'function') {
                return;
            }

            renderer(node);
            node.dataset.mapReady = '1';
        }

        function init(root) {
            var scope = root || document;
            scope.querySelectorAll('.allbriz-map-widget').forEach(renderNode);
        }

        registerProvider('iframe', renderIframeMap);
        registerProvider('osm_embed', renderIframeMap);
        registerProvider('custom_embed', renderIframeMap);

        return {
            registerProvider: registerProvider,
            init: init,
            renderNode: renderNode
        };
    }

    function initMapWidgets() {
        if (!window.AllbrizMaps) {
            window.AllbrizMaps = createMapRegistry();
        }

        window.AllbrizMaps.init(document);
    }

    function initDevelopmentNotice() {
        var modalNode = document.getElementById('allbrizDevNoticeModal');
        if (!modalNode || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        var storageKey = 'allbriz_dev_notice_seen_v1';
        var hasSeen = false;
        try {
            hasSeen = window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            hasSeen = false;
        }

        var modal = bootstrap.Modal.getOrCreateInstance(modalNode, {
            backdrop: 'static',
            keyboard: false
        });

        modalNode.addEventListener('hidden.bs.modal', function () {
            try {
                window.localStorage.setItem(storageKey, '1');
            } catch (error) {
            }
        }, { once: true });

        var acknowledgeButton = document.getElementById('allbrizDevNoticeAcknowledge');
        if (acknowledgeButton) {
            acknowledgeButton.addEventListener('click', function () {
                try {
                    window.localStorage.setItem(storageKey, '1');
                } catch (error) {
                }
            });
        }

        if (!hasSeen) {
            modal.show();
        }
    }

    $(document).ready(function () {
        applyBackgroundSources();
        initSwipers();
        initImagePopups();
        initMapWidgets();
        initDevelopmentNotice();
    });
}(jQuery));

// Weiches Scrollen (Lenis, liegt lokal unter /js/lenis) und optionaler Parallax-Hintergrund.
// Bei "Bewegung reduzieren" scrollt die Seite normal und die Symbole laufen einfach mit.
(function () {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var lenis = null;

    if (!reduceMotion && window.Lenis) {
        lenis = new window.Lenis({
            autoRaf: true,
            lerp: 0.1,
            anchors: true,           // #-Links ebenfalls weich anspringen
            allowNestedScroll: true, // Timeline, Dokument-Viewer und Dialoge scrollen weiter selbst
        });
    }

    var layer = document.querySelector('[data-parallax]');
    if (!layer) return;

    var items = Array.prototype.slice.call(layer.children);
    var placed = [];

    // Jedes Element steht bei data-offset (Anteil der Fensterhöhe), wenn man data-at (Anteil 0–1) der Seite
    // gescrollt hat. Davor und danach wandert es mit data-speed mal der Scrollgeschwindigkeit.
    function layout() {
        var vh = window.innerHeight;
        var range = Math.max(0, document.documentElement.scrollHeight - vh);

        placed = items.map(function (el) {
            var speed = reduceMotion ? 1 : parseFloat(el.dataset.speed || '0.5');
            var at = parseFloat(el.dataset.at || '0') * range;
            return {
                el: el,
                speed: speed,
                base: at * speed + parseFloat(el.dataset.offset || '0.3') * vh,
                spin: reduceMotion ? 0 : parseFloat(el.dataset.spin || '0') / 1000, // Grad pro Pixel
            };
        });
        update();
    }

    function update() {
        var y = window.scrollY;
        placed.forEach(function (p) {
            p.el.style.transform = 'translate3d(0, ' + (p.base - y * p.speed).toFixed(1) + 'px, 0)'
                + ' rotate(calc(var(--rotate, 0deg) + ' + (y * p.spin).toFixed(2) + 'deg))';
        });
    }

    if (lenis) {
        lenis.on('scroll', update);
    } else {
        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;
                update();
            });
        }, { passive: true });
    }

    // Seitenhöhe ändert sich durch nachladende Bilder, aufgeklappte Karten usw.
    new ResizeObserver(layout).observe(document.body);
    window.addEventListener('resize', layout);

    layout();
    items.forEach(function (el) {
        el.classList.add('is-placed');
    });
})();

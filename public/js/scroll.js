// Weiches Scrollen (Lenis, liegt lokal unter /js/lenis) und Parallax-Hintergrund.
// Bei "Bewegung reduzieren" scrollt die Seite normal und die Bilder laufen einfach mit.
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

    var images = Array.prototype.slice.call(layer.querySelectorAll('img'));
    var placed = [];

    // Verteilt die Bilder über die ganze Seitenlänge: Bild i steht an seiner Startposition,
    // wenn man i/(n-1) der Seite gescrollt hat. Kurze Seiten bekommen entsprechend weniger Bilder.
    function layout() {
        var vh = window.innerHeight;
        var range = Math.max(0, document.documentElement.scrollHeight - vh);
        var count = Math.min(images.length, Math.max(1, Math.round(range / vh) + 1));

        placed = [];
        images.forEach(function (img, i) {
            var visible = i < count;
            img.hidden = !visible;
            if (!visible) return;

            var speed = reduceMotion ? 1 : parseFloat(img.dataset.speed || '0.5');
            var at = count > 1 ? (i / (count - 1)) * range : 0;
            // abwechselnd etwas höher und tiefer im Bild, damit links und rechts nicht gleichauf stehen
            var offset = vh * (i % 2 ? 0.45 : 0.1);
            placed.push({ img: img, speed: speed, base: at * speed + offset });
        });
        update();
    }

    function update() {
        var y = window.scrollY;
        placed.forEach(function (p) {
            p.img.style.transform = 'translate3d(0, ' + (p.base - y * p.speed).toFixed(1) + 'px, 0)';
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
    images.forEach(function (img) {
        img.classList.add('is-placed');
    });
})();

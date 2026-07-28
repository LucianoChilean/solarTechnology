/* SolarTechonolgy — banner deslizante, filtros de catálogo y (si la tienda
   está activa) carrito lateral con add-to-cart AJAX. */
(function () {
  'use strict';

  var d = document;

  /* ---- Menú móvil -------------------------------------------------------- */
  (function initNavToggle() {
    var toggle = d.querySelector('[data-st-nav-toggle]');
    var nav = toggle && d.getElementById(toggle.getAttribute('aria-controls'));
    if (!nav) return;

    function setOpen(open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Al elegir una sección, el panel se cierra.
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });

    d.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });

    // Si se vuelve a ancho de escritorio, la nav debe quedar en su estado normal.
    if (window.matchMedia) {
      var wide = window.matchMedia('(min-width: 641px)');
      var onChange = function (ev) {
        if (ev.matches) setOpen(false);
      };
      if (wide.addEventListener) wide.addEventListener('change', onChange);
      else if (wide.addListener) wide.addListener(onChange);
    }
  })();

  /* ---- Banner deslizante (3 diapositivas) -------------------------------- */
  (function initSlider() {
    var slider = d.querySelector('[data-st-slider]');
    if (!slider) return;

    var tracks = slider.querySelectorAll('[data-st-track]');
    var slides = slider.querySelectorAll('.st-slide');
    var dots = slider.querySelectorAll('[data-st-dot]');
    var count = slides.length;
    if (count < 2) return;

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var delay = parseInt(slider.getAttribute('data-autoplay'), 10) || 0;
    var index = 0;
    var timer = null;

    function goTo(i) {
      index = (i % count + count) % count;
      var offset = -index * 100;

      tracks.forEach(function (track) {
        track.style.transform = 'translateX(' + offset + '%)';
      });

      slides.forEach(function (slide, n) {
        var active = n === index;
        if (active) {
          slide.removeAttribute('aria-hidden');
        } else {
          slide.setAttribute('aria-hidden', 'true');
        }
        // Lo que no se ve tampoco recibe el foco del teclado.
        slide.querySelectorAll('a, button').forEach(function (el) {
          el.tabIndex = active ? 0 : -1;
        });
      });

      dots.forEach(function (dot, n) {
        dot.classList.toggle('is-active', n === index);
        dot.setAttribute('aria-selected', n === index ? 'true' : 'false');
      });
    }

    function next() { goTo(index + 1); }
    function prev() { goTo(index - 1); }

    function play() {
      stop();
      if (!delay || reduced || d.hidden) return;
      timer = setInterval(next, delay);
    }
    function stop() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    var prevBtn = slider.querySelector('[data-st-prev]');
    var nextBtn = slider.querySelector('[data-st-next]');
    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); play(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { next(); play(); });

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        goTo(parseInt(dot.getAttribute('data-st-dot'), 10) || 0);
        play();
      });
    });

    // Pausa al pasar el mouse, al enfocar con el teclado o al ocultar la pestaña.
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', play);
    slider.addEventListener('focusin', stop);
    slider.addEventListener('focusout', play);
    d.addEventListener('visibilitychange', function () { d.hidden ? stop() : play(); });

    // Flechas del teclado.
    slider.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { prev(); play(); }
      if (e.key === 'ArrowRight') { next(); play(); }
    });

    // Deslizar con el dedo.
    var startX = null;
    var swiped = false;
    slider.addEventListener('pointerdown', function (e) {
      startX = e.clientX;
      swiped = false;
    });
    slider.addEventListener('pointerup', function (e) {
      if (startX === null) return;
      var dx = e.clientX - startX;
      startX = null;
      if (Math.abs(dx) < 45) return;
      swiped = true;
      dx < 0 ? next() : prev();
      play();
    });
    // Un deslizamiento no debe abrir el enlace que quedó debajo del dedo.
    slider.addEventListener('click', function (e) {
      if (swiped) { e.preventDefault(); swiped = false; }
    }, true);

    goTo(0);
    play();
  })();

  /* ---- Drawer del carrito (solo si la tienda está activa) ---------------- */
  var drawer = d.querySelector('[data-st-cart-drawer]');
  var backdrop = d.querySelector('[data-st-cart-backdrop]');

  function openCart() {
    if (!drawer) return;
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.classList.add('is-open');
    d.body.style.overflow = 'hidden';
  }
  function closeCart() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.classList.remove('is-open');
    d.body.style.overflow = '';
  }
  window.stOpenCart = openCart;

  d.addEventListener('click', function (e) {
    var openBtn = e.target.closest('[data-st-open-cart]');
    if (openBtn) { e.preventDefault(); openCart(); return; }
    if (e.target.closest('[data-st-close-cart]') || e.target.closest('[data-st-cart-backdrop]')) {
      e.preventDefault(); closeCart(); return;
    }
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCart(); });

  /* ---- Filtro de categorías (client-side, como el ejemplo) --------------- */
  var filters = d.querySelector('[data-st-filters]');
  if (filters) {
    filters.addEventListener('click', function (e) {
      var btn = e.target.closest('.st-chip-btn');
      if (!btn) return;
      filters.querySelectorAll('.st-chip-btn').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      var cat = btn.getAttribute('data-cat');
      d.querySelectorAll('[data-st-grid] .st-card').forEach(function (card) {
        var cats = (card.getAttribute('data-cat') || '').split(' ');
        var show = cat === 'all' || cats.indexOf(cat) !== -1;
        card.classList.toggle('is-hidden', !show);
      });
    });
  }

  /* ---- Add to cart AJAX (WooCommerce wc-ajax) ---------------------------- */
  function refreshFragments() {
    if (!window.jQuery || !window.ST) return;
    window.jQuery.post(
      (window.ST.cartUrl || '/').split('?')[0].replace(/\/$/, '') + '/?wc-ajax=get_refreshed_fragments',
      function () {}
    );
  }

  d.addEventListener('click', function (e) {
    var btn = e.target.closest('.st-add-to-cart');
    if (!btn) return;
    var id = btn.getAttribute('data-product_id');
    if (!id) return; // enlace normal
    e.preventDefault();

    var qty = btn.getAttribute('data-quantity') || 1;
    var original = btn.textContent;
    btn.textContent = 'Agregando…';
    btn.classList.add('is-loading');

    var body = new URLSearchParams();
    body.append('product_id', id);
    body.append('quantity', qty);

    var base = (window.ST && window.ST.cartUrl ? window.ST.cartUrl : location.origin).split('?')[0];
    var endpoint = base.replace(/\/(carrito|cart)\/?$/, '/') + '?wc-ajax=add_to_cart';

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.textContent = '✓ Agregado';
        // Actualiza el contador y el mini-cart vía fragments de Woo.
        if (data && data.fragments) {
          Object.keys(data.fragments).forEach(function (sel) {
            d.querySelectorAll(sel).forEach(function (node) {
              var tmp = d.createElement('div');
              tmp.innerHTML = data.fragments[sel];
              if (tmp.firstElementChild) node.replaceWith(tmp.firstElementChild);
            });
          });
        }
        if (window.jQuery) {
          window.jQuery(d.body).trigger('wc_fragment_refresh');
          window.jQuery(d.body).trigger('added_to_cart');
        }
        openCart();
        setTimeout(function () { btn.textContent = original; btn.classList.remove('is-loading'); }, 1400);
      })
      .catch(function () {
        // Fallback: navegar al enlace clásico de add-to-cart.
        btn.textContent = original;
        btn.classList.remove('is-loading');
        if (btn.getAttribute('href')) location.href = btn.getAttribute('href');
      });
  });
})();

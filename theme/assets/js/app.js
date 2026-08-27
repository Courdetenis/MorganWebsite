/* Morgane Cachia – Art — thème clair/sombre, menu, motion (GSAP + ScrollTrigger).
   Les animations utilisent `from` : sans JS, le contenu reste visible. */
(function () {
  if (window.__morganeInit) return;      // garde anti double-exécution
  window.__morganeInit = true;

  var root = document.documentElement;
  root.classList.add('js');

  /* -------------------------------------------------------
     1. Thème clair / sombre
     ------------------------------------------------------- */
  var STORE = 'morgane-theme';
  var mqDark = window.matchMedia('(prefers-color-scheme: dark)');

  var stored = null;
  try { stored = localStorage.getItem(STORE); } catch (e) {}

  var apply = function (theme) {
    root.setAttribute('data-theme', theme);
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    });
  };

  apply(stored || (mqDark.matches ? 'dark' : 'light'));

  // Sans choix explicite, on suit le réglage système en direct.
  mqDark.addEventListener('change', function (e) {
    var saved = null;
    try { saved = localStorage.getItem(STORE); } catch (err) {}
    if (!saved) apply(e.matches ? 'dark' : 'light');
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    apply(next);
    try { localStorage.setItem(STORE, next); } catch (err) {}
  });

  /* -------------------------------------------------------
     2. Menu
     Au-dessus de 720 px les liens sont posés à plat dans l'en-tête et
     le bouton est masqué : ce qui suit ne sert qu'en mobile.
     Le panneau n'est replié que si ce script tourne — sans lui, le CSS
     le laisse déplié plutôt que de le rendre inatteignable.
     ------------------------------------------------------- */
  var menu = document.querySelector('.menu');
  if (menu) {
    var trigger = menu.querySelector('.menu__trigger');

    var setOpen = function (open) {
      if (open) { menu.setAttribute('data-open', ''); }
      else { menu.removeAttribute('data-open'); }
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    trigger.addEventListener('click', function () {
      setOpen(!menu.hasAttribute('data-open'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !menu.hasAttribute('data-open')) return;
      setOpen(false);
      trigger.focus();
    });

    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target)) setOpen(false);
    });
  }

  /* -------------------------------------------------------
     3. En-tête : fond givré au défilement
     ------------------------------------------------------- */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 40); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* -------------------------------------------------------
     4. Carrousels d'œuvres
     Le bandeau défile déjà tout seul (CSS + scroll-snap) : on n'ajoute ici
     que ce qui a besoin de JS, les deux boutons. Placé avant la section
     Motion, qui coupe court quand l'utilisateur réduit les animations —
     les boutons, eux, ne sont pas une animation.
     ------------------------------------------------------- */
  var strips = document.querySelectorAll('.wp-block-gallery.is-style-strip');
  if (strips.length) {
    var en = (document.documentElement.lang || 'fr').slice(0, 2) === 'en';
    var LABEL = {
      prev: en ? 'Previous artworks' : 'Œuvres précédentes',
      next: en ? 'Next artworks' : 'Œuvres suivantes'
    };
    var ARROW = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>';

    strips.forEach(function (strip) {
      var wrap = document.createElement('div');
      // L'enveloppe prend la place du bandeau dans la mise en page : sans
      // reprendre `alignwide`, c'est elle qui serait bridée à 760 px.
      wrap.className = 'carousel' +
        (strip.classList.contains('alignwide') ? ' alignwide' : '') +
        (strip.classList.contains('alignfull') ? ' alignfull' : '');
      strip.parentNode.insertBefore(wrap, strip);
      wrap.appendChild(strip);

      var button = function (dir) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'carousel__btn carousel__btn--' + dir;
        b.setAttribute('aria-label', LABEL[dir]);
        b.innerHTML = ARROW;
        wrap.appendChild(b);
        return b;
      };

      var prev = button('prev');
      var next = button('next');

      // Un pas = une œuvre, gouttière comprise.
      var step = function () {
        var first = strip.querySelector('.wp-block-image');
        if (!first) return strip.clientWidth;
        return first.getBoundingClientRect().width +
          (parseFloat(getComputedStyle(strip).columnGap) || 0);
      };

      // Une série qui tient entière laisse les deux boutons désactivés, donc
      // invisibles : rien à annoncer et rien à cliquer.
      var sync = function () {
        var max = strip.scrollWidth - strip.clientWidth;
        prev.disabled = strip.scrollLeft <= 2;
        next.disabled = strip.scrollLeft >= max - 2;
      };

      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var go = function (sign) {
        strip.scrollBy({ left: sign * step(), behavior: reduce ? 'auto' : 'smooth' });
        // L'état des boutons ne dépend pas de l'événement `scroll` : il peut
        // être étranglé, et le défilement doux met le temps qu'il veut.
        setTimeout(sync, 450);
      };

      prev.addEventListener('click', function () { go(-1); });
      next.addEventListener('click', function () { go(1); });

      strip.addEventListener('scroll', sync, { passive: true });
      window.addEventListener('resize', sync);
      // Les images sont en chargement différé : les largeurs ne sont sûres
      // qu'une fois la page chargée.
      window.addEventListener('load', sync);
      sync();
    });
  }

  /* -------------------------------------------------------
     5. Motion
     ------------------------------------------------------- */
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (typeof gsap === 'undefined') return;
  if (window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  /* Titre du hero : découpé en mots pour l'entrée en scène.
     Ciblé par sa position (`.hero h1`) et non par un attribut : le titre est
     un bloc WordPress ordinaire, que Morgane doit pouvoir modifier sans voir
     l'éditeur se plaindre d'un attribut inconnu. */
  document.querySelectorAll('.hero h1, [data-split]').forEach(function (el) {
    if (el.dataset.splitDone) return;
    el.dataset.splitDone = '1';
    el.innerHTML = el.textContent.trim().split(/\s+/).map(function (w) {
      return '<span class="word"><span class="word__i">' + w + '</span></span>';
    }).join(' ');
  });

  /* Les contenus vivent dans WordPress : on ne va pas demander à Morgane de
     poser un attribut sur chaque bloc — et un attribut maison sur un bloc
     natif ferait râler l'éditeur. On désigne donc ici ce qui s'anime. */
  var REVEAL = [
    '[data-reveal]',
    'main > .wp-block-group > h2',
    'main > .wp-block-group > .wp-block-heading',
    '.section-head',
    '.nav-cards',
    '.wp-block-gallery',
    '.steps',
    '.note',
    '.form-wrap',
    'main .wp-block-buttons'
  ].join(',');

  var start = function () {
    var heroWords = gsap.utils.toArray('.hero h1 .word__i');
    // `.hero .art-float` est ici et non dans REVEAL : la boucle REVEAL écarte
    // explicitement tout ce qui est dans le hero, et une apparition au
    // défilement n'aurait de toute façon aucun sens au-dessus de la ligne de
    // flottaison — le déclencheur serait déjà passé au chargement.
    var heroRest  = gsap.utils.toArray('.hero .art-float, .hero__eyebrow, .hero__lede, .hero .wp-block-buttons, .hero .scroll-cue');

    if (heroWords.length || heroRest.length) {
      var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
      if (heroWords.length) tl.from(heroWords, { yPercent: 60, opacity: 0, duration: 0.7, stagger: 0.06 });
      if (heroRest.length)  tl.from(heroRest, { opacity: 0, y: 26, duration: 0.6, stagger: 0.12 }, '-=0.35');
    }

    gsap.utils.toArray(REVEAL).forEach(function (el) {
      if (el.closest('.hero')) return;
      gsap.from(el, {
        opacity: 0, y: 26, duration: 0.6, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 88%', toggleActions: 'play none none reverse' }
      });
    });

    if (document.querySelector('.woocommerce ul.products li.product')) {
      gsap.from('.woocommerce ul.products li.product', {
        opacity: 0, y: 24, scale: 0.96, duration: 0.5, ease: 'back.out(1.4)', stagger: 0.08,
        scrollTrigger: { trigger: '.woocommerce ul.products', start: 'top 85%' }
      });
    }

    gsap.utils.toArray('.blob').forEach(function (b, i) {
      gsap.to(b, {
        yPercent: (i % 2 === 0 ? -1 : 1) * (14 + i * 6), ease: 'none',
        scrollTrigger: { trigger: b.closest('section') || b, scrub: 0.6 }
      });
    });

    ScrollTrigger.refresh();
  };

  if (document.readyState === 'complete') { start(); }
  else { window.addEventListener('load', start); }
})();

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
     Au-dessus de 1280 px les liens sont posés à plat dans l'en-tête et
     le bouton est masqué : ce qui suit ne sert qu'en dessous.
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
     2 bis. Commandes de l'en-tête (langue, thème, compte, panier)
     Repliées derrière un bouton à trois points en dessous de 720 px. Au-dessus,
     le CSS laisse le panneau à plat et le déclencheur masqué : ce qui suit ne
     sert qu'en dessous, et ne gêne pas au-dessus.
     ------------------------------------------------------- */
  var tools = document.querySelector('[data-tools]');
  if (tools) {
    var toolsTrigger = tools.querySelector('.tools__trigger');

    var setToolsOpen = function (open) {
      if (open) { tools.setAttribute('data-open', ''); }
      else { tools.removeAttribute('data-open'); }
      toolsTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toolsTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      setToolsOpen(!tools.hasAttribute('data-open'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !tools.hasAttribute('data-open')) return;
      setToolsOpen(false);
      toolsTrigger.focus();
    });

    document.addEventListener('click', function (e) {
      if (!tools.contains(e.target)) setToolsOpen(false);
    });
  }

  /* -------------------------------------------------------
     2 bis. Sélecteur de langue
     Le panneau s'ouvre au survol par le CSS seul, ce qui suffit à la souris.
     Ici on ne traite que le clic — indispensable sur téléphone et tablette,
     où il n'y a pas de survol. Sans ce script, le survol continue de marcher
     et le panneau reste atteignable au clavier par `:focus-within`.
     ------------------------------------------------------- */
  var lang = document.querySelector('[data-lang-switch]');
  if (lang) {
    var langTrigger = lang.querySelector('.lang-switch__trigger');

    var setLangOpen = function (open) {
      if (open) { lang.setAttribute('data-open', ''); }
      else { lang.removeAttribute('data-open'); }
      langTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    langTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      setLangOpen(!lang.hasAttribute('data-open'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !lang.hasAttribute('data-open')) return;
      setLangOpen(false);
      langTrigger.focus();
    });

    document.addEventListener('click', function (e) {
      if (!lang.contains(e.target)) setLangOpen(false);
    });
  }

  /* -------------------------------------------------------
     2 ter. Rayons de la boutique
     Même partage que ci-dessus : le survol est affaire de CSS, on ne traite
     ici que le clic — indispensable là où survoler ne veut rien dire. Le lien
     « Boutique » n'est pas touché : il mène toujours à la vitrine, et c'est le
     chevron à côté qui déroule. Sous 1280 px le chevron est masqué et la liste
     est dépliée d'office : ce code n'a alors rien à faire.
     ------------------------------------------------------- */
  document.querySelectorAll('[data-submenu]').forEach(function (parent) {
    var trigger = parent.querySelector('.menu__sub-trigger');
    if (!trigger) return;

    var setSubOpen = function (open) {
      if (open) { parent.setAttribute('data-open', ''); }
      else { parent.removeAttribute('data-open'); }
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      setSubOpen(!parent.hasAttribute('data-open'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !parent.hasAttribute('data-open')) return;
      setSubOpen(false);
      trigger.focus();
    });

    document.addEventListener('click', function (e) {
      if (!parent.contains(e.target)) setSubOpen(false);
    });
  });

  /* -------------------------------------------------------
     2 quater. Bandeau de mots-clés : le mettre en marche s'il déborde
     Sur grand écran la liste tient en entier et reste immobile. Sur téléphone
     elle est plus large que l'écran, et le bandeau en coupait les deux bouts :
     là, et là seulement, elle défile. Le CSS ne sait pas mesurer, d'où ce
     calcul ici — il pose la classe, double la série de mots (la boucle a
     besoin de deux séries pour se recoudre sans saut) et donne la durée.
     ------------------------------------------------------- */
  var VITESSE_RUBAN = 35;   // pixels par seconde — l'allure du bandeau
  var moinsDeMouvement = window.matchMedia('(prefers-reduced-motion: reduce)');

  var rubans = Array.prototype.map.call(
    document.querySelectorAll('.color-band .marquee'),
    function (ruban) {
      var mots = Array.prototype.slice.call(ruban.children);

      /*
       * Les pages créées avant septembre portent leurs mots deux fois : la
       * boucle d'alors avait besoin de deux séries, et `install/pages.php`
       * n'en écrit plus qu'une depuis. On repart d'une série unique dans les
       * deux cas — c'est le clone plus bas qui la redouble, au besoin. Aucune
       * page n'a donc à être régénérée.
       */
      var moitie = mots.length / 2;
      if (mots.length >= 2 && mots.length % 2 === 0) {
        var enDouble = true;
        for (var i = 0; i < moitie; i++) {
          if (mots[i].textContent !== mots[i + moitie].textContent) { enDouble = false; break; }
        }
        if (enDouble) {
          for (var j = moitie; j < mots.length; j++) mots[j].remove();
          mots = mots.slice(0, moitie);
        }
      }

      return { bande: ruban.parentNode, ruban: ruban, serie: mots };
    }
  );

  var reglerRuban = function (r) {
    if (!r.serie.length) return;

    // Retour à la série seule : c'est la seule mise en page où la mesure veut
    // dire quelque chose, et celle qu'on garde si les mots tiennent.
    r.bande.classList.remove('is-defilant');
    while (r.ruban.children.length > r.serie.length) {
      r.ruban.removeChild(r.ruban.lastChild);
    }

    // Qui demande moins d'animations lit le bandeau sur plusieurs lignes
    // (feuille de style, § 16) : pas de mouvement, et surtout pas de série en
    // double, qui lui ferait lire deux fois la même liste.
    if (moinsDeMouvement.matches) return;

    /*
     * `getBoundingClientRect()` plutôt que `scrollWidth` : immobile, le ruban
     * est centré, et un débordement centré déborde des deux côtés — la partie
     * gauche échappe à `scrollWidth`, qui annoncerait que tout tient. Les
     * rectangles, eux, donnent la position réelle même hors du cadre.
     */
    var gauche = r.serie[0].getBoundingClientRect().left;
    var droite = r.serie[r.serie.length - 1].getBoundingClientRect().right;
    if (droite - gauche <= r.bande.clientWidth) return;   // ça tient : rien à faire

    r.serie.forEach(function (mot) { r.ruban.appendChild(mot.cloneNode(true)); });
    r.bande.classList.add('is-defilant');

    // Une durée par bandeau, tirée de sa largeur : les trois pages qui en
    // portent un n'ont pas le même nombre de mots, et une durée fixe les ferait
    // défiler à trois vitesses. Un tour = une série, soit la moitié du ruban.
    var duree = r.ruban.scrollWidth / 2 / VITESSE_RUBAN;
    r.ruban.style.setProperty('--marquee-duree', duree.toFixed(1) + 's');
  };

  if (rubans.length) {
    var largeurConnue = -1;
    var majRubans = function () {
      /*
       * Seule la largeur nous concerne. Sur mobile, la barre d'adresse qui se
       * replie au défilement change la hauteur et déclenche un `resize` : sans
       * ce garde-fou, l'animation repartirait de zéro à chaque fois.
       */
      if (root.clientWidth === largeurConnue) return;
      largeurConnue = root.clientWidth;
      rubans.forEach(reglerRuban);
    };

    majRubans();
    window.addEventListener('resize', majRubans);

    /*
     * Les titres sont dans une police auto-hébergée. Tant qu'elle n'est pas
     * arrivée, les mots sont mesurés dans la police de secours, plus étroite :
     * un bandeau limite se croirait à l'aise. On remesure à son arrivée.
     */
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () {
        largeurConnue = -1;
        majRubans();
      });
    }

    // La préférence « moins d'animations » se change sans recharger la page.
    if (moinsDeMouvement.addEventListener) {
      moinsDeMouvement.addEventListener('change', function () { rubans.forEach(reglerRuban); });
    }
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

    /* Construit en DOM plutôt qu'en chaîne de caractères : le titre vient de
       WordPress, et le repasser par `innerHTML` réinterprétait son texte comme
       du balisage. Une esperluette ou un chevron dans un titre suffisait à
       casser l'affichage. `textContent` ne réinterprète rien. */
    var mots = el.textContent.trim().split(/\s+/);
    var frag = document.createDocumentFragment();

    mots.forEach(function (mot, i) {
      if (i) frag.appendChild(document.createTextNode(' '));
      var enveloppe = document.createElement('span');
      enveloppe.className = 'word';
      var interieur = document.createElement('span');
      interieur.className = 'word__i';
      interieur.textContent = mot;
      enveloppe.appendChild(interieur);
      frag.appendChild(enveloppe);
    });

    el.replaceChildren(frag);
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
    '.pull-quote',
    'main .wp-block-buttons'
  ].join(',');

  var start = function () {
    var heroWords = gsap.utils.toArray('.hero h1 .word__i');
    // `.hero .art-float` est ici et non dans REVEAL : la boucle REVEAL écarte
    // explicitement tout ce qui est dans le hero, et une apparition au
    // défilement n'aurait de toute façon aucun sens au-dessus de la ligne de
    // flottaison — le déclencheur serait déjà passé au chargement.
    var heroRest  = gsap.utils.toArray('.hero .hero__media, .hero__eyebrow, .hero__lede, .hero p, .hero .wp-block-buttons, .hero .scroll-cue');
    var flotGauche = document.querySelector('.hero .art-float--left');
    var flotDroite = document.querySelector('.hero .art-float--right');

    if (heroWords.length || heroRest.length || flotGauche || flotDroite) {
      var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
      if (heroWords.length) tl.from(heroWords, { yPercent: 60, opacity: 0, duration: 0.7, stagger: 0.06 });
      if (heroRest.length)  tl.from(heroRest, { opacity: 0, y: 26, duration: 0.6, stagger: 0.12 }, '-=0.35');

      /* Les deux œuvres entrent par leur côté, avec un léger dépassement
         (`back.out`) qui les fait se poser plutôt qu'apparaître. `x`, `y` et
         `scale` seulement : la rotation reste à la propriété CSS `rotate`,
         indépendante de `transform`, que GSAP ne touche donc pas. */
      if (flotGauche) tl.from(flotGauche, { xPercent: -70, yPercent: 18, scale: .82, opacity: 0, duration: 1.15, ease: 'back.out(1.5)' }, '-=0.9');
      if (flotDroite) tl.from(flotDroite, { xPercent: 70, yPercent: -18, scale: .82, opacity: 0, duration: 1.15, ease: 'back.out(1.5)' }, '<0.18');

      /* Une fois posées, elles respirent : un lent va-et-vient vertical, les
         deux à des rythmes différents pour ne jamais se synchroniser. */
      tl.add(function () {
        if (flotGauche) gsap.to(flotGauche, { y: '+=14', duration: 3.4, yoyo: true, repeat: -1, ease: 'sine.inOut' });
        if (flotDroite) gsap.to(flotDroite, { y: '-=12', duration: 4.1, yoyo: true, repeat: -1, ease: 'sine.inOut' });
      });
    }

    /* Parallaxe à la souris, sur le seul axe horizontal — le vertical est déjà
       occupé par la respiration ci-dessus. Réservé aux appareils qui ont un
       curseur : sur tactile, il n'y aurait rien à suivre. */
    var hero = document.querySelector('.hero');
    if (hero && (flotGauche || flotDroite) && window.matchMedia('(hover: hover)').matches) {
      var versG = flotGauche ? gsap.quickTo(flotGauche, 'x', { duration: .9, ease: 'power3' }) : null;
      var versD = flotDroite ? gsap.quickTo(flotDroite, 'x', { duration: .9, ease: 'power3' }) : null;
      hero.addEventListener('mousemove', function (e) {
        var r = hero.getBoundingClientRect();
        var dx = (e.clientX - r.left) / r.width - 0.5;   // -0.5 à gauche, +0.5 à droite
        if (versG) versG(dx * -28);
        if (versD) versD(dx * -28);
      });
      hero.addEventListener('mouseleave', function () {
        if (versG) versG(0);
        if (versD) versD(0);
      });
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

    // GSAP et ScrollTrigger sont deux fichiers : le second peut manquer à l'appel
    // alors que le premier a chargé. Les animations sont déjà posées à ce stade,
    // seul ce rafraîchissement lèverait une erreur.
    if (window.ScrollTrigger) ScrollTrigger.refresh();
  };

  /* -------------------------------------------------------
     6. Quantité : deux boutons plutôt que les flèches du navigateur

     Délégué sur le document plutôt que posé sur chaque bouton : WooCommerce
     redessine ce bloc après un ajout au panier, et des écouteurs attachés aux
     éléments d'origine ne survivraient pas au remplacement.
     ------------------------------------------------------- */
  var qtyBornes = function (champ) {
    return {
      pas: parseFloat(champ.step) || 1,
      min: champ.min === '' ? 0 : parseFloat(champ.min),
      max: champ.max === '' ? Infinity : parseFloat(champ.max)
    };
  };

  var qtyMajEtat = function (champ) {
    var b = qtyBornes(champ);
    var val = parseFloat(champ.value);
    var wrap = champ.parentNode;
    var moins = wrap.querySelector('[data-qty-step="down"]');
    var plus = wrap.querySelector('[data-qty-step="up"]');
    if (moins) moins.disabled = !isNaN(val) && val <= b.min;
    if (plus) plus.disabled = !isNaN(val) && val >= b.max;
  };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-qty-step]') : null;
    if (!btn) return;

    var champ = btn.parentNode.querySelector('input.qty');
    if (!champ) return;

    var b = qtyBornes(champ);
    var val = parseFloat(champ.value);
    if (isNaN(val)) val = b.min;

    val += (btn.getAttribute('data-qty-step') === 'up' ? b.pas : -b.pas);
    champ.value = Math.min(b.max, Math.max(b.min, val));

    /* WooCommerce et l'extension d'options écoutent « change » pour recalculer
       les totaux affichés : sans cet événement, le prix ne suivrait pas. */
    champ.dispatchEvent(new Event('input', { bubbles: true }));
    champ.dispatchEvent(new Event('change', { bubbles: true }));
    qtyMajEtat(champ);
  });

  document.addEventListener('input', function (e) {
    if (e.target.matches && e.target.matches('.quantity input.qty')) qtyMajEtat(e.target);
  });

  var qtyInit = function () {
    document.querySelectorAll('.quantity input.qty').forEach(function (c) { qtyMajEtat(c); });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', qtyInit);
  } else {
    qtyInit();
  }

  /* -------------------------------------------------------
     7. Page Commande : sortir du mode retrait hors Québec

     Le retrait n'existe que pour une adresse au Québec (inc/boutique.php).
     En mode retrait, WooCommerce prend l'adresse de facturation comme adresse
     de livraison : un client qui en saisit une hors Québec voit disparaître
     le bouton « Remise en main propre »… et reste en mode retrait, sans tarif
     ni moyen d'en sortir — WooCommerce ne prévoit pas ce cas. On le ramène
     sur « Livraison », où son adresse l'attend.
     ------------------------------------------------------- */
  var retraitHorsQuebec = function (essais) {
    if (!document.querySelector('.wp-block-woocommerce-checkout')) return;

    /* Ce fichier passe avant les scripts de WooCommerce dans le pied de page,
       et un optimiseur de scripts peut bousculer l'ordre encore. Sans
       `wp.data` : on attend la fin du chargement, puis on réessaie dix
       secondes plutôt que d'abandonner en silence. */
    var data = window.wp && window.wp.data;
    if (!data) {
      if (document.readyState !== 'complete') {
        window.addEventListener('load', function () { retraitHorsQuebec(0); }, { once: true });
      } else if (essais < 50) {
        setTimeout(function () { retraitHorsQuebec(essais + 1); }, 200);
      }
      return;
    }

    /* Les stores de WooCommerce sont relus à chaque passage, jamais gardés :
       ils peuvent ne pas exister encore au moment de l'abonnement. */
    data.subscribe(function () {
      var commande = data.select('wc/store/checkout');
      var panier = data.select('wc/store/cart');
      // Une version de WooCommerce sans ces fonctions : on ne touche à rien.
      if (!commande || !panier || typeof commande.prefersCollection !== 'function') return;
      if (!commande.prefersCollection()) return;

      var settings = window.wc && window.wc.wcSettings;
      var retraits = (settings && settings.getSetting('collectableMethodIds')) || ['pickup_location'];
      var possible = panier.getShippingRates().every(function (colis) {
        return colis.shipping_rates.some(function (tarif) {
          return retraits.indexOf(tarif.method_id) !== -1;
        });
      });
      if (!possible) data.dispatch('wc/store/checkout').setPrefersCollection(false);
    });
  };

  // Lancé à part des animations : une erreur de GSAP ne doit pas priver la
  // page Commande de ce garde-fou.
  retraitHorsQuebec(0);

  if (document.readyState === 'complete') { start(); }
  else { window.addEventListener('load', start); }
})();

<?php
/**
 * Crée l'arborescence complète du site, en français et en anglais, avec les
 * vrais textes fournis par Morgane. Idempotent : relançable sans doublon.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/pages.php
 *
 * Le contenu n'est écrit que dans les pages vides, pour ne jamais écraser une
 * modification faite depuis l'admin. Pour forcer la réécriture (dev) :
 *
 *   docker compose run --rm -e MORGANE_FORCE=1 --entrypoint wp wpcli eval-file /scripts/pages.php
 *
 * Ou une seule page, par sa clé dans l'arborescence ci-dessous :
 *
 *   docker compose run --rm -e MORGANE_FORCE=about --entrypoint wp wpcli eval-file /scripts/pages.php
 *
 * Les images viennent de la médiathèque : media.php les y a importées et a écrit
 * la table « numéro de photo → attachement » dans l'option `morgane_media`.
 * Une seule image par photo, mais un texte alternatif par langue, écrit ici.
 */

if ( ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang inactif. Lancer pll-setup.php d\'abord.' );
}

if ( ! get_option( 'morgane_media' ) ) {
	WP_CLI::warning( 'Option morgane_media vide : lancer media.php d\'abord, sinon les pages seront écrites sans images.' );
}

/*
 * MORGANE_FORCE=1 réécrit tout le site ; MORGANE_FORCE=about,murals ne réécrit
 * que ces pages. La liste évite d'écraser le reste du travail fait depuis
 * l'admin quand une seule page est redessinée.
 */
$force_env  = (string) getenv( 'MORGANE_FORCE' );
$force_all  = ( '1' === $force_env );
$force_keys = array_flip( array_filter( array_map( 'trim', explode( ',', $force_env ) ) ) );

/* =========================================================================
   1. Arborescence
   ========================================================================= */

$tree = array(
	'home'           => array( 'fr' => array( 'accueil', 'Accueil' ), 'en' => array( 'home', 'Home' ) ),
	'murals'         => array( 'fr' => array( 'murales', 'Murales' ), 'en' => array( 'murals', 'Murals' ) ),
	'portfolio'      => array( 'fr' => array( 'portfolio', 'Portfolio' ), 'en' => array( 'works', 'Portfolio' ) ),
	'pf_youth'       => array( 'parent' => 'portfolio', 'fr' => array( 'illustration-jeunesse', 'Illustration jeunesse' ), 'en' => array( 'childrens-illustration', 'Children\'s illustration' ) ),
	'pf_illus'       => array( 'parent' => 'portfolio', 'fr' => array( 'illustration', 'Illustration' ), 'en' => array( 'illustration', 'Illustration' ) ),
	'pf_draw'        => array( 'parent' => 'portfolio', 'fr' => array( 'dessin', 'Dessin' ), 'en' => array( 'drawing', 'Drawing' ) ),
	'pf_murals'      => array( 'parent' => 'portfolio', 'fr' => array( 'murales', 'Murales' ), 'en' => array( 'murals', 'Murals' ) ),
	'about'          => array( 'fr' => array( 'a-propos', 'À propos' ), 'en' => array( 'about', 'About' ) ),
	'hub'            => array( 'fr' => array( 'commissions-et-collaborations', 'Commissions et collaborations' ), 'en' => array( 'commissions-and-collaborations', 'Commissions and collaborations' ) ),
	'commissions'    => array( 'parent' => 'hub', 'fr' => array( 'commissions', 'Commissions' ), 'en' => array( 'commissions', 'Commissions' ) ),
	'collaborations' => array( 'parent' => 'hub', 'fr' => array( 'collaborations', 'Collaborations' ), 'en' => array( 'collaborations', 'Collaborations' ) ),

	/*
	 * Pages de bas de page. Elles ne sont pas dans le menu — leur place est le
	 * pied de page, où on les cherche.
	 *
	 * L'anglais du contact s'appelle `contact-us` et non `contact` : deux pages
	 * de langues différentes ne peuvent pas partager un slug au même niveau,
	 * WordPress résolvant un chemin avant que Polylang n'ait son mot à dire.
	 * Même raison que `works` pour le portfolio.
	 */
	'contact'        => array( 'fr' => array( 'contact', 'Contact' ), 'en' => array( 'contact-us', 'Contact' ) ),
	'returns'        => array( 'fr' => array( 'retours-et-remboursements', 'Retours et remboursements' ), 'en' => array( 'returns-and-refunds', 'Returns and refunds' ) ),
	'privacy'        => array( 'fr' => array( 'confidentialite', 'Politique de confidentialité' ), 'en' => array( 'privacy', 'Privacy policy' ) ),
);

/*
 * Deux pages de langues différentes ne peuvent pas partager un slug au même
 * niveau : WordPress résout une page par son chemin avant que Polylang n'ait
 * son mot à dire, et c'est la première trouvée qui gagne. D'où deux choix :
 *   - Commissions et Collaborations sont rangées sous leur page d'entrée
 *     (parents différents, donc slugs libres — et l'arborescence y gagne) ;
 *   - le portfolio anglais s'appelle « works », usage courant sur les sites
 *     d'artistes, le titre affiché restant « Portfolio ».
 */

/**
 * Cherche une page par slug, dans une langue et sous un parent donnés.
 *
 * La requête demande explicitement toutes les langues, et c'est nous qui
 * filtrons ensuite sur celle voulue. En ligne de commande, Polylang ne charge
 * que son modèle de données : l'argument est ignoré, le filtrage manuel suffit.
 * Depuis wp-admin, ses filtres tournent et restreindraient la recherche à la
 * langue courante — on ne verrait pas la page qu'on vient de créer dans
 * l'autre langue, et on en créerait une seconde.
 */
function mg_find( $slug, $lang, $parent = null ) {
	/*
	 * `lang => ''` : « toutes les langues ». En WP-CLI, Polylang ne charge que
	 * son modèle de données et l'argument est ignoré — sans conséquence. Mais
	 * depuis wp-admin ses filtres tournent : il accroche `pre_get_posts`, que
	 * `suppress_filters` ne neutralise pas, et restreint silencieusement la
	 * requête à la langue courante. La recherche ne voyait alors pas les pages
	 * de l'autre langue et en recréait des doubles, qui se volaient les URL.
	 */
	$args = array(
		'post_type'        => 'page',
		'name'             => $slug,
		'post_status'      => 'any',
		'numberposts'      => -1,
		'suppress_filters' => true,
		'lang'             => '',
	);
	if ( null !== $parent ) {
		$args['post_parent'] = $parent;   // null = n'importe quel parent
	}

	$found = get_posts( $args );

	foreach ( $found as $page ) {
		if ( mg_claimed( (int) $page->ID ) ) {
			continue;
		}
		if ( pll_get_post_language( $page->ID ) === $lang ) {
			return (int) $page->ID;
		}
	}
	return 0;
}

/**
 * Registre des pages déjà attribuées.
 *
 * Deux pages du site portent le slug « murales » (la page Murales et le
 * portfolio Murales) : sans registre, la seconde recherche retomberait sur la
 * première. Le registre vit dans une variable statique et non dans une globale
 * parce que `wp eval-file` exécute le script dans une fonction : une variable
 * écrite au niveau racine du fichier n'est pas une globale.
 *
 * @param int  $id    Page à tester.
 * @param bool $claim true pour l'enregistrer.
 */
function mg_claimed( $id, $claim = false ) {
	static $taken = array();
	if ( $claim ) {
		$taken[ (int) $id ] = true;
		return true;
	}
	return isset( $taken[ (int) $id ] );
}

/**
 * Crée une page dans une langue donnée.
 * Le slug est remis en forme à la toute fin du script (voir passe 3).
 */
function mg_create( $slug, $title, $lang, $parent ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_parent'  => $parent,
			'post_status'  => 'publish',
			'post_content' => '',
		)
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $slug . ' : ' . $id->get_error_message() );
		return 0;
	}

	pll_set_post_language( $id, $lang );
	WP_CLI::log( 'Créée : ' . $slug . ' (' . $lang . ')' );
	return (int) $id;
}

// --- Passe 1 : créer les pages et lier les traductions --------------------
$ids = array();

// Les parents d'abord, sinon post_parent ne peut pas être résolu.
$ordered = array();
foreach ( $tree as $key => $def ) {
	if ( empty( $def['parent'] ) ) {
		$ordered[ $key ] = $def;
	}
}
foreach ( $tree as $key => $def ) {
	if ( ! empty( $def['parent'] ) ) {
		$ordered[ $key ] = $def;
	}
}

foreach ( $ordered as $key => $def ) {
	$pair = array();

	foreach ( array( 'fr', 'en' ) as $lang ) {
		$parent = empty( $def['parent'] ) ? 0 : ( $ids[ $def['parent'] ][ $lang ] ?? 0 );
		$slug   = $def[ $lang ][0];

		$id = mg_find( $slug, $lang, $parent );

		// Page existante rattachée au mauvais parent (arborescence corrigée
		// après coup) : on la replace au lieu d'en créer une deuxième.
		if ( ! $id ) {
			$loose = mg_find( $slug, $lang );
			if ( $loose ) {
				wp_update_post( array( 'ID' => $loose, 'post_parent' => $parent ) );
				$id = $loose;
				WP_CLI::log( 'Replacée : ' . $slug . ' (' . $lang . ') sous #' . $parent );
			}
		}

		// Traduction déjà liée mais créée sous un autre slug.
		if ( ! $id && 'en' === $lang && ! empty( $pair['fr'] ) ) {
			$linked = pll_get_post( $pair['fr'], 'en' );
			if ( $linked && pll_get_post_language( $linked ) === 'en' ) {
				$id = (int) $linked;
			}
		}

		if ( ! $id ) {
			$id = mg_create( $slug, $def[ $lang ][1], $lang, $parent );
		}

		$pair[ $lang ] = $id;
		mg_claimed( $id, true );
	}

	$ids[ $key ] = $pair;

	if ( $pair['fr'] && $pair['en'] && $pair['fr'] !== $pair['en'] ) {
		pll_save_post_translations( array( 'fr' => $pair['fr'], 'en' => $pair['en'] ) );
	}
}

// Accueil statique.
if ( $ids['home']['fr'] ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home']['fr'] );
	WP_CLI::log( 'Accueil statique : FR #' . $ids['home']['fr'] . ' ↔ EN #' . $ids['home']['en'] );
}

/* =========================================================================
   2. URL résolues (une carte par langue)
   ========================================================================= */

$urls = array();
foreach ( array( 'fr', 'en' ) as $lang ) {
	foreach ( $ids as $key => $pair ) {
		$urls[ $lang ][ '{{' . $key . '}}' ] = $pair[ $lang ] ? get_permalink( $pair[ $lang ] ) : home_url( '/' );
	}

	// Boutique WooCommerce : Polylang gratuit ne la traduit pas, on retombe
	// sur la page boutique unique si la traduction n'existe pas.
	$shop = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
	if ( $shop > 0 ) {
		$translated = pll_get_post( $shop, $lang );
		$shop       = $translated ? $translated : $shop;
		$urls[ $lang ]['{{shop}}'] = get_permalink( $shop );
	} else {
		$urls[ $lang ]['{{shop}}'] = home_url( '/' );
	}
}

/* =========================================================================
   3. Contenus
   ========================================================================= */

/**
 * Identifiant d'attachement d'une photo, d'après son numéro.
 *
 * La table est relue une seule fois. Elle vit dans une statique et non dans une
 * globale : `wp eval-file` exécute le script à l'intérieur d'une fonction.
 */
function mg_photo_id( $n ) {
	static $map = null;
	if ( null === $map ) {
		$map = (array) get_option( 'morgane_media', array() );
	}
	return isset( $map[ $n ] ) ? (int) $map[ $n ] : 0;
}

/**
 * Une image de la médiathèque, avec son srcset.
 *
 * `sizes` est indispensable : sans lui WordPress annonce « 100vw » et le
 * navigateur choisit dans le srcset une image bien plus large que le cadre où
 * elle atterrit — on téléchargerait 2000 px pour en afficher 390.
 *
 * @param int    $n     Numéro de photo.
 * @param string $alt   Texte alternatif, dans la langue de la page.
 * @param string $size  Taille WordPress. 'full' pour les bandeaux pleine largeur.
 * @param bool   $eager Image visible d'entrée de page : à charger sans attendre.
 * @param string $sizes Largeur d'affichage réelle, pour le choix dans le srcset.
 */
function mg_photo( $n, $alt, $size = 'large', $eager = false, $sizes = '' ) {
	$id = mg_photo_id( $n );
	if ( ! $id ) {
		return '';
	}

	$attr = array( 'alt' => $alt, 'decoding' => 'async' );
	if ( $sizes ) {
		$attr['sizes'] = $sizes;
	}
	if ( $eager ) {
		// Plus grosse image du premier écran : la différer retarderait d'autant
		// l'affichage perçu de la page.
		$attr['loading']       = 'eager';
		$attr['fetchpriority'] = 'high';
	} else {
		$attr['loading'] = 'lazy';
	}

	return wp_get_attachment_image( $id, $size, false, $attr );
}

/**
 * Galerie à défilement horizontal (style de bloc « strip » du thème).
 *
 * Le JS du thème l'enveloppe ensuite dans un carrousel (boutons précédent /
 * suivant). Aucun repère écrit n'est nécessaire : les boutons le disent mieux,
 * et au doigt l'œuvre suivante déjà entamée suffit.
 *
 * @param array $items array( array( numéro, texte alternatif ), … ).
 */
function mg_gallery( array $items, $lang ) {
	$inner = '';

	foreach ( $items as $item ) {
		$id = mg_photo_id( $item[0] );
		if ( ! $id ) {
			continue;
		}
		$src = wp_get_attachment_image_src( $id, 'large' );
		if ( ! $src ) {
			continue;
		}

		/*
		 * Balisage strictement identique à celui qu'enregistre le bloc image de
		 * WordPress : src, alt, class « wp-image-ID », largeur et hauteur. Rien
		 * de plus. Un srcset ou un loading figés ici feraient dire à l'éditeur
		 * que le bloc « contient un contenu inattendu » — ce sont des attributs
		 * que WordPress ajoute au rendu, pas au contenu.
		 */
		$inner .= '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n"
			. '<figure class="wp-block-image size-large"><img src="' . esc_url( $src[0] ) . '"'
			. ' alt="' . esc_attr( $item[1] ) . '"'
			. ' class="wp-image-' . $id . '"'
			. ' width="' . (int) $src[1] . '" height="' . (int) $src[2] . '"/></figure>' . "\n"
			. '<!-- /wp:image -->' . "\n";
	}

	if ( '' === $inner ) {
		return '';
	}

	// « wide » : le texte des pages reste à 760 px pour rester lisible, mais les
	// bandeaux d'œuvres prennent la largeur large du thème (1240 px).
	// imageCrop désactivé : toutes ces œuvres sont en portrait, elles gardent
	// leurs proportions. « is-cropped » forcerait un recadrage en hauteur.
	return '<!-- wp:gallery {"align":"wide","linkTo":"none","imageCrop":false,"className":"is-style-strip"} -->' . "\n"
		. '<figure class="wp-block-gallery alignwide has-nested-images columns-default is-style-strip">' . "\n"
		. $inner
		. '</figure>' . "\n"
		. '<!-- /wp:gallery -->';
}

/**
 * Grille de cartes image + libellé.
 *
 * @param array $cards array( array( 'url', 'libellé', 'note', numéro de photo, 'alt' ), … ).
 */
function mg_cards( array $cards ) {
	// alignwide : dans les pages, la grille sort de la colonne de texte. Sur
	// l'accueil elle est déjà dans une section large, la classe n'y change rien.
	$html = '<!-- wp:html -->' . "\n" . '<div class="nav-cards alignwide" data-reveal>' . "\n";
	foreach ( $cards as $card ) {
		// Grille auto-fit dans 1240 px : au plus large, une carte fait ~390 px.
		$photo = isset( $card[3] ) ? mg_photo( $card[3], $card[4], 'large', false, '(max-width: 780px) 100vw, 390px' ) : '';
		$html .= '  <div class="nav-card"><a href="' . $card[0] . '">'
			. '<span class="nav-card__frame">' . $photo . '</span>'
			. '<span class="nav-card__label">' . $card[1] . '</span>'
			. ( ! empty( $card[2] ) ? '<span class="nav-card__note">' . $card[2] . '</span>' : '' )
			. '</a></div>' . "\n";
	}
	$html .= '</div>' . "\n" . '<!-- /wp:html -->';

	return $html;
}

/**
 * Deux boutons côte à côte.
 *
 * Par défaut la rangée s'aligne à gauche, comme le texte qui la précède dans
 * les blocs de prose. Sous une section centrée, passer $center : la rangée est
 * un conteneur flex, donc sans consigne de justification elle reste collée à
 * gauche même quand tout le reste est centré.
 */
function mg_buttons( array $buttons, $center = false ) {
	$attrs = $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '';
	$class = $center ? ' is-content-justification-center' : '';
	$out   = '<!-- wp:buttons' . $attrs . ' -->' . "\n"
		. '<div class="wp-block-buttons' . $class . '">' . "\n";
	foreach ( $buttons as $i => $b ) {
		$style = 0 === $i ? '' : ' is-style-outline';
		$battr = 0 === $i ? '' : ' {"className":"is-style-outline"}';
		$out  .= '<!-- wp:button' . $battr . ' -->'
			. '<div class="wp-block-button' . $style . '"><a class="wp-block-button__link wp-element-button" href="' . $b[0] . '">' . $b[1] . '</a></div>'
			. '<!-- /wp:button -->' . "\n";
	}
	$out .= '</div>' . "\n" . '<!-- /wp:buttons -->';
	return $out;
}

function mg_h2( $text ) {
	return '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">' . $text . '</h2>' . "\n" . '<!-- /wp:heading -->';
}

/**
 * Chapeau de section : une accroche manuscrite au-dessus du titre.
 */
function mg_head( $eyebrow, $title ) {
	return '<!-- wp:group {"className":"section-head","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group section-head">' . "\n"
		. '<!-- wp:paragraph {"className":"eyebrow"} --><p class="eyebrow">' . $eyebrow . '</p><!-- /wp:paragraph -->' . "\n"
		. mg_h2( $title ) . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Bandeau d'ouverture d'une page : la mise en scène « aurore » du hero de
 * l'accueil, réutilisée telle quelle. La classe `hero` n'est pas décorative —
 * elle apporte le fond en dégradé animé, et `app.js` lui réserve son entrée en
 * scène (titre découpé en mots, puis image, chapeau et boutons en cascade).
 *
 * Les pages qui l'emploient prennent le gabarit sans titre : leur h1 est ici,
 * sinon le titre de la page ferait doublon juste au-dessus.
 *
 * Deux formes, selon qu'une photo est fournie :
 *   - avec photo, deux colonnes qui se font face, image et texte ;
 *   - sans, le texte se centre et deux œuvres flottent de part et d'autre.
 *
 * @param string $eyebrow Accroche manuscrite au-dessus du titre.
 * @param string $title   Titre de la page (h1).
 * @param array  $lede    Paragraphes ; le premier est le chapeau, en grand.
 * @param array  $buttons Boutons, au format de mg_buttons().
 * @param array  $photo   array( numéro, alt ) — 'wide' en 3e position pour une
 *                        image en paysage.
 * @param array  $floats  Deux array( numéro, alt ), employées sans photo.
 */
function mg_hero( $eyebrow, $title, array $lede, array $buttons, array $photo = array(), array $floats = array() ) {

	// Les formes floues vivent dans un bloc HTML : ce sont des éléments de
	// décor, rien que Morgane ait à modifier depuis l'éditeur.
	$html = '<!-- wp:html -->' . "\n"
		. '<div class="blob blob--rose" style="width:min(34vw,360px);aspect-ratio:1;top:6%;left:-7%;"></div>' . "\n"
		. '<div class="blob blob--sage" style="width:min(26vw,290px);aspect-ratio:1;bottom:8%;right:-5%;"></div>' . "\n";

	// Pas de `data-reveal` sur ces œuvres : la boucle REVEAL d'app.js saute
	// tout ce qui est dans le hero, qui a sa propre ouverture.
	foreach ( $floats as $i => $float ) {
		$html .= '<figure class="art-float art-float--' . ( 0 === $i ? 'left' : 'right' ) . '">'
			. mg_photo( $float[0], $float[1], 'medium_large', true, '210px' )
			. '</figure>' . "\n";
	}
	$html .= '<!-- /wp:html -->' . "\n";

	$text = '<!-- wp:paragraph {"className":"hero__eyebrow"} -->' . "\n"
		. '<p class="hero__eyebrow">' . $eyebrow . '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:heading {"level":1} -->' . "\n"
		. '<h1 class="wp-block-heading">' . $title . '</h1>' . "\n"
		. '<!-- /wp:heading -->' . "\n";

	foreach ( $lede as $i => $paragraph ) {
		$text .= 0 === $i
			? '<!-- wp:paragraph {"className":"hero__lede","fontSize":"large"} -->' . "\n"
				. '<p class="hero__lede has-large-font-size">' . $paragraph . '</p>' . "\n"
				. '<!-- /wp:paragraph -->' . "\n"
			: mg_p( $paragraph ) . "\n";
	}

	if ( $photo ) {
		$wide  = ( isset( $photo[2] ) && 'wide' === $photo[2] ) ? ' hero__media--wide' : '';
		$size  = '1240px';
		$inner = '<!-- wp:group {"className":"hero-split","layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group hero-split">' . "\n"
			. '<!-- wp:html -->' . "\n"
			. '<div class="hero__media' . $wide . '">'
			. mg_photo( $photo[0], $photo[1], 'large', true, '(max-width: 899px) 100vw, 480px' )
			. '</div>' . "\n"
			. '<!-- /wp:html -->' . "\n"
			. '<!-- wp:group {"className":"hero-split__text","layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group hero-split__text">' . "\n"
			. $text
			. mg_buttons( $buttons ) . "\n"
			. '</div>' . "\n" . '<!-- /wp:group -->' . "\n"
			. '</div>' . "\n" . '<!-- /wp:group -->' . "\n";
	} else {
		// Colonne étroite : centré, une accroche ne se lit pas sur 1240 px.
		$size  = '880px';
		$inner = '<!-- wp:group {"className":"hero__inner","layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group hero__inner">' . "\n"
			. $text
			. mg_buttons( $buttons, true ) . "\n"
			. '</div>' . "\n" . '<!-- /wp:group -->' . "\n";
	}

	return '<!-- wp:group {"tagName":"section","align":"full","className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}},"dimensions":{"minHeight":"72vh"}},"layout":{"type":"constrained","contentSize":"' . $size . '"}} -->' . "\n"
		. '<section class="wp-block-group alignfull hero" style="min-height:72vh;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
		. $html . $inner
		. '</section>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Section « atelier » : la mise en scène du hero, mais au fil de la page — un
 * titre centré dans une colonne étroite, deux œuvres qui flottent de part et
 * d'autre. Sert à faire respirer entre deux blocs de texte dense.
 *
 * @param string $tail Contenu ajouté sous les paragraphes (pastilles, boutons…).
 */
function mg_atelier( $eyebrow, $title, array $body, array $floats, $tail = '' ) {
	$html = '<!-- wp:html -->' . "\n"
		. '<div class="blob blob--lilac" style="width:min(28vw,300px);aspect-ratio:1;top:10%;right:-6%;"></div>' . "\n"
		. '<div class="blob blob--sage" style="width:min(24vw,260px);aspect-ratio:1;bottom:12%;left:-5%;"></div>' . "\n";

	// Ici, `data-reveal` : hors du hero, ces œuvres apparaissent au défilement.
	foreach ( $floats as $i => $float ) {
		$html .= '<figure class="art-float art-float--' . ( 0 === $i ? 'left' : 'right' ) . '" data-reveal>'
			. mg_photo( $float[0], $float[1], 'medium_large', false, '210px' )
			. '</figure>' . "\n";
	}
	$html .= '<!-- /wp:html -->' . "\n";

	$text = '';
	foreach ( $body as $i => $paragraph ) {
		$text .= 0 === $i
			? '<!-- wp:paragraph {"align":"center","fontSize":"large","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->' . "\n"
				. '<p class="has-text-align-center has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">'
				. $paragraph . '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
			: '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . $paragraph
				. '</p><!-- /wp:paragraph -->' . "\n";
	}

	return '<!-- wp:group {"tagName":"section","align":"full","className":"atelier","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->' . "\n"
		. '<section class="wp-block-group alignfull atelier" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
		. $html
		. '<!-- wp:paragraph {"align":"center","className":"eyebrow"} --><p class="has-text-align-center eyebrow">' . $eyebrow . '</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . $title . '</h2><!-- /wp:heading -->' . "\n"
		. $text
		. ( $tail ? $tail . "\n" : '' )
		. '</section>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Section ordinaire sur la largeur large : un chapeau de section puis
 * du contenu (grille de cartes, bandeau d'œuvres…).
 */
function mg_section( $eyebrow, $title, $inner ) {
	return '<!-- wp:group {"tagName":"section","align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->' . "\n"
		. '<section class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">' . "\n"
		. mg_head( $eyebrow, $title ) . "\n" . $inner . "\n"
		. '</section>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Bandeau de couleur défilant. Décoratif, donc `aria-hidden` : les mots sont
 * déjà dans la page, il ne s'agit que de les mettre en mouvement.
 */
function mg_marquee( array $words ) {
	$span = '<span>' . implode( '</span><span>', array_merge( $words, $words ) ) . '</span>';
	return '<!-- wp:html -->' . "\n"
		. '<div class="color-band alignfull" aria-hidden="true"><div class="marquee">' . $span . '</div></div>' . "\n"
		. '<!-- /wp:html -->';
}

/**
 * Une phrase sortie du texte courant, en grand et dans la police manuscrite :
 * le moment où la page parle à la première personne.
 */
function mg_quote( $text ) {
	return '<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">' . "\n"
		. '<!-- wp:paragraph {"className":"pull-quote"} -->' . "\n"
		. '<p class="pull-quote">' . $text . '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Rangée de pastilles manuscrites : des mots-clés posés côte à côte plutôt
 * qu'une liste à puces de plus.
 */
function mg_tags( array $items ) {
	return '<!-- wp:html -->' . "\n"
		. '<ul class="tags" data-reveal><li>' . implode( '</li><li>', $items ) . '</li></ul>' . "\n"
		. '<!-- /wp:html -->';
}

/**
 * Bande éditoriale : titre de section à gauche, contenu à droite, sur toute la
 * largeur large. Sans elle, ces pages n'étaient qu'un ruban de 760 px au milieu
 * d'un écran de 1440, titres et textes empilés au centre.
 * Sous 1000 px la grille retombe d'elle-même en une colonne.
 */
function mg_band( $title, $inner ) {
	return '<!-- wp:group {"align":"wide","className":"band","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide band">' . "\n"
		. '<!-- wp:group {"className":"band__head","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group band__head">' . "\n" . mg_h2( $title ) . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->' . "\n"
		. '<!-- wp:group {"className":"band__body","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group band__body">' . "\n" . $inner . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->' . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Bloc de texte courant, calé sur le même bord gauche que les bandes et les
 * bandeaux d'images. Même raison que pour le chapeau : hors d'un groupe en flux
 * normal, WordPress recentre chaque enfant d'une mise en page contrainte.
 */
function mg_prose( $inner ) {
	return '<!-- wp:group {"align":"wide","className":"prose","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide prose">' . "\n" . $inner . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

function mg_p( $text, $class = '' ) {
	$attrs = $class ? ' {"className":"' . $class . '"}' : '';
	$cls   = $class ? ' class="' . $class . '"' : '';
	return '<!-- wp:paragraph' . $attrs . ' -->' . "\n" . '<p' . $cls . '>' . $text . '</p>' . "\n" . '<!-- /wp:paragraph -->';
}

/**
 * Liste numérotée « comment ça se passe ».
 */
function mg_steps( array $items ) {
	$out = '<!-- wp:list {"ordered":true,"className":"steps"} -->' . "\n" . '<ol class="wp-block-list steps">';
	foreach ( $items as $item ) {
		$out .= '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->';
	}
	return $out . '</ol>' . "\n" . '<!-- /wp:list -->';
}

function mg_bullets( array $items ) {
	$out = '<!-- wp:list -->' . "\n" . '<ul class="wp-block-list">';
	foreach ( $items as $item ) {
		$out .= '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->';
	}
	return $out . '</ul>' . "\n" . '<!-- /wp:list -->';
}

/**
 * Encadré rassurant (tarifs, entente…).
 */
function mg_note( $inner ) {
	return '<!-- wp:group {"className":"note","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group note">' . "\n" . $inner . "\n" . '</div>' . "\n"
		. '<!-- /wp:group -->';
}

/**
 * Formulaire, sur la largeur large : ses champs passent alors sur deux colonnes.
 */
function mg_form( $type ) {
	// Ancre : les bandeaux d'ouverture pointent dessus (« Parler de votre
	// projet »), pour ne pas obliger à faire défiler toute la page.
	return '<!-- wp:group {"align":"wide","anchor":"formulaire","className":"form-band","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide form-band" id="formulaire">' . "\n"
		. '<!-- wp:morgane/form {"type":"' . $type . '"} /-->' . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

function mg_sep() {
	return '<!-- wp:separator {"className":"rule"} --><hr class="wp-block-separator rule"/><!-- /wp:separator -->';
}


/**
 * Contenu d'une page. Les {{clés}} sont remplacées par les URL de la langue.
 */
function mg_content( $key, $lang ) {
	$fr = ( 'fr' === $lang );

	/*
	 * Le lien courriel vient de theme/inc/contact.php, jamais d'une adresse
	 * recopiée : quatre pages l'affichent, et une adresse changée en oubliant
	 * l'une des quatre, c'est un client qui écrit dans le vide.
	 */
	$mg_mail = '';
	if ( function_exists( 'morgane_contact_info' ) ) {
		$c       = morgane_contact_info();
		$mg_mail = isset( $c['email'] ) ? $c['email'] : '';
	}
	$mg_lien_mail = $mg_mail
		? '<a href="mailto:' . esc_attr( $mg_mail ) . '">' . esc_html( $mg_mail ) . '</a>'
		: '';

	switch ( $key ) {

		/* ---------------------------------------------------------- Accueil */
		case 'home':
			$words = $fr
				? array( 'Illustration', 'Peinture', 'Murales', 'Montréal', 'Gouache', 'Sur mesure' )
				: array( 'Illustration', 'Painting', 'Murals', 'Montreal', 'Gouache', 'Custom work' );
			$marquee = '<span>' . implode( '</span><span>', array_merge( $words, $words ) ) . '</span>';

			return implode( "\n\n", array(

				// --- Hero : fond « aurore », formes flottantes, titre animé ---
				'<!-- wp:group {"tagName":"section","align":"full","className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"}},"dimensions":{"minHeight":"84vh"}},"layout":{"type":"constrained","contentSize":"880px"}} -->' . "\n"
					. '<section class="wp-block-group alignfull hero" style="min-height:84vh;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">' . "\n"

					. '<!-- wp:html -->' . "\n"
					. '<div class="blob blob--rose" style="width:min(32vw,340px);aspect-ratio:1;top:10%;left:-5%;"></div>' . "\n"
					. '<div class="blob blob--sage" style="width:min(26vw,280px);aspect-ratio:1;bottom:12%;right:-3%;"></div>' . "\n"
					// Deux œuvres flottantes, comme sur « L'atelier ». Pas de
					// `data-reveal` ici : la boucle REVEAL d'app.js saute tout ce
					// qui est dans le hero, qui a sa propre ouverture. Elles sont
					// jointes à cette ouverture, dans `heroRest`.
					// `eager` : elles sont au-dessus de la ligne de flottaison.
					. '<figure class="art-float art-float--left">'
					. mg_photo(
						7,
						$fr
							? 'Illustration à la gouache : un miroir ancien au sommet d’un escalier de pierre, dans une forêt enchantée'
							: 'Gouache illustration: an antique mirror at the top of a stone staircase, in an enchanted forest',
						'medium_large',
						true,
						'210px'
					)
					. '</figure>' . "\n"
					. '<figure class="art-float art-float--right">'
					. mg_photo(
						14,
						$fr
							? 'Illustration jeunesse : un ourson grimpe à une échelle pour allumer un réverbère dans une ville enneigée'
							: 'Children’s illustration: a bear cub climbing a ladder to light a streetlamp in a snowy town',
						'medium_large',
						true,
						'210px'
					)
					. '</figure>' . "\n"
					. '<!-- /wp:html -->' . "\n"

					. '<!-- wp:group {"className":"hero__inner","layout":{"type":"default"}} -->' . "\n"
					. '<div class="wp-block-group hero__inner">' . "\n"

					. '<!-- wp:paragraph {"className":"hero__eyebrow"} -->' . "\n"
					. '<p class="hero__eyebrow">'
					. ( $fr ? 'illustration · peinture · murales — Montréal' : 'illustration · painting · murals — Montreal' )
					. '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"

					. '<!-- wp:heading {"textAlign":"center","level":1} -->' . "\n"
					. '<h1 class="wp-block-heading has-text-align-center">'
					. ( $fr
						? 'Des œuvres douces et féeriques pour celles et ceux qui aiment la poésie'
						: 'Soft and enchanted artworks for those who love poetry' )
					. '</h1>' . "\n" . '<!-- /wp:heading -->' . "\n"

					. '<!-- wp:paragraph {"align":"center","className":"hero__lede","fontSize":"large"} -->' . "\n"
					. '<p class="hero__lede has-text-align-center has-large-font-size">'
					. ( $fr
						? 'Illustratrice, artiste peintre et muraliste basée à Montréal.'
						: 'Illustrator, painter and mural artist based in Montreal.' )
					. '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"

					. mg_buttons(
						array(
							array( '{{shop}}', $fr ? 'Découvrir les œuvres' : 'Discover the artworks' ),
							array( '{{hub}}', $fr ? 'Travailler ensemble' : 'Work together' ),
						),
						true
					) . "\n"

					. '<!-- wp:html --><div class="scroll-cue" aria-hidden="true"></div><!-- /wp:html -->' . "\n"
					. '</div>' . "\n" . '<!-- /wp:group -->' . "\n"
					. '</section>' . "\n" . '<!-- /wp:group -->',

				// --- Bandeau couleur défilant ---
				'<!-- wp:html -->' . "\n"
					. '<div class="color-band alignfull" aria-hidden="true"><div class="marquee">' . $marquee . '</div></div>' . "\n"
					. '<!-- /wp:html -->',

				// --- Trois portes d'entrée ---
				'<!-- wp:group {"tagName":"section","align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->' . "\n"
					. '<section class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60)">' . "\n"
					. mg_head(
						$fr ? 'Par où commencer' : 'Where to start',
						$fr ? 'Trois chemins' : 'Three ways in'
					) . "\n"
					. mg_cards(
						$fr
							? array(
								array( '{{shop}}', 'Boutique', 'Prints et originaux', 2, 'Ensemble de tirages d’art des illustrations à la gouache de Morgane Cachia' ),
								array( '{{hub}}', 'Commissions et collaborations', 'Une œuvre pensée pour vous', 3, 'Peinture d’un épouvantail coiffé d’un chapeau fleuri dans un champ de tournesols, réalisée sur commande' ),
								array( '{{murals}}', 'Murales', 'Transformer un lieu', 4, 'Morgane Cachia devant sa murale : un enfant en imperméable jaune qui creuse la terre' ),
							)
							: array(
								array( '{{shop}}', 'Shop', 'Prints and originals', 2, 'A spread of fine art prints of Morgane Cachia’s gouache illustrations' ),
								array( '{{hub}}', 'Commissions and collaborations', 'A piece made for you', 3, 'Painting of a scarecrow in a flowered hat standing in a field of sunflowers, made on commission' ),
								array( '{{murals}}', 'Murals', 'Transforming a space', 4, 'Morgane Cachia in front of her mural: a child in a yellow raincoat digging in the soil' ),
							)
					) . "\n"
					. '</section>' . "\n" . '<!-- /wp:group -->',

				// --- Sélection d'œuvres ---
				'<!-- wp:group {"tagName":"section","align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->' . "\n"
					. '<section class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
					. mg_head(
						$fr ? 'À l\'affiche' : 'On show',
						$fr ? 'Œuvres disponibles' : 'Available artworks'
					) . "\n"
					. '<!-- wp:morgane/artworks {"limit":8} /-->' . "\n"
					. mg_buttons( array( array( '{{shop}}', $fr ? 'Voir toute la boutique' : 'Browse the shop' ) ), true ) . "\n"
					. '</section>' . "\n" . '<!-- /wp:group -->',

				// --- L'atelier ---
				// Plus de fond crème : la section reprend la mise en scène « aurore »
				// du hero, qui se pose sur le fond de page. Un aplat par-dessus
				// masquerait le dégradé.
				'<!-- wp:group {"tagName":"section","align":"full","className":"atelier","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->' . "\n"
					. '<section class="wp-block-group alignfull atelier" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
					// Formes flottantes et deux œuvres, comme sur l'accueil. Les
					// œuvres portent `data-reveal` : elles apparaissent au défilement
					// par la règle déjà en place dans app.js.
					. '<!-- wp:html -->' . "\n"
					. '<div class="blob blob--rose" style="width:min(30vw,320px);aspect-ratio:1;top:8%;left:-6%;"></div>' . "\n"
					. '<div class="blob blob--sage" style="width:min(24vw,260px);aspect-ratio:1;bottom:10%;right:-4%;"></div>' . "\n"
					. '<figure class="art-float art-float--left" data-reveal>'
					. mg_photo(
						9,
						$fr
							? 'Illustration à la gouache : une fée assise au cœur d’une pivoine rose immense'
							: 'Gouache illustration: a fairy seated in the heart of a huge pink peony',
						'medium_large',
						false,
						'210px'
					)
					. '</figure>' . "\n"
					. '<figure class="art-float art-float--right" data-reveal>'
					. mg_photo(
						6,
						$fr
							? 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée'
							: 'Gouache illustration: a long-haired woman holding a glowing moon in a blue-tinged winter forest',
						'medium_large',
						false,
						'210px'
					)
					. '</figure>' . "\n"
					. '<!-- /wp:html -->' . "\n"
					. '<!-- wp:paragraph {"align":"center","className":"eyebrow"} --><p class="has-text-align-center eyebrow">'
					. ( $fr ? 'L\'atelier' : 'The studio' )
					. '</p><!-- /wp:paragraph -->' . "\n"
					. '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">'
					. ( $fr ? 'Entre magie et mélancolie' : 'Between magic and melancholy' )
					. '</h2><!-- /wp:heading -->' . "\n"
					. '<!-- wp:paragraph {"align":"center","fontSize":"large","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->' . "\n"
					. '<p class="has-text-align-center has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">'
					. ( $fr
						? 'J’aime peindre des personnages et faire apparaître leurs histoires et leurs âmes, dans mes illustrations à la gouache comme dans mes murales.'
						: 'I love painting characters and revealing their stories, their souls, in both my gouache illustrations and my murals.' )
					. '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
					. mg_buttons( array( array( '{{about}}', $fr ? 'Rencontrer Morgane' : 'Meet Morgane' ) ), true ) . "\n"
					. '</section>' . "\n" . '<!-- /wp:group -->',
			) );

		/* --------------------------------------------------------- À propos */
		case 'about':
			$photo_alt = $fr
				? 'Morgane Cachia, illustratrice et artiste peintre à Montréal, assise dans l’herbe avec une de ses peintures'
				: 'Morgane Cachia, illustrator and painter based in Montreal, sitting in the grass with one of her paintings';

			return implode( "\n\n", array(

				mg_hero(
					$fr ? 'illustratrice · peintre · muraliste — Montréal' : 'illustrator · painter · mural artist — Montreal',
					'Morgane Cachia',
					$fr
						? array(
							'Bonjour ! Je suis Morgane. Je crée des œuvres oniriques, traversées par des univers enchantés, entre magie et mélancolie.',
							'J’aime peindre des personnages et faire apparaître leurs histoires et leurs âmes, dans mes illustrations à la gouache comme dans mes murales.',
						)
						: array(
							'Hello! I’m Morgane. I create dreamlike works inhabited by enchanted worlds, somewhere between magic and melancholy.',
							'I love painting characters and revealing their stories, their souls, in both my gouache illustrations and my murals.',
						),
					array(
						array( '{{portfolio}}', $fr ? 'Voir les portfolios' : 'View the portfolios' ),
						array( '{{hub}}', $fr ? 'Travailler ensemble' : 'Work together' ),
					),
					array( 5, $photo_alt )
				),

				// Les mots du bandeau défilant sont ceux de Morgane, pas des
				// mots-clés : ils reprennent les thèmes cités juste en dessous.
				mg_marquee(
					$fr
						? array( 'Gouache', 'Murales', 'Ghibli', 'Enfance', 'Souvenir', 'Nature', 'Magie discrète' )
						: array( 'Gouache', 'Murals', 'Ghibli', 'Childhood', 'Memory', 'Nature', 'Quiet magic' )
				),

				mg_atelier(
					$fr ? 'Mon univers' : 'My world',
					$fr ? 'Ce qui m’inspire' : 'What inspires me',
					$fr
						? array( 'Mon inspiration vient de plusieurs formes d’art et de récit : j’aime lire, je suis passionnée de musique, et l’univers des films du studio Ghibli m’accompagne tous les jours. Les thèmes qui m’habitent :' )
						: array( 'My inspiration comes from many forms of art and storytelling: I love reading, I’m passionate about music, and the world of Studio Ghibli films has been with me for years. The themes that resonate most deeply with me:' ),
					$fr
						? array(
							array( 23, 'Illustration à la gouache : une table d’alchimiste éclairée à la bougie, fioles fumantes et vieux livres' ),
							array( 10, 'Illustration à la gouache : une fée éclaire une fleur de lierre au creux de sa main, dans un jardin de roses' ),
						)
						: array(
							array( 23, 'Gouache illustration: an alchemist’s table lit by candlelight, steaming vials and old books' ),
							array( 10, 'Gouache illustration: a fairy lighting an ivy flower in the palm of her hand, in a rose garden' ),
						),
					mg_tags(
						$fr
							? array( 'l’enfance', 'le souvenir', 'la beauté de la nature', 'la magie discrète' )
							: array( 'childhood', 'memory', 'the beauty of nature', 'quiet magic' )
					)
				),

				mg_quote(
					$fr
						? '« C’est un peu de cette magie que je cherche avant tout à transmettre à travers mon travail. »'
						: '“It is above all this sense of enchantment that I hope to convey through my work.”'
				),

				// Une œuvre de chaque univers, toutes en portrait autour de 3:4 :
				// le bandeau défilant garde les proportions d'origine, un format
				// beaucoup plus étroit y ferait une image deux fois plus haute
				// que ses voisines.
				mg_section(
					$fr ? 'En images' : 'In pictures',
					$fr ? 'Quelques œuvres' : 'A few works',
					mg_gallery(
							$fr
								? array(
									array( 6, 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée' ),
									array( 11, 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes' ),
									array( 22, 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres' ),
									array( 19, 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée' ),
									array( 9, 'Illustration à la gouache : une fée assise au cœur d’une pivoine rose immense' ),
									array( 21, 'Murale : un enfant en imperméable jaune déterre une graine lumineuse' ),
								)
								: array(
									array( 6, 'Gouache illustration: a woman with long hair holds a glowing moon in a blue winter forest' ),
									array( 11, 'Children’s illustration: a fawn and a puppy meet in a snowy forest of violet tones' ),
									array( 22, 'Mural “The tree of knowledge”: a flowering tree whose branches carry letters' ),
									array( 19, 'Ink drawing: still life with a cello, sheet music and a bouquet on a draped table' ),
									array( 9, 'Gouache illustration: a fairy seated in the heart of an immense pink peony' ),
									array( 21, 'Mural: a child in a yellow raincoat unearthing a glowing seed' ),
								),
							$lang
					) . "\n"
					. mg_buttons( array( array( '{{portfolio}}', $fr ? 'Voir les portfolios' : 'View the portfolios' ) ), true )
				),

				mg_prose(
					mg_sep() . "\n\n"
					. mg_p(
						$fr
							? 'Écrivez-moi : ' . $mg_lien_mail
							: 'Write to me: ' . $mg_lien_mail
					) . "\n\n"
					. mg_buttons( array(
						array( '{{hub}}', $fr ? 'Travailler ensemble' : 'Work together' ),
						array( '{{shop}}', $fr ? 'Voir la boutique' : 'Browse the shop' ),
					) )
				),
			) );

		/* --------------------------------------------------------- Portfolio */
		case 'portfolio':
			return implode( "\n\n", array(

				mg_hero(
					$fr ? 'Le travail' : 'The work',
					'Portfolio',
					$fr
						? array( 'Quatre ensembles de travaux : l’illustration jeunesse, l’illustration, le dessin et les murales.' )
						: array( 'Four bodies of work: children’s illustration, illustration, drawing and murals.' ),
					array(
						array( '{{commissions}}', $fr ? 'Commander une œuvre' : 'Commission a piece' ),
						array( '{{shop}}', $fr ? 'Voir la boutique' : 'Browse the shop' ),
					),
					array(),
					$fr
						? array(
							array( 12, 'Illustration jeunesse : une souris arrose des primevères devant sa maison creusée dans un arbre' ),
							array( 19, 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée' ),
						)
						: array(
							array( 12, 'Children’s illustration: a mouse waters primroses outside her home carved into a tree' ),
							array( 19, 'Ink drawing: still life with a cello, sheet music and a bouquet on a draped table' ),
						)
				),

				mg_marquee(
					$fr
						? array( 'Illustration jeunesse', 'Illustration', 'Dessin', 'Murales' )
						: array( 'Children’s illustration', 'Illustration', 'Drawing', 'Murals' )
				),

				mg_section(
					$fr ? 'Quatre univers' : 'Four worlds',
					$fr ? 'Choisir un portfolio' : 'Choose a portfolio',
					mg_cards(
						$fr
							? array(
								array( '{{pf_youth}}', 'Illustration jeunesse', '', 11, 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes' ),
								array( '{{pf_illus}}', 'Illustration', '', 6, 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée' ),
								array( '{{pf_draw}}', 'Dessin', '', 16, 'Dessin à l’encre : un vieil homme barbu écrit à la plume dans une bibliothèque sombre' ),
								array( '{{pf_murals}}', 'Murales', '', 20, 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres' ),
							)
							: array(
								array( '{{pf_youth}}', 'Children’s illustration', '', 11, 'Children’s illustration: a fawn and a puppy meet in a snowy forest of violet tones' ),
								array( '{{pf_illus}}', 'Illustration', '', 6, 'Gouache illustration: a woman with long hair holds a glowing moon in a blue winter forest' ),
								array( '{{pf_draw}}', 'Drawing', '', 16, 'Ink drawing: an old bearded man writing with a quill in a dark library' ),
								array( '{{pf_murals}}', 'Murals', '', 20, 'Mural: a child in a blue raincoat smelling a flower from which letters escape' ),
							)
					)
				),

				mg_prose(
					mg_sep() . "\n\n"
					. mg_p(
						$fr
							? 'Une œuvre déjà disponible, ou une pièce imaginée pour vous ?'
							: 'A piece that is already available, or one imagined for you?'
					) . "\n\n"
					. mg_buttons( array(
						array( '{{shop}}', $fr ? 'Voir la boutique' : 'Browse the shop' ),
						array( '{{hub}}', $fr ? 'Travailler ensemble' : 'Work together' ),
					) )
				),
			) );

		case 'pf_youth':
		case 'pf_illus':
		case 'pf_draw':
			$series = array(
				'pf_youth' => $fr
					? array(
						array( 11, 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes' ),
						array( 12, 'Illustration jeunesse : une souris arrose des primevères devant sa maison creusée dans un arbre' ),
						array( 13, 'Illustration jeunesse : une petite fille en robe rose serre son ourson pendant un pique-nique' ),
						array( 14, 'Illustration jeunesse : un ourson grimpe à une échelle pour allumer un réverbère dans une ville enneigée' ),
						array( 15, 'Illustration jeunesse : une fillette plante une pousse dans un jardin de roses, observée par une oie blanche' ),
					)
					: array(
						array( 11, 'Children’s illustration: a fawn and a puppy meet in a snowy forest of violet tones' ),
						array( 12, 'Children’s illustration: a mouse waters primroses outside her home carved into a tree' ),
						array( 13, 'Children’s illustration: a little girl in a pink dress holds her teddy bear during a picnic' ),
						array( 14, 'Children’s illustration: a teddy bear climbs a ladder to light a street lamp in a snowy town' ),
						array( 15, 'Children’s illustration: a little girl plants a seedling in a rose garden, watched by a white goose' ),
					),
				'pf_illus' => $fr
					? array(
						array( 6, 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée' ),
						array( 7, 'Illustration à la gouache : un miroir ancien au sommet d’un escalier de pierre, dans une forêt enchantée' ),
						array( 8, 'Illustration à la gouache : une femme aux cheveux dorés flottants dans une bibliothèque envahie de lierre' ),
						array( 9, 'Illustration à la gouache : une fée assise au cœur d’une pivoine rose immense' ),
						array( 10, 'Illustration à la gouache : une fée éclaire une fleur de lierre au creux de sa main, dans un jardin de roses' ),
					)
					: array(
						array( 6, 'Gouache illustration: a woman with long hair holds a glowing moon in a blue winter forest' ),
						array( 7, 'Gouache illustration: an antique mirror at the top of a stone stairway in an enchanted forest' ),
						array( 8, 'Gouache illustration: a woman with flowing golden hair in a library overgrown with ivy' ),
						array( 9, 'Gouache illustration: a fairy seated in the heart of an immense pink peony' ),
						array( 10, 'Gouache illustration: a fairy lighting an ivy flower in the palm of her hand, in a rose garden' ),
					),
				'pf_draw'  => $fr
					? array(
						array( 16, 'Dessin à l’encre : un vieil homme barbu écrit à la plume dans une bibliothèque sombre' ),
						array( 17, 'Dessin à l’encre : une cascade dévale une paroi de montagne sous des nuages en volutes' ),
						array( 18, 'Dessin à l’encre : une fillette en bonnet souffle sur un pissenlit' ),
						array( 19, 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée' ),
					)
					: array(
						array( 16, 'Ink drawing: an old bearded man writing with a quill in a dark library' ),
						array( 17, 'Ink drawing: a waterfall tumbling down a mountain face beneath swirling clouds' ),
						array( 18, 'Ink drawing: a girl in a knitted hat blowing on a dandelion' ),
						array( 19, 'Ink drawing: still life with a cello, sheet music and a bouquet on a draped table' ),
					),
			);

			// Une œuvre de la série en ouverture, en portrait proche de 3:4 :
			// le cadre du bandeau garde ces proportions, un format plus étroit
			// s'y ferait recadrer.
			$heroes = array(
				'pf_youth' => array(
					'fr' => array( 'Illustration jeunesse', 11, 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes' ),
					'en' => array( 'Children’s illustration', 11, 'Children’s illustration: a fawn and a puppy meet in a snowy forest of violet tones' ),
				),
				'pf_illus' => array(
					'fr' => array( 'Illustration', 8, 'Illustration à la gouache : une femme aux cheveux dorés flottants dans une bibliothèque envahie de lierre' ),
					'en' => array( 'Illustration', 8, 'Gouache illustration: a woman with flowing golden hair in a library overgrown with ivy' ),
				),
				'pf_draw'  => array(
					'fr' => array( 'Dessin', 17, 'Dessin à l’encre : une cascade dévale une paroi de montagne sous des nuages en volutes' ),
					'en' => array( 'Drawing', 17, 'Ink drawing: a waterfall tumbling down a mountain face beneath swirling clouds' ),
				),
			);
			$hero = $heroes[ $key ][ $lang ];

			return implode( "\n\n", array(
				mg_hero(
					'Portfolio',
					$hero[0],
					array(),
					array(
						array( '{{portfolio}}', $fr ? 'Tous les portfolios' : 'All the portfolios' ),
						array( '{{commissions}}', $fr ? 'Commander une illustration' : 'Commission an illustration' ),
					),
					array( $hero[1], $hero[2] )
				),
				mg_section(
					$fr ? 'La série' : 'The series',
					$fr ? 'Les œuvres' : 'The works',
					mg_gallery( $series[ $key ], $lang )
				),
				mg_prose(
					mg_sep() . "\n\n"
					. mg_p(
						$fr
							? 'Une illustration sur mesure, ou une œuvre déjà disponible ?'
							: 'A custom illustration, or a piece that is already available?'
					) . "\n\n"
					. mg_buttons( array(
						array( '{{commissions}}', $fr ? 'Commander une illustration' : 'Commission an illustration' ),
						array( '{{shop}}', $fr ? 'Voir la boutique' : 'Browse the shop' ),
					) )
				),
			) );

		case 'pf_murals':
			return implode( "\n\n", array(
				mg_hero(
					'Portfolio',
					$fr ? 'Murales' : 'Murals',
					array(),
					array(
						array( '{{portfolio}}', $fr ? 'Tous les portfolios' : 'All the portfolios' ),
						array( '{{murals}}', $fr ? 'Projet de murale' : 'Mural project' ),
					),
					$fr
						? array( 22, 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres' )
						: array( 22, 'Mural “The tree of knowledge”: a flowering tree whose branches carry letters' )
				),
				mg_section(
					$fr ? 'La série' : 'The series',
					$fr ? 'Les murales' : 'The murals',
					mg_gallery(
						$fr
							? array(
								array( 20, 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres' ),
								array( 21, 'Murale : un enfant en imperméable jaune déterre une graine lumineuse' ),
								array( 22, 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres' ),
							)
							: array(
								array( 20, 'Mural: a child in a blue raincoat smelling a flower from which letters escape' ),
								array( 21, 'Mural: a child in a yellow raincoat unearthing a glowing seed' ),
								array( 22, 'Mural “The tree of knowledge”: a flowering tree whose branches carry letters' ),
							),
						$lang
					)
				),
				mg_prose(
					mg_sep() . "\n\n"
					. mg_p( $fr ? 'Un mur à transformer ?' : 'A wall to transform?' ) . "\n\n"
					. mg_buttons( array(
						array( '{{murals}}', $fr ? 'Projet de murale' : 'Mural project' ),
					) )
				),
			) );

		/* ----------------------------------------------------------- Murales */
		case 'murals':
			// La dernière phrase de la présentation est sortie du paragraphe pour
			// devenir la citation en grand : c'est celle qui dit le mieux ce que
			// Morgane cherche dans une murale.
			$intro = $fr
				? 'J’aime créer des murales qui transforment un espace en y apportant une atmosphère, une présence et une histoire. Je suis particulièrement attirée par les projets qui laissent place à l’imaginaire, à la poésie et à une forme de douceur visuelle.'
				: 'I love creating murals that transform a space by bringing in atmosphere, presence, and a sense of story. I’m especially drawn to projects that leave room for imagination, poetry, and a certain visual softness.';

			$intro_quote = $fr
				? '« J’aime composer des images qui dialoguent avec le lieu, tout en y faisant entrer un peu de rêve et de magie. »'
				: '“I enjoy composing images that respond to a place while bringing in a touch of dream and magic.”';

			$lead = $fr
				? 'Vous avez un projet de murale en tête ? J’aime imaginer des fresques qui transforment un espace et lui donnent une atmosphère unique. Que ce soit pour un lieu privé, un commerce, un espace culturel ou un projet plus personnel, nous commençons toujours par un échange afin de définir ensemble l’ambiance, le sujet, les dimensions, le délai et le budget.'
				: 'Do you have a mural project in mind? I love imagining murals that transform a space and give it a unique atmosphere. Whether it’s for a private home, a business, a cultural space, or a more personal project, we always begin with a conversation to define the mood, subject, dimensions, timeline, and budget together.';

			$steps = $fr
				? array(
					'Vous m’écrivez avec votre idée de murale.',
					'Nous discutons ensemble du lieu, des dimensions, du sujet, de l’ambiance souhaitée, du délai et du budget.',
					'Une entente de commande est validée, puis un acompte de 50&nbsp;% est demandé pour réserver le projet et lancer la phase de préparation.',
					'Je commence la recherche et la conception des esquisses.',
					'Je vous propose des croquis pour validation. Jusqu’à trois allers-retours sont inclus à cette étape.',
					'Une fois le croquis final approuvé, je réalise la murale.',
					'Lorsque le projet est terminé, des ajustements mineurs peuvent être effectués si nécessaire.',
					'La facture finale est réglée avant ou à la fin du projet, selon l’entente convenue.',
				)
				: array(
					'You get in touch with your mural idea.',
					'We discuss the location, dimensions, subject, desired mood, timeline, and budget.',
					'A commission agreement is confirmed, and a 50% deposit is requested to reserve the project and begin the preparation phase.',
					'I begin the research and sketching process.',
					'I send you sketches for approval. Up to three rounds of revisions are included at this stage.',
					'Once the final sketch is approved, I begin the mural.',
					'When the project is complete, minor adjustments can be made if needed.',
					'The final invoice is paid before or at the end of the project, according to the agreed terms.',
				);

			$pricing = $fr
				? array(
					'Mes tarifs sont établis en fonction de plusieurs éléments : la taille de la surface à peindre, la complexité du projet, le niveau de détail, les matériaux nécessaires, le temps de préparation et le temps de réalisation sur place. Chaque murale étant unique, les délais varient également selon l’ampleur du projet et ma disponibilité au moment de la demande.',
					'Afin de préparer une proposition juste et adaptée, je demande quelques informations essentielles dès le premier contact : les dimensions approximatives de la surface, le lieu, les dates envisagées pour la réalisation, ainsi qu’une idée du thème ou de l’ambiance souhaitée.',
				)
				: array(
					'My pricing is based on several factors: the size of the surface to be painted, the complexity of the project, the level of detail, the materials required, the preparation time, and the time needed to complete the mural on site. Since each mural is unique, timelines also vary depending on the scope of the project and my availability at the time of the request.',
					'To prepare an accurate and tailored proposal, I ask for a few essential details at the first contact stage: the approximate dimensions of the surface, the location, the intended dates for the mural, and an idea of the theme or atmosphere you have in mind.',
				);

			$agreement = $fr
				? 'Pour assurer un cadre clair et serein, chaque murale fait l’objet d’une entente écrite précisant le sujet du projet, le prix, l’acompte, les délais, les retours inclus et les conditions de réalisation. Cette étape permet de poser les bases du projet dès le départ et d’avancer avec fluidité jusqu’à la réalisation finale.'
				: 'To ensure a clear and reassuring process, each mural is covered by a written agreement outlining the project subject, price, deposit, timeline, included revisions, and production terms. This helps establish the project from the start and allows it to unfold smoothly through to completion.';

			return implode( "\n\n", array(

				mg_hero(
					$fr ? 'Sur les murs' : 'On walls',
					$fr ? 'Murales' : 'Murals',
					array( $intro ),
					array(
						array( '#formulaire', $fr ? 'Parler de votre projet' : 'Tell me about your project' ),
						array( '{{pf_murals}}', $fr ? 'Voir les murales' : 'See the murals' ),
					),
					$fr
						? array( 21, 'Murale : un enfant en imperméable jaune déterre une graine lumineuse' )
						: array( 21, 'Mural: a child in a yellow raincoat unearthing a glowing seed' )
				),

				// Deux murales seulement : le portfolio Murales montre la suite,
				// d'où le bouton juste dessous.
				mg_section(
					$fr ? 'Réalisations' : 'Completed work',
					$fr ? 'Deux murales' : 'Two murals',
					mg_gallery(
							$fr
								? array(
									array( 22, 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres' ),
									array( 20, 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres' ),
								)
								: array(
									array( 22, 'Mural “The tree of knowledge”: a flowering tree whose branches carry letters' ),
									array( 20, 'Mural: a child in a blue raincoat smelling a flower from which letters escape' ),
								),
							$lang
					) . "\n"
					. mg_buttons( array( array( '{{pf_murals}}', $fr ? 'Pour en voir plus' : 'To see more' ) ), true )
				),

				mg_quote( $intro_quote ),

				mg_atelier(
					$fr ? 'Votre mur' : 'Your wall',
					$fr ? 'Un projet de murale ?' : 'A mural project?',
					array( $lead ),
					$fr
						? array(
							array( 20, 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres' ),
							array( 22, 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres' ),
						)
						: array(
							array( 20, 'Mural: a child in a blue raincoat smelling a flower from which letters escape' ),
							array( 22, 'Mural “The tree of knowledge”: a flowering tree whose branches carry letters' ),
						)
				),

				mg_band( $fr ? 'Comment ça se passe ?' : 'How it works', mg_steps( $steps ) ),
				mg_band(
					$fr ? 'Tarifs et délais' : 'Pricing and timelines',
					mg_note( mg_p( $pricing[0] ) . "\n\n" . mg_p( $pricing[1] ) )
				),
				mg_band(
					$fr ? 'Entente de projet' : 'Project agreement',
					mg_note( mg_p( $agreement ) )
				),
				mg_form( 'mural' ),
			) );

		/* --------------------------------- Hub commissions / collaborations */
		case 'hub':
			return implode( "\n\n", array(

				mg_hero(
					$fr ? 'Travailler ensemble' : 'Work together',
					$fr ? 'Commissions et collaborations' : 'Commissions and collaborations',
					$fr
						? array( 'Deux façons de travailler ensemble : une œuvre commandée pour vous, ou un projet mené avec une marque, un lieu ou une équipe.' )
						: array( 'Two ways of working together: a piece commissioned for you, or a project developed with a brand, a venue or a team.' ),
					array(
						array( '{{commissions}}', 'Commissions' ),
						array( '{{collaborations}}', 'Collaborations' ),
					),
					array(),
					$fr
						? array(
							array( 6, 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée' ),
							array( 21, 'Murale : un enfant en imperméable jaune déterre une graine lumineuse' ),
						)
						: array(
							array( 6, 'Gouache illustration: a woman with long hair holds a glowing moon in a blue winter forest' ),
							array( 21, 'Mural: a child in a yellow raincoat unearthing a glowing seed' ),
						)
				),

				mg_section(
					$fr ? 'Par où commencer' : 'Where to start',
					$fr ? 'Deux chemins' : 'Two ways in',
					mg_cards(
						$fr
							? array(
								array( '{{commissions}}', 'Commissions', 'Une œuvre créée pour vous', 3, 'Peinture d’un épouvantail coiffé d’un chapeau fleuri dans un champ de tournesols, réalisée sur commande' ),
								array( '{{collaborations}}', 'Collaborations', 'Marques, lieux, institutions, créateurs', 23, 'Illustration à la gouache : une table d’alchimiste éclairée à la bougie, fioles fumantes et vieux livres' ),
							)
							: array(
								array( '{{commissions}}', 'Commissions', 'A piece created for you', 3, 'Painting of a scarecrow in a flowered hat standing in a field of sunflowers, made on commission' ),
								array( '{{collaborations}}', 'Collaborations', 'Brands, venues, institutions, creators', 23, 'Gouache illustration: an alchemist’s candlelit table with smoking flasks and old books' ),
							)
					)
				),

				mg_prose(
					mg_sep() . "\n\n"
					. mg_p(
						$fr
							? 'Une question avant de vous lancer ? ' . $mg_lien_mail
							: 'A question before you begin? ' . $mg_lien_mail
					) . "\n\n"
					. mg_buttons( array(
						array( '{{portfolio}}', $fr ? 'Voir les portfolios' : 'View the portfolios' ),
						array( '{{murals}}', $fr ? 'Projet de murale' : 'Mural project' ),
					) )
				),
			) );

		/* ------------------------------------------------------- Commissions */
		case 'commissions':
			$intro = $fr
				? 'Vous avez une idée de projet en tête ? J’aime beaucoup collaborer à partir de vos histoires, de vos envies et de vos images intérieures. Que ce soit pour une illustration à la gouache, à l’encre, à l’aquarelle, nous commençons toujours par une discussion afin de définir ensemble la nature du projet, le format, le délai et le budget.'
				: 'Have a project in mind? I love working from your stories, your ideas, and the images you carry within you. Whether it’s a gouache illustration, a watercolor painting, or ink artwork, we always begin with a conversation to define the scope, format, timeline, and budget.';

			$steps = $fr
				? array(
					'Vous me contactez avec votre idée ou votre projet.',
					'Nous échangeons ensemble afin de préciser le sujet, les dimensions, le médium, le délai et le tarif.',
					'Une entente de commande est validée, puis un acompte de 50&nbsp;% est demandé pour réserver votre place dans mon calendrier et lancer le travail.',
					'Je commence alors la phase de recherche et de croquis.',
					'Je vous propose des croquis pour validation. Jusqu’à trois allers-retours sont inclus à cette étape.',
					'Une fois le croquis final approuvé, je réalise l’œuvre.',
					'Lorsque l’œuvre est terminée, vous bénéficiez de deux retours mineurs inclus, afin d’ajuster certains détails si nécessaire.',
					'L’œuvre est ensuite emballée avec soin et envoyée par livraison suivie et sécurisée.',
					'Un certificat d’authenticité accompagne également chaque peinture originale.',
					'Le solde restant est réglé avant l’envoi final.',
				)
				: array(
					'You get in touch with your idea or project.',
					'We discuss the subject, dimensions, medium, timeline, and price together.',
					'A commission agreement is confirmed, and a 50% deposit is required to reserve your spot and begin the work.',
					'I then move into the research and sketching phase.',
					'I send you sketches for approval. Up to three rounds of revisions are included at this stage.',
					'Once the final sketch is approved, I begin the artwork.',
					'When the piece is finished, two minor revisions are included if small adjustments are needed.',
					'The artwork is then carefully packaged and shipped with tracked, secure delivery.',
					'A certificate of authenticity is included with every original painting.',
					'The remaining balance is due before final shipment.',
				);

			$pricing = $fr
				? array(
					'Mes tarifs sont établis en fonction de plusieurs éléments : la complexité du sujet, le format de l’œuvre, le médium utilisé, le niveau de détail, le temps de recherche nécessaire et le temps de réalisation. Chaque projet étant unique, les délais varient également selon son ampleur et selon ma disponibilité au moment de la commande.',
					'À titre indicatif, une illustration originale à la gouache au format 9 x 12 pouces commence à partir de 400 CAD, et peut varier entre 400 et 650 CAD selon la complexité du projet. Pour les demandes plus détaillées, comportant plusieurs personnages, un décor élaboré ou un rendu particulièrement minutieux, un tarif plus élevé peut être proposé.',
				)
				: array(
					'My pricing is based on several factors: the complexity of the subject, the size of the artwork, the medium used, the level of detail, the amount of research involved, and the time required to complete the piece. Since every project is unique, timelines also vary depending on the scope of the work and my availability at the time of the commission.',
					'As a guideline, an original 9 x 12 inch gouache illustration starts at 400 CAD and may range from 400 to 650 CAD depending on the complexity of the project. For more detailed requests involving multiple characters, an elaborate background, or a highly refined finish, a higher price may apply.',
				);

			$agreement = $fr
				? 'Afin d’assurer un cadre clair et serein pour tout le monde, chaque commission fait l’objet d’une entente écrite précisant le sujet du projet, le prix, l’acompte, les délais, le nombre de retours inclus et les conditions de livraison. Cette étape permet de définir les attentes dès le départ et de garantir un processus fluide du début à la fin.'
				: 'To keep the process clear and reassuring for everyone, each commission includes a written agreement outlining the subject of the project, the price, the deposit, the timeline, the number of revisions included, and the delivery terms. This helps set expectations from the start and ensures a smooth process from beginning to end.';

			return implode( "\n\n", array(

				mg_hero(
					$fr ? 'Une œuvre pour vous' : 'A piece for you',
					'Commissions',
					array( $intro ),
					array(
						array( '#formulaire', $fr ? 'Demander une commission' : 'Request a commission' ),
						array( '{{portfolio}}', $fr ? 'Voir les portfolios' : 'View the portfolios' ),
					),
					$fr
						? array( 3, 'Peinture d’un épouvantail coiffé d’un chapeau fleuri dans un champ de tournesols, réalisée sur commande' )
						: array( 3, 'Painting of a scarecrow in a flowered hat standing in a field of sunflowers, made on commission' )
				),

				mg_band( $fr ? 'Comment ça se passe ?' : 'How it works', mg_steps( $steps ) ),
				mg_band(
					$fr ? 'Tarifs et délais' : 'Pricing and timelines',
					mg_note( mg_p( $pricing[0] ) . "\n\n" . mg_p( $pricing[1] ) )
				),
				mg_band(
					$fr ? 'Entente de commande' : 'Commission agreement',
					mg_note( mg_p( $agreement ) )
				),
				mg_form( 'commission' ),
			) );

		/* ---------------------------------------------------- Collaborations */
		case 'collaborations':
			$body = $fr
				? array(
					'Je suis toujours ouverte à des projets de collaboration qui résonnent avec mon univers. Que vous soyez une marque, un lieu, une institution ou une créatrice ou un créateur, je serai heureuse d’échanger avec vous autour d’une idée, d’une image ou d’une direction artistique à développer ensemble.',
					'J’aime travailler sur des projets qui laissent place à la sensibilité, à la poésie et à l’imaginaire, que ce soit dans le cadre d’une illustration, d’une campagne visuelle, d’un objet, d’une publication ou d’une création sur mesure. Si vous avez une proposition en tête, je vous invite à me contacter en me donnant quelques détails sur votre projet, vos besoins et le calendrier envisagé.',
				)
				: array(
					'I am always open to collaborative projects that resonate with my world. Whether you are a brand, a venue, an institution, or a fellow creator, I would be delighted to exchange with you about an idea, an image, or an artistic direction to develop together.',
					'I enjoy working on projects that leave room for sensitivity, poetry, and imagination, whether through illustration, a visual campaign, an object, a publication, or a custom creation. If you have a project in mind, feel free to get in touch and share a few details about your idea, your needs, and your intended timeline.',
				);

			$asks = $fr
				? array(
					'une brève présentation de votre projet ;',
					'le type de collaboration envisagé ;',
					'les délais souhaités ;',
					'votre budget ou une fourchette budgétaire ;',
					'tout lien, document ou visuel utile pour mieux comprendre votre demande.',
				)
				: array(
					'a brief presentation of your project;',
					'the type of collaboration you have in mind;',
					'your desired timeline;',
					'your budget or budget range;',
					'any links, documents, or visuals that may help me better understand your request.',
				);

			return implode( "\n\n", array(

				// Photo en paysage : le cadre du bandeau passe en 4:3, sinon un
				// recadrage en 3:4 n'en garderait qu'une bande centrale.
				mg_hero(
					$fr ? 'À plusieurs' : 'Together',
					'Collaborations',
					array( $body[0] ),
					array(
						array( '#formulaire', $fr ? 'Proposer un projet' : 'Submit a project' ),
						array( '{{portfolio}}', $fr ? 'Voir les portfolios' : 'View the portfolios' ),
					),
					$fr
						? array( 23, 'Illustration à la gouache : une table d’alchimiste éclairée à la bougie, fioles fumantes et vieux livres', 'wide' )
						: array( 23, 'Gouache illustration: an alchemist’s table lit by candlelight, steaming vials and old books', 'wide' )
				),

				mg_atelier(
					$fr ? 'Ce que j’aime' : 'What I enjoy',
					$fr ? 'Des projets qui laissent place à l’imaginaire' : 'Projects that leave room for imagination',
					array( $body[1] ),
					$fr
						? array(
							array( 8, 'Illustration à la gouache : une femme aux cheveux dorés flottants dans une bibliothèque envahie de lierre' ),
							array( 19, 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée' ),
						)
						: array(
							array( 8, 'Gouache illustration: a woman with flowing golden hair in a library overgrown with ivy' ),
							array( 19, 'Ink drawing: still life with a cello, sheet music and a bouquet on a draped table' ),
						),
					// Les formes de collaboration citées dans le paragraphe
					// ci-dessus, posées en pastilles.
					mg_tags(
						$fr
							? array( 'illustration', 'campagne visuelle', 'objet', 'publication', 'création sur mesure' )
							: array( 'illustration', 'visual campaign', 'object', 'publication', 'custom creation' )
					)
				),

				mg_band(
					$fr ? 'Comment me proposer un projet' : 'How to submit a project',
					mg_p(
						$fr
							? 'Dans l’espace ci-dessous, merci de m’indiquer, si possible :'
							: 'If possible, please include:'
					) . "\n\n"
					. mg_bullets( $asks ) . "\n\n"
					. mg_p(
						$fr
							? 'Je prendrai le temps de lire votre message avec attention et de vous répondre dans les plus brefs délais.'
							: 'I will take the time to read your message carefully and get back to you as soon as possible.'
					)
				),
				mg_form( 'collaboration' ),
			) );

		/* ---------------------------------------------------------- Contact */
		case 'contact':
			$c     = function_exists( 'morgane_contact_info' ) ? morgane_contact_info() : array();
			$ville = isset( $c['city'][ $lang ] ) ? $c['city'][ $lang ] : 'Montréal';
			$lien  = $mg_lien_mail;

			return implode( "\n\n", array(
				mg_prose(
					mg_p(
						$fr
							? 'Pour une question, une commande passée, un projet ou simplement pour dire bonjour — le courriel est le plus direct.'
							: 'For a question, an existing order, a project, or simply to say hello — email is the most direct way.',
						'lede'
					) . "\n\n"
					. mg_p(
						$fr
							? 'Courriel : ' . $lien . '<br>Basée à ' . $ville . '.'
							: 'Email: ' . $lien . '<br>Based in ' . $ville . '.'
					) . "\n\n"
					. mg_p(
						$fr
							? 'Je réponds en général sous deux à trois jours ouvrables. Si vous n’avez rien reçu au bout d’une semaine, votre message s’est probablement perdu : réécrivez-moi sans hésiter.'
							: 'I usually reply within two to three business days. If you have heard nothing after a week, your message has most likely gone astray — please do write again.'
					)
				),
				mg_band(
					$fr ? 'Un projet en tête ?' : 'A project in mind?',
					mg_p(
						$fr
							? 'Pour une œuvre sur mesure, une collaboration ou une murale, les <a href="{{hub}}">formulaires dédiés</a> me font gagner un aller-retour : ils demandent d’emblée les dimensions, le lieu, les délais et le médium souhaité.'
							: 'For a custom piece, a collaboration or a mural, the <a href="{{hub}}">dedicated forms</a> save us a round trip: they ask up front about dimensions, location, timing and the medium you have in mind.'
					)
				),
				mg_band(
					$fr ? 'Une commande de la boutique' : 'A shop order',
					mg_p(
						$fr
							? 'Indiquez votre numéro de commande dans le message : il figure dans le courriel de confirmation. Les conditions de retour sont détaillées sur la page <a href="{{returns}}">Retours et remboursements</a>.'
							: 'Please include your order number — it appears in your confirmation email. Return conditions are set out on the <a href="{{returns}}">Returns and refunds</a> page.'
					)
				),
			) );

		/* ------------------------------------------ Retours, remboursements */
		case 'returns':
			$c    = function_exists( 'morgane_contact_info' ) ? morgane_contact_info() : array();
			$lien = $mg_lien_mail;

			return implode( "\n\n", array(
				mg_prose(
					mg_p(
						$fr
							? 'Les tirages peuvent être retournés, les originaux non. La raison est simple : un tirage se réimprime, une pièce unique ne se remplace pas.'
							: 'Prints can be returned, originals cannot. The reason is simple: a print can be reprinted, a one-of-a-kind piece cannot be replaced.',
						'lede'
					)
				),
				mg_band(
					$fr ? 'Tirages' : 'Prints',
					mg_bullets( $fr ? array(
						'Vous avez <strong>14 jours</strong> après réception pour demander un retour.',
						'L’œuvre doit revenir <strong>non utilisée, dans son emballage d’origine</strong>. Un tirage encadré ou marouflé n’est plus retournable.',
						'Les <strong>frais de retour sont à votre charge</strong> s’il s’agit d’un changement d’avis, ainsi que les frais de livraison initiaux.',
					) : array(
						'You have <strong>14 days</strong> from delivery to request a return.',
						'The piece must come back <strong>unused, in its original packaging</strong>. A print that has been framed or mounted can no longer be returned.',
						'<strong>Return shipping is at your expense</strong> if you simply changed your mind, as are the original shipping costs.',
					) )
				),
				mg_band(
					$fr ? 'Originaux' : 'Originals',
					mg_p(
						$fr
							? 'Un original est une pièce unique, peinte ou dessinée à la main : il n’est pas repris en cas de changement d’avis. Prenez le temps de regarder les photos, et écrivez-moi avant d’acheter si un détail vous échappe — une question posée avant vaut mieux qu’un regret après.'
							: 'An original is a unique, hand-painted or hand-drawn piece: it cannot be returned if you change your mind. Take the time to look at the photographs, and write to me before buying if anything is unclear — a question asked beforehand beats a regret afterwards.'
					)
				),
				mg_band(
					$fr ? 'Casse ou erreur d’envoi' : 'Damage or wrong item',
					mg_p(
						$fr
							? 'Cela vaut pour <strong>tout</strong>, originaux compris. Si l’œuvre arrive abîmée, ou si je me suis trompée d’envoi :'
							: 'This applies to <strong>everything</strong>, originals included. If a piece arrives damaged, or if I sent the wrong one:'
					) . "\n\n"
					. mg_steps( $fr ? array(
						'Écrivez-moi <strong>sous 48 heures</strong> après réception, avec une photo de l’œuvre et une de l’emballage.',
						'Je vous réponds avec la marche à suivre. Les <strong>frais sont à ma charge</strong>.',
						'Au choix : remboursement complet, ou remplacement quand la pièce est un tirage.',
					) : array(
						'Write to me <strong>within 48 hours</strong> of delivery, with a photo of the piece and one of the packaging.',
						'I will reply with what to do next. <strong>Costs are on me</strong>.',
						'Your choice: a full refund, or a replacement where the piece is a print.',
					) )
				),
				mg_band(
					$fr ? 'Demander un retour' : 'Requesting a return',
					mg_p(
						$fr
							? 'Écrivez à ' . $lien . ' en indiquant votre <strong>numéro de commande</strong>, l’œuvre concernée et le motif. Je vous réponds avec l’adresse de retour — ne renvoyez rien avant d’avoir reçu cette réponse, sans quoi le colis risque de se perdre.'
							: 'Write to ' . $lien . ' with your <strong>order number</strong>, the piece concerned and the reason. I will reply with the return address — please do not send anything back before you receive that reply, or the parcel may go astray.'
					)
				),
				mg_band(
					$fr ? 'Remboursement' : 'Refunds',
					mg_p(
						$fr
							? 'Une fois le retour reçu et vérifié, le remboursement part <strong>sous 10 jours ouvrables</strong>, sur le moyen de paiement utilisé lors de l’achat. Le délai d’apparition sur votre relevé dépend ensuite de votre banque.'
							: 'Once the return has arrived and been checked, the refund is issued <strong>within 10 business days</strong>, to the payment method used at purchase. How long it then takes to appear on your statement is up to your bank.'
					) . "\n\n"
					. mg_note(
						mg_p(
							$fr
								? 'Les frais de livraison initiaux sont remboursés en cas de casse ou d’erreur de ma part. Ils ne le sont pas pour un simple changement d’avis.'
								: 'Original shipping costs are refunded in the event of damage or a mistake on my side. They are not refunded if you simply changed your mind.'
						)
					)
				),
			) );

		/* ------------------------------------------------- Confidentialité */
		case 'privacy':
			$c     = function_exists( 'morgane_contact_info' ) ? morgane_contact_info() : array();
			$ville = isset( $c['city'][ $lang ] ) ? $c['city'][ $lang ] : 'Montréal';
			$lien  = $mg_lien_mail;
			$maj   = date_i18n( $fr ? 'j F Y' : 'F j, Y' );

			return implode( "\n\n", array(
				mg_prose(
					mg_p(
						$fr
							? 'Ce site recueille le strict nécessaire pour honorer une commande et répondre à un message. Rien n’est vendu, rien n’est cédé, et aucune page n’appelle de service extérieur.'
							: 'This site collects only what is needed to fulfil an order and answer a message. Nothing is sold, nothing is passed on, and no page calls out to an outside service.',
						'lede'
					)
				),
				mg_band(
					$fr ? 'Qui est responsable' : 'Who is responsible',
					mg_p(
						$fr
							? 'Morgane Cachia, travailleuse autonome, ' . $ville . '. C’est elle qui répond des renseignements personnels recueillis par ce site, au sens de la loi québécoise sur la protection des renseignements personnels dans le secteur privé. Pour toute question : ' . $lien . '.'
							: 'Morgane Cachia, self-employed artist, ' . $ville . '. She is the person accountable for the personal information collected by this site, under Quebec’s private-sector privacy legislation. For any question: ' . $lien . '.'
					)
				),
				mg_band(
					$fr ? 'Ce qui est recueilli' : 'What is collected',
					mg_bullets( $fr ? array(
						'<strong>Une commande</strong> : votre nom, votre adresse de livraison, votre courriel et le contenu de votre panier. Le paiement est traité par le prestataire de paiement — <strong>je ne vois jamais votre numéro de carte</strong>, il ne transite pas par ce site.',
						'<strong>Un formulaire</strong> (commissions, collaborations, murales) : vos réponses me sont envoyées par courriel et <strong>ne sont pas enregistrées sur le site</strong>. Elles vivent ensuite dans ma boîte de réception, comme n’importe quel courriel.',
						'<strong>Des témoins</strong> : le panier de la boutique et la langue choisie. Ils servent au fonctionnement du site, pas à vous suivre.',
						'<strong>Votre navigateur</strong> retient votre préférence de thème clair ou sombre. Cette information reste chez vous et ne m’est jamais transmise.',
						'<strong>Une empreinte de votre adresse IP</strong>, chiffrée à sens unique, quand vous envoyez un formulaire — elle limite les envois automatisés. Elle ne permet pas de remonter à vous et s’efface au bout d’une heure.',
					) : array(
						'<strong>An order</strong>: your name, delivery address, email and basket contents. Payment is handled by the payment provider — <strong>I never see your card number</strong>, and it does not pass through this site.',
						'<strong>A form</strong> (commissions, collaborations, murals): your answers are emailed to me and <strong>are not stored on the site</strong>. They then live in my inbox, like any other email.',
						'<strong>Cookies</strong>: your shopping basket and your chosen language. They make the site work; they do not track you.',
						'<strong>Your browser</strong> remembers whether you prefer the light or dark theme. That stays with you and is never sent to me.',
						'<strong>A one-way fingerprint of your IP address</strong> when you submit a form — it caps automated submissions. It cannot be traced back to you and is erased after an hour.',
					) )
				),
				mg_band(
					$fr ? 'Ce qui n’est pas fait' : 'What is not done',
					mg_bullets( $fr ? array(
						'Aucune mesure d’audience, aucun pixel publicitaire, aucun réseau social intégré dans les pages.',
						'<strong>Aucune ressource n’est chargée depuis un tiers</strong> : les polices et les scripts d’animation sont servis par ce site. Votre visite n’est donc signalée à personne.',
						'Vos renseignements ne sont ni vendus, ni loués, ni échangés.',
					) : array(
						'No analytics, no advertising pixels, no social media widgets embedded in the pages.',
						'<strong>No resource is loaded from a third party</strong>: fonts and animation scripts are served by this site. Your visit is therefore reported to no one.',
						'Your information is never sold, rented or traded.',
					) )
				),
				mg_band(
					$fr ? 'Combien de temps' : 'For how long',
					mg_p(
						$fr
							? 'Les données d’une commande sont conservées le temps requis par les obligations comptables et fiscales. Les courriels sont conservés tant que l’échange peut encore servir, puis supprimés.'
							: 'Order data is kept for as long as accounting and tax obligations require. Emails are kept as long as the exchange may still be useful, then deleted.'
					)
				),
				mg_band(
					$fr ? 'Vos droits' : 'Your rights',
					mg_p(
						$fr
							? 'Vous pouvez demander à <strong>consulter</strong> les renseignements que je détiens sur vous, à les faire <strong>corriger</strong>, ou à les faire <strong>supprimer</strong> lorsque rien ne m’oblige à les garder. Écrivez à ' . $lien . ' : je réponds dans les trente jours. Si ma réponse ne vous satisfait pas, vous pouvez vous adresser à la Commission d’accès à l’information du Québec.'
							: 'You may ask to <strong>see</strong> the information I hold about you, to have it <strong>corrected</strong>, or to have it <strong>deleted</strong> where nothing obliges me to keep it. Write to ' . $lien . ' and I will reply within thirty days. If my answer does not satisfy you, you may contact the Commission d’accès à l’information du Québec.'
					)
				),
				mg_prose(
					mg_p(
						( $fr ? 'Dernière mise à jour : ' : 'Last updated: ' ) . $maj . '.',
						'note-date'
					)
				),
			) );
	}

	return '';
}

/* =========================================================================
   4. Passe 2 : écrire les contenus
   ========================================================================= */

/*
 * Pages dont le contenu ouvre sur son propre h1 (le bandeau de mg_hero) : elles
 * prennent le gabarit sans titre, sinon le titre de la page ferait doublon
 * juste au-dessus. Le gabarit est posé avec le contenu, pas avant : les deux
 * vont ensemble.
 *
 * L'accueil est absent volontairement : il a son propre gabarit
 * (front-page.html), qu'un gabarit de page choisi ici viendrait supplanter.
 */
$no_title = array_flip( array(
	'about', 'portfolio', 'pf_youth', 'pf_illus', 'pf_draw', 'pf_murals',
	'murals', 'hub', 'commissions', 'collaborations',
) );

$written = 0;
foreach ( $ids as $key => $pair ) {
	foreach ( array( 'fr', 'en' ) as $lang ) {
		$id = $pair[ $lang ];
		if ( ! $id ) {
			continue;
		}

		$existing = get_post_field( 'post_content', $id );
		if ( '' !== trim( $existing ) && ! $force_all && ! isset( $force_keys[ $key ] ) ) {
			continue; // Déjà rempli — on ne touche pas au travail de Morgane.
		}

		$content = strtr( mg_content( $key, $lang ), $urls[ $lang ] );
		if ( '' === $content ) {
			continue;
		}

		wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );

		if ( isset( $no_title[ $key ] ) ) {
			update_post_meta( $id, '_wp_page_template', 'page-no-title' );
		}

		$written++;
	}
}

WP_CLI::success( $written . ' pages remplies. Relancer avec MORGANE_FORCE=1 (tout) ou MORGANE_FORCE=<clé> pour réécrire.' );

/* =========================================================================
   5. Passe 3 : remettre les slugs voulus
   ========================================================================= */

/*
 * Polylang autorise deux pages de langues différentes à partager le même slug
 * — la langue est dans l'URL, il n'y a pas d'ambiguïté. Mais ce dédoublonnage
 * vit dans ses filtres de requête, absents en WP-CLI : `wp_insert_post` comme
 * `wp_update_post` rajoutent « -2 ». On remet donc le slug voulu en base, une
 * fois toutes les écritures terminées.
 */

global $wpdb;
$renamed = 0;

foreach ( $ids as $key => $pair ) {
	foreach ( array( 'fr', 'en' ) as $lang ) {
		$id   = $pair[ $lang ];
		$slug = $tree[ $key ][ $lang ][0];

		if ( ! $id || get_post_field( 'post_name', $id ) === $slug ) {
			continue;
		}

		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		WP_CLI::log( 'Slug corrigé : #' . $id . ' → ' . $slug . ' (' . $lang . ')' );
		$renamed++;
	}
}

if ( $renamed ) {
	flush_rewrite_rules( false );
}

WP_CLI::success( $renamed . ' slugs remis en forme.' );

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
 *
 * En ligne de commande, la consigne arrive par l'environnement. Depuis
 * l'installateur de wp-admin — un hébergement mutualisé sans SSH n'a pas
 * d'autre porte d'entrée — elle arrive par $GLOBALS, comme
 * `morgane_install_only` le fait déjà pour les photos.
 */
$force_env  = (string) getenv( 'MORGANE_FORCE' );
if ( '' === $force_env && isset( $GLOBALS['morgane_force'] ) ) {
	$force_env = (string) $GLOBALS['morgane_force'];
}
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
	'terms'          => array( 'fr' => array( 'conditions-generales-de-vente', 'Conditions générales de vente' ), 'en' => array( 'terms-and-conditions', 'Terms and conditions of sale' ) ),
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

	/*
	 * Boutique WooCommerce : page unique, que Polylang gratuit ne traduit pas.
	 *
	 * Surtout, on ne passe pas par `get_permalink()` : il rend l'adresse dans la
	 * langue à laquelle la page est *rattachée*, pas dans celle qu'on vise. Une
	 * page Boutique rattachée à l'anglais donnait donc « /en/boutique/ » aux
	 * deux langues — et l'accueil français renvoyait ses visiteurs sur le site
	 * anglais. Constaté en production.
	 *
	 * `morgane_wc_page_url()` retire le préfixe d'origine avant de poser celui
	 * de la langue visée : c'est précisément ce qu'elle sait faire.
	 */
	if ( function_exists( 'morgane_wc_page_url' ) ) {
		$urls[ $lang ]['{{shop}}'] = morgane_wc_page_url( 'shop', $lang );
	} else {
		$shop = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		$urls[ $lang ]['{{shop}}'] = $shop > 0 ? get_permalink( $shop ) : home_url( '/' );
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
		// Surtitre facultatif : vide, il ne laisse pas un paragraphe creux
		// au-dessus du titre. Même règle que dans mg_hero().
		. ( '' !== $eyebrow
			? '<!-- wp:paragraph {"className":"eyebrow"} --><p class="eyebrow">' . $eyebrow . '</p><!-- /wp:paragraph -->' . "\n"
			: '' )
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
function mg_hero( $eyebrow, $title, array $lede, array $buttons, array $photo = array(), array $floats = array(), $min_height = '72vh' ) {

	// Les formes floues vivent dans un bloc HTML : ce sont des éléments de
	// décor, rien que Morgane ait à modifier depuis l'éditeur.
	$html = '<!-- wp:html -->' . "\n"
		. '<div class="blob blob--rose" style="width:min(34vw,360px);aspect-ratio:1;top:6%;left:-7%;"></div>' . "\n"
		. '<div class="blob blob--sage" style="width:min(26vw,290px);aspect-ratio:1;bottom:8%;right:-5%;"></div>' . "\n";

	// Pas de `data-reveal` sur ces œuvres : la boucle REVEAL d'app.js saute
	// tout ce qui est dans le hero, qui a sa propre ouverture.
	foreach ( $floats as $i => $float ) {
		$html .= '<figure class="art-float art-float--' . ( 0 === $i ? 'left' : 'right' ) . '">'
			. mg_photo( $float[0], $float[1], 'medium_large', true, '(max-width: 1099px) 32vw, 19vw' )
			. '</figure>' . "\n";
	}
	$html .= '<!-- /wp:html -->' . "\n";

	// Un surtitre vide ne laisse pas un paragraphe vide derrière lui : il
	// pousserait le titre vers le bas et l'éditeur signalerait un bloc sans
	// contenu. Même raison que pour le bloc de boutons, plus bas.
	$text = '' !== $eyebrow
		? '<!-- wp:paragraph {"className":"hero__eyebrow"} -->' . "\n"
			. '<p class="hero__eyebrow">' . $eyebrow . '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
		: '';

	$text .= '<!-- wp:heading {"level":1} -->' . "\n"
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
			// Sans boutons, pas de bloc de boutons : un conteneur vide laisse une
			// gouttière sous l'accroche, et l'éditeur signale un bloc sans contenu.
			. ( $buttons ? mg_buttons( $buttons, true ) . "\n" : '' )
			. '</div>' . "\n" . '<!-- /wp:group -->' . "\n";
	}

	/*
	 * La hauteur minimale se règle : 72vh donne son ampleur à un bandeau qui
	 * porte une œuvre, mais laissait 450 px de vide sous trois lignes de texte
	 * sur les pages de conditions. Le fond « aurore » reste, la respiration est
	 * proportionnée à ce qu'il y a dedans.
	 */
	$mh_attr  = $min_height ? ',"dimensions":{"minHeight":"' . $min_height . '"}' : '';
	$mh_style = $min_height ? 'min-height:' . $min_height . ';' : '';

	return '<!-- wp:group {"tagName":"section","align":"full","className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}' . $mh_attr . '},"layout":{"type":"constrained","contentSize":"' . $size . '"}} -->' . "\n"
		. '<section class="wp-block-group alignfull hero" style="' . $mh_style . 'padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
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
	/*
	 * Une seule série. Les mots étaient doublés du temps où le bandeau
	 * défilait : la boucle CSS avait besoin de deux séries pour se recoudre
	 * sans saut. Le bandeau est immobile depuis, et sur un écran large les
	 * deux séries tenaient en entier — on lisait la même liste deux fois.
	 */
	$span = '<span>' . implode( '</span><span>', $words ) . '</span>';
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
 * Pages de conditions : contact, conditions de vente, retours, confidentialité.
 *
 * Une colonne, alignée à gauche, sur une mesure de lecture. On n'emploie pas
 * `mg_band()` ici : celle-ci met le titre dans un rail et le texte en regard,
 * ce qui sert l'éditorial mais dessert un texte de conditions — le titre y
 * annonce ce qui suit et doit rester au-dessus de lui. Avec des intitulés
 * longs (« Qui est responsable »), le rail se cassait en deux lignes et
 * laissait un grand vide sous lui.
 */
function mg_legal( $inner ) {
	return '<!-- wp:group {"align":"wide","className":"legal","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide legal">' . "\n" . $inner . "\n"
		. '</div>' . "\n" . '<!-- /wp:group -->';
}

/**
 * Une section : son titre, puis son contenu, dans le fil. Sans titre, c'est un
 * préambule : il garde la mesure de lecture des sections qui le suivent.
 */
function mg_legal_part( $title, $inner ) {
	return '<!-- wp:group {"className":"legal__part","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group legal__part">' . "\n"
		. ( '' !== $title ? mg_h2( $title ) . "\n" : '' ) . $inner . "\n"
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
			$marquee = '<span>' . implode( '</span><span>', $words ) . '</span>';  // une seule série, voir mg_marquee()

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
						'(max-width: 1099px) 32vw, 19vw'
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
						'(max-width: 1099px) 32vw, 19vw'
					)
					. '</figure>' . "\n"
					. '<!-- /wp:html -->' . "\n"

					. '<!-- wp:group {"className":"hero__inner","layout":{"type":"default"}} -->' . "\n"
					. '<div class="wp-block-group hero__inner">' . "\n"

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
						'',
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
					. '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">'
					. ( $fr ? 'Entre magie et mélancolie' : 'Between magic and melancholy' )
					. '</h2><!-- /wp:heading -->' . "\n"
					. '<!-- wp:paragraph {"align":"center","fontSize":"large","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->' . "\n"
					. '<p class="has-text-align-center has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">'
					. ( $fr
						? 'J’aime peindre des personnages et faire apparaître leurs histoires et leurs âmes, dans mes illustrations à la gouache comme dans mes murales.'
						: 'I love painting characters and revealing their stories, their souls, in both my gouache illustrations and my murals.' )
					. '</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
					. mg_buttons( array( array( '{{about}}', $fr ? 'À propos' : 'About' ) ), true ) . "\n"
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

				/*
				 * Bandeau nu : le titre, et deux œuvres qui flottent. Le surtitre
				 * et l'accroche sont retirés ; les deux appels à l'action sont
				 * descendus sous la grille des portfolios, dans le bloc de fin.
				 */
				mg_hero(
					'',
					'Portfolio',
					array(),
					array(),
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
						array( '{{commissions}}', $fr ? 'Commander une œuvre' : 'Commission a piece' ),
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
						array( '#formulaire', $fr ? 'Parlez-moi de votre projet !' : 'Tell me about your idea!' ),
						array( '{{pf_murals}}', $fr ? 'Voir les murales' : 'See the murals' ),
					),
					$fr
						? array( 21, 'Murale : un enfant en imperméable jaune déterre une graine lumineuse' )
						: array( 21, 'Mural: a child in a yellow raincoat unearthing a glowing seed' )
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

			return implode( "\n\n", array(
				mg_hero(
					'',
					'Contact',
					array(),
					array(),   // pas de bouton
					array(),   // pas de photo
					array(),   // pas d'œuvres flottantes
					'44vh'
				),
				mg_legal(
					mg_legal_part(
						$fr ? 'Où m’écrire' : 'Where to write',
						mg_p( ( $fr ? 'Courriel : ' : 'Email: ' ) . $mg_lien_mail )
						. "\n\n" . mg_p(
							$fr
								? 'Je réponds en général sous deux à trois jours ouvrables. Si vous n’avez rien reçu au bout d’une semaine, votre message s’est probablement perdu : réécrivez-moi sans hésiter.'
								: 'I usually reply within two to three business days. If you have heard nothing after a week, your message has most likely gone astray — please do write again.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Un projet en tête ?' : 'A project in mind?',
						mg_p(
							$fr
								? 'Pour une œuvre sur mesure, une collaboration ou une murale, les <a href="{{hub}}">formulaires dédiés</a> me font gagner un aller-retour : ils demandent d’emblée les dimensions, le lieu, les délais et le médium souhaité.'
								: 'For a custom piece, a collaboration or a mural, the <a href="{{hub}}">dedicated forms</a> save us a round trip: they ask up front about dimensions, location, timing and the medium you have in mind.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Une commande de la boutique' : 'A shop order',
						mg_p(
							$fr
								? 'Indiquez votre numéro de commande dans le message : il figure dans le courriel de confirmation. Les conditions de retour sont détaillées sur la page <a href="{{returns}}">Retours et remboursements</a>.'
								: 'Please include your order number — it appears in your confirmation email. Return conditions are set out on the <a href="{{returns}}">Returns and refunds</a> page.'
						)
					)
				),
			) );

		/* ------------------------------------ Conditions générales de vente */
		case 'terms':
			/*
			 * Le texte de Morgane, repris tel quel à quelques corrections près.
			 * Le domaine n'est pas écrit en dur : il est lu sur le serveur qui
			 * génère la page, et ne peut donc pas désigner un autre site que
			 * celui où l'on achète.
			 *
			 * La date est celle de la version du texte, pas celle de la
			 * génération : les conditions d'une commande sont celles affichées
			 * au moment où elle est confirmée (section 12). À changer à la main
			 * avec le texte.
			 */
			$domaine = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

			return implode( "\n\n", array(
				mg_hero(
					$fr ? 'Avant d’acheter' : 'Before you buy',
					$fr ? 'Conditions générales de vente' : 'Terms and conditions of sale',
					array(
						$fr
							? 'Les présentes conditions générales de vente s’appliquent à tout achat effectué sur le site ' . $domaine . ', exploité par Morgane Cachia.'
							: 'These Terms and Conditions of Sale apply to all purchases made on the website ' . $domaine . ', operated by Morgane Cachia.',
						$fr ? 'Dernière mise à jour : 29 septembre 2026' : 'Last updated: September 29, 2026',
					),
					array(),   // pas de bouton
					array(),   // pas de photo
					array(),   // pas d'œuvres flottantes
					'44vh'
				),
				mg_legal(
					mg_legal_part(
						'',
						mg_p(
							$fr
								? 'En passant une commande, le client reconnaît avoir lu et expressément accepté les présentes conditions générales de vente, telles qu’affichées sur le site au moment de la confirmation du paiement. Cette acceptation se matérialise par le clic sur le bouton de confirmation de commande, après validation d’une case à cocher à cet effet.'
								: 'By placing an order, the customer acknowledges having read and expressly accepted these Terms and Conditions of Sale as displayed on the website at the time payment is confirmed. This acceptance is completed by clicking the order confirmation button after selecting the relevant checkbox.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'La présente entente est rédigée en français. En cas de traduction dans une autre langue, la version française prévaut en cas de contradiction ou d’ambiguïté.'
								: 'This agreement is drafted in French. If it is translated into another language, the French version prevails in the event of any discrepancy or ambiguity.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '1. Produits' : '1. Products',
						mg_p(
							$fr
								? 'La boutique vend notamment des œuvres originales, impressions, illustrations, objets artistiques et produits réalisés en collaboration.'
								: 'The shop sells, in particular, original artworks, prints, illustrations, art objects, and products created in collaboration with others.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les dimensions, couleurs et textures peuvent légèrement varier selon l’écran utilisé. Les œuvres et objets réalisés à la main peuvent aussi présenter de petites variations qui ne constituent pas un défaut.'
								: 'Dimensions, colours, and textures may vary slightly depending on the screen used. Handmade artworks and objects may also have minor variations, which do not constitute defects.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les informations essentielles concernant chaque produit (format, matériaux, dimensions, options et disponibilité) sont indiquées dans sa fiche descriptive.'
								: 'The essential information concerning each product — including its format, materials, dimensions, options, and availability — is provided in the product description.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '2. Prix et taxes' : '2. Prices and taxes',
						mg_p(
							$fr
								? 'Les prix sont indiqués en dollars canadiens, sauf indication contraire.'
								: 'Prices are shown in Canadian dollars unless otherwise indicated.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les taxes applicables et les frais de livraison sont ajoutés ou affichés avant la confirmation du paiement. Les prix peuvent être modifiés à tout moment, mais ces modifications ne s’appliquent pas aux commandes déjà confirmées.'
								: 'Applicable taxes and shipping fees are added or displayed before payment is confirmed. Prices may be changed at any time; however, such changes do not apply to orders that have already been confirmed.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Pour toute commande expédiée hors du Canada, les droits de douane, taxes d’importation et autres frais exigés par les autorités du pays de destination sont entièrement à la charge du client, sans exception. La boutique n’a aucune visibilité sur ces montants, qui varient selon le pays et la nature des produits. Il appartient au client de vérifier la réglementation d’importation en vigueur dans son pays avant de passer commande. Aucun remboursement ne sera accordé en cas de refus de dédouanement ou de non-retrait du colis imputable au client.'
								: 'For any order shipped outside Canada, customs duties, import taxes, and any other fees required by the authorities of the destination country are entirely the customer’s responsibility, without exception. The shop has no control over or visibility into these amounts, which vary depending on the country and the nature of the products. Customers are responsible for checking the import regulations in force in their country before placing an order. No refund will be issued if customs clearance is refused or if the parcel is not collected for reasons attributable to the customer.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '3. Commandes et paiement' : '3. Orders and payment',
						mg_p(
							$fr
								? 'Le client doit fournir des renseignements exacts, notamment son nom, son adresse courriel et son adresse de livraison.'
								: 'The customer must provide accurate information, including their name, email address, and shipping address.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Une commande est confirmée une fois le paiement accepté. Un courriel de confirmation est alors envoyé au client.'
								: 'An order is confirmed once payment has been accepted. A confirmation email is then sent to the customer.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les paiements sont traités par Stripe, selon les modes proposés sur le site.'
								: 'Payments are processed by Stripe, using the payment methods offered on the website.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Si un produit devient indisponible ou si une erreur importante s’est glissée dans sa description ou son prix, la boutique peut annuler la commande concernée et rembourser intégralement le montant payé.'
								: 'If a product becomes unavailable or if a significant error appears in its description or price, the shop may cancel the relevant order and issue a full refund of the amount paid.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '4. Préparation et livraison' : '4. Processing and delivery',
						mg_p(
							$fr
								? 'Le délai de préparation varie selon le type de produit :'
								: 'Processing times vary depending on the type of product:'
						)
						. "\n\n" . mg_bullets( $fr ? array(
							'<strong>Produit en stock</strong> : 1 à 7 jours ouvrables ;',
							'<strong>Impression à produire</strong> : 5 à 15 jours ouvrables ;',
							'<strong>Commande personnalisée</strong> (murale, collaboration) : délai convenu dans le devis.',
						) : array(
							'<strong>In-stock product:</strong> 1 to 7 business days;',
							'<strong>Print made to order:</strong> 5 to 15 business days;',
							'<strong>Custom order</strong>, including murals and collaborations: timeframe agreed upon in the quotation.',
						) )
						. "\n\n" . mg_p(
							$fr
								? 'Ces délais sont indicatifs et s’entendent hors temps de transport. En cas de délai inhabituellement long, la boutique en informera le client par courriel.'
								: 'These timeframes are estimates and do not include shipping time. In the event of an unusually long delay, the shop will notify the customer by email.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les délais de transport dépendent du transporteur. La boutique ne peut être tenue responsable des retards causés par le transporteur, les douanes, les conditions météorologiques ou un événement indépendant de sa volonté.'
								: 'Shipping times depend on the carrier. The shop cannot be held responsible for delays caused by the carrier, customs authorities, weather conditions, or events beyond its reasonable control.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Le client est responsable de l’exactitude de son adresse. Des frais peuvent être demandés pour une nouvelle expédition lorsque l’adresse fournie est erronée ou incomplète.'
								: 'The customer is responsible for ensuring that their shipping address is accurate. Additional fees may be charged for reshipping when the address provided is incorrect or incomplete.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '5. Retours et remboursements' : '5. Returns and refunds',
						mg_p(
							$fr
								? 'Les retours, échanges, remboursements et réclamations concernant les articles endommagés sont régis par la page <a href="{{returns}}">Retours et remboursements</a>, qui fait partie intégrante des présentes conditions. En résumé :'
								: 'Returns, exchanges, refunds, and claims concerning damaged items are governed by the <a href="{{returns}}">Returns and refunds</a> page, which forms an integral part of these Terms and Conditions. In summary:'
						)
						. "\n\n" . mg_bullets( $fr ? array(
							'Tout produit reçu endommagé ou non conforme doit être signalé dans les 48 heures suivant la réception, avec photos à l’appui ;',
							'Un remplacement ou un remboursement sera offert au choix de la boutique ;',
							'Les produits retournés doivent être dans leur état d’origine.',
						) : array(
							'Any product received damaged or not as described must be reported within 48 hours of receipt, with supporting photographs;',
							'A replacement or refund will be offered at the shop’s discretion;',
							'Returned products must be in their original condition.',
						) )
						. "\n\n" . mg_p(
							$fr
								? 'Cette politique ne limite pas les droits et recours prévus par les lois applicables, notamment en cas de produit défectueux ou non conforme.'
								: 'This policy does not limit any rights or remedies provided under applicable laws, including in cases involving defective or non-conforming products.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '6. Droit d’annulation et exceptions' : '6. Cancellation rights and exceptions',
						/*
						 * La Loi sur la protection du consommateur n'impose aucun délai
						 * de réflexion pour un achat en ligne : ses sept jours ne jouent
						 * que si le commerçant a manqué à son devoir d'information
						 * (art. 54.8). Le texte d'origine lui attribuait ce droit ; c'est
						 * la boutique qui l'accorde, et il s'ajoute à ceux de la Loi.
						 */
						mg_p(
							$fr
								? 'Le client dispose d’un droit d’annulation de sept (7) jours suivant la réception de la marchandise pour les produits standards en stock. Ce droit est accordé par la boutique et s’ajoute à ceux que la Loi sur la protection du consommateur du Québec (RLRQ, c. P-40.1) reconnaît au client.'
								: 'The customer has a seven (7)-day cancellation period following receipt of the goods for standard in-stock products. This right is granted by the shop and is in addition to the rights the customer has under Quebec’s Consumer Protection Act (CQLR, c. P-40.1).'
						)
						. "\n\n" . mg_p( $fr ? 'Ce droit ne s’applique pas aux :' : 'This right does not apply to:' )
						. "\n\n" . mg_bullets( $fr ? array(
							'œuvres originales (pièce unique ou non reproductible) ;',
							'impressions produites sur commande spécifique (format, papier ou personnalisation) ;',
							'commandes personnalisées (murales, collaborations, œuvres sur devis).',
						) : array(
							'original artworks, including one-of-a-kind or non-reproducible pieces;',
							'prints produced to a customer’s specific requirements, including format, paper, or personalization;',
							'custom orders, including murals, collaborations, and artworks produced pursuant to a quotation.',
						) )
						/*
						 * La directive européenne n'exclut pas les pièces uniques : ses
						 * exceptions (art. 16) visent les biens faits sur mesure ou
						 * personnalisés. « Sous réserve des mêmes exceptions » promettait
						 * aux clients européens moins que ce que la directive leur donne.
						 */
						. "\n\n" . mg_p(
							$fr
								? 'Pour les clients résidant dans l’Union européenne, le droit de rétractation est de quatorze (14) jours suivant la réception de la marchandise (Directive 2011/83/UE). Il ne s’applique pas aux biens confectionnés selon les spécifications du client ou nettement personnalisés.'
								: 'For customers residing in the European Union, the withdrawal period is fourteen (14) days following receipt of the goods under Directive 2011/83/EU. It does not apply to goods made to the customer’s specifications or clearly personalized.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Pour exercer ce droit, le client informe la boutique par courriel, à ' . $mg_lien_mail . ', avant l’expiration du délai. Les frais de retour sont à la charge du client.'
								: 'To exercise this right, the customer must notify the shop by email, at ' . $mg_lien_mail . ', before the applicable deadline expires. Return shipping costs are the customer’s responsibility.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '7. Commandes personnalisées, collaborations et murales' : '7. Custom orders, collaborations, and murals',
						mg_p(
							$fr
								? 'Les commandes personnalisées, collaborations et murales font l’objet d’un devis ou d’une entente distincte précisant le projet, le prix, les délais, les modalités de paiement et les droits d’utilisation. Toute modification demandée après l’approbation du projet peut entraîner des frais et un délai supplémentaires. Les conditions particulières indiquées dans le devis ou l’entente applicable prévalent sur les présentes conditions en cas de différence.'
								: 'Custom orders, collaborations, and murals are subject to a quotation or separate agreement specifying the project, price, timeframe, payment terms, and usage rights. Any modification requested after the project has been approved may result in additional fees and an extended timeframe. In the event of any discrepancy, the specific terms set out in the applicable quotation or agreement shall prevail over these Terms and Conditions.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Sauf indication contraire dans le devis, un acompte non remboursable de 50&nbsp;% du montant total est exigé avant le début des travaux. Cet acompte couvre les frais de conception, de matériaux et le temps de travail engagés. Il n’est pas remboursé en cas d’annulation après le début de la réalisation.'
								: 'Unless otherwise stated in the quotation, a non-refundable deposit equal to 50% of the total amount is required before work begins. This deposit covers design costs, materials, and work time incurred. It will not be refunded in the event of cancellation after production has begun.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '8. Droits d’auteur et utilisation des œuvres' : '8. Copyright and use of the works',
						mg_p(
							$fr
								? 'Les œuvres, illustrations, photographies, textes, designs et autres contenus créés par Morgane Cachia demeurent protégés par le droit d’auteur.'
								: 'The artworks, illustrations, photographs, texts, designs, and other content created by Morgane Cachia remain protected by copyright.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'L’achat d’une œuvre originale, d’une impression, d’un objet artistique ou d’un fichier numérique transfère uniquement la propriété de l’article acheté. Il ne transfère aucun droit de reproduction, de modification, de publication, de diffusion, de revente ou d’utilisation commerciale.'
								: 'The purchase of an original artwork, print, art object, or digital file transfers ownership of the purchased item only. It does not transfer any right to reproduce, modify, publish, distribute, resell, or commercially use the work.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Sauf autorisation écrite contraire, le client peut utiliser l’article acheté uniquement à des fins personnelles et non commerciales. Il ne peut notamment pas le reproduire, le numériser, le modifier, l’imprimer ou l’intégrer à un produit destiné à la vente.'
								: 'Unless otherwise authorized in writing, the customer may use the purchased item solely for personal, non-commercial purposes. In particular, the customer may not reproduce, digitize, modify, print, or incorporate it into a product intended for sale.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Toute licence commerciale ou cession de droits doit faire l’objet d’une entente écrite distincte précisant les droits accordés, les supports, le territoire, la durée, les usages permis et la rémunération applicable. Les droits non expressément accordés demeurent la propriété de Morgane Cachia.'
								: 'Any commercial licence or assignment of rights must be set out in a separate written agreement specifying the rights granted, media, territory, duration, permitted uses, and applicable compensation. Any rights not expressly granted remain the property of Morgane Cachia.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '9. Présentation des projets' : '9. Presentation of projects',
						mg_p(
							$fr
								? 'Sauf demande écrite de confidentialité formulée avant le début du projet, Morgane Cachia peut présenter une œuvre vendue ou réalisée sur commande dans son portfolio, sur son site Web, sur ses réseaux sociaux ou dans son matériel promotionnel. Cette utilisation n’inclut pas la divulgation de renseignements confidentiels du client.'
								: 'Unless the customer submits a written confidentiality request before the project begins, Morgane Cachia may showcase a sold or commissioned work in her portfolio, on her website, on social media, or in promotional materials. This use does not include the disclosure of the customer’s confidential information.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '10. Entretien et responsabilité' : '10. Care and liability',
						mg_p(
							$fr
								? 'Le client est responsable de manipuler, entreposer et entretenir le produit conformément aux recommandations fournies.'
								: 'The customer is responsible for handling, storing, and caring for the product in accordance with the recommendations provided.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'La boutique ne peut être tenue responsable des dommages causés après la livraison par une mauvaise manipulation, un mauvais entretien, un encadrement inadéquat ou des conditions d’entreposage inappropriées.'
								: 'The shop cannot be held responsible for damage caused after delivery by improper handling, inadequate care, unsuitable framing, or inappropriate storage conditions.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Cette clause ne limite pas les droits, garanties ou responsabilités qui ne peuvent légalement être exclus ou restreints.'
								: 'This clause does not limit any rights, warranties, or liabilities that cannot legally be excluded or restricted.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '11. Renseignements personnels' : '11. Personal information',
						mg_p(
							$fr
								? 'Les renseignements personnels recueillis (nom, adresse, courriel, données de paiement) sont utilisés pour traiter le paiement, préparer et expédier la commande, et communiquer avec le client à ce sujet. Ces renseignements sont traités par des tiers de confiance (Stripe pour les paiements, transporteurs pour la livraison) dans le respect de leurs propres politiques.'
								: 'The personal information collected — including the customer’s name, address, email address, and payment details — is used to process payment, prepare and ship the order, and communicate with the customer regarding the order. This information is processed by trusted third parties, including Stripe for payment processing and shipping carriers for delivery, in accordance with their own policies.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Conformément à la Loi 25 (Québec), le client dispose d’un droit d’accès, de rectification et de suppression de ses renseignements personnels. Pour exercer ces droits : ' . $mg_lien_mail . '.'
								: 'In accordance with Quebec’s Law 25, customers have the right to access, correct, and delete their personal information. To exercise these rights, please contact ' . $mg_lien_mail . '.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Pour plus d’information, veuillez consulter la <a href="{{privacy}}">Politique de confidentialité</a>.'
								: 'For more information, please consult the <a href="{{privacy}}">Privacy policy</a>.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? '12. Modifications et droit applicable' : '12. Amendments and governing law',
						mg_p(
							$fr
								? 'La boutique peut modifier les présentes conditions au besoin. Les conditions applicables à une commande sont celles qui étaient affichées au moment de sa confirmation.'
								: 'The shop may amend these Terms and Conditions as needed. The terms applicable to an order are those displayed at the time the order is confirmed.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Les présentes conditions sont régies par les lois applicables au Québec et au Canada, sous réserve des droits impératifs accordés aux consommateurs par les lois de leur pays de résidence.'
								: 'These Terms and Conditions are governed by the laws applicable in Quebec and Canada, subject to the mandatory rights granted to consumers under the laws of their country of residence.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'En cas de litige, les parties s’engagent à tenter de le résoudre à l’amiable avant tout recours judiciaire. À défaut de règlement amiable dans un délai de trente (30) jours, tout litige sera soumis à la compétence exclusive des tribunaux du district judiciaire de Montréal, Québec, Canada. Pour les clients résidant au Québec, le recours à la Division des petites créances de la Cour du Québec demeure possible conformément à la législation applicable.'
								: 'In the event of a dispute, the parties agree to attempt to resolve the matter amicably before initiating legal proceedings. If no amicable settlement is reached within thirty (30) days, any dispute shall be submitted to the exclusive jurisdiction of the courts of the judicial district of Montréal, Quebec, Canada. For customers residing in Quebec, recourse to the Small Claims Division of the Court of Québec remains available in accordance with applicable legislation.'
						)
					)
					. "\n\n" . mg_legal_part(
						'13. Contact',
						mg_p(
							$fr
								? 'Pour toute question concernant une commande ou les présentes conditions :'
								: 'For any questions concerning an order or these Terms and Conditions, please contact:'
						)
						. "\n\n" . mg_p( '<strong>Morgane Cachia</strong><br>' . $mg_lien_mail )
					)
				),
			) );

		/* ------------------------------------------ Retours, remboursements */
		case 'returns':
			return implode( "\n\n", array(
				mg_hero(
					$fr ? 'Avant d’acheter' : 'Before you buy',
					$fr ? 'Retours et remboursements' : 'Returns and refunds',
					/*
					 * Aligné sur les conditions générales de vente (sections 5 et
					 * 6), dont cette page fait partie : les deux textes ne doivent
					 * jamais se contredire. Une ambiguïté entre eux se lirait en
					 * faveur du client.
					 */
					array(
						$fr
							? 'Un produit standard en stock peut être retourné dans les 7 jours suivant sa réception. Pour le reste, pas d’échange, de retour ou de remboursement, sauf en cas de casse ou d’erreur d’envoi.'
							: 'A standard in-stock product can be returned within 7 days of delivery. Otherwise, no exchanges, returns, or refunds, except in case of breakage or shipping error.',
					),
					array(),   // pas de bouton
					array(),   // pas de photo
					array(),   // pas d'œuvres flottantes
					'44vh'
				),
				mg_legal(
					mg_legal_part(
						$fr ? 'Annuler sous 7 jours' : 'Cancelling within 7 days',
						mg_p(
							$fr
								? 'Vous disposez de <strong>7 jours</strong> suivant la réception pour annuler l’achat d’un produit standard en stock. Ce droit ne s’applique pas aux :'
								: 'You have <strong>7 days</strong> from delivery to cancel the purchase of a standard in-stock product. This right does not apply to:'
						)
						. "\n\n" . mg_bullets( $fr ? array(
							'œuvres originales (pièce unique ou non reproductible) ;',
							'impressions produites sur commande spécifique (format, papier ou personnalisation) ;',
							'commandes personnalisées (murales, collaborations, œuvres sur devis).',
						) : array(
							'original artworks, including one-of-a-kind or non-reproducible pieces;',
							'prints produced to a customer’s specific requirements, including format, paper, or personalization;',
							'custom orders, including murals, collaborations, and artworks produced pursuant to a quotation.',
						) )
						. "\n\n" . mg_p(
							$fr
								? 'Écrivez-moi à ' . $mg_lien_mail . ' avant la fin du délai, avec votre numéro de commande. Le produit doit revenir dans son état d’origine, et <strong>les frais de retour sont à votre charge</strong>.'
								: 'Email me at ' . $mg_lien_mail . ' before the deadline, with your order number. The product must come back in its original condition, and <strong>return shipping costs are yours</strong>.'
						)
						. "\n\n" . mg_p(
							$fr
								? 'Vous résidez dans l’Union européenne ? Le délai est alors de 14 jours : voir les <a href="{{terms}}">conditions générales de vente</a>.'
								: 'Living in the European Union? The period is then 14 days: see the <a href="{{terms}}">terms and conditions of sale</a>.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Casse ou erreur d’envoi' : 'Damage or wrong item',
						mg_p(
							$fr
								? 'Cela vaut pour <strong>tout</strong>, originaux compris. Si l’œuvre arrive abîmée, ou si je me suis trompée d’envoi :'
								: 'This applies to <strong>everything</strong>, originals included. If a piece arrives damaged, or if I sent the wrong one:'
						)
						. "\n\n" . mg_steps( $fr ? array(
							'Écrivez-moi <strong>sous 48 heures</strong> après réception, avec une photo de l’œuvre et une de l’emballage.',
							'Je vous réponds avec la marche à suivre. Les <strong>frais sont à ma charge</strong>.',
							'À mon choix : remboursement complet, ou remplacement quand la pièce est un tirage.',
						) : array(
							'Write to me <strong>within 48 hours</strong> of delivery, with a photo of the piece and one of the packaging.',
							'I will reply with what to do next. <strong>Costs are on me</strong>.',
							'At my discretion: a full refund, or a replacement where the piece is a print.',
						) )
						. "\n\n" . mg_p(
							$fr
								? 'Cette page fait partie des <a href="{{terms}}">conditions générales de vente</a>. Elle ne limite pas les droits et recours prévus par les lois applicables, notamment en cas de produit défectueux ou non conforme.'
								: 'This page forms part of the <a href="{{terms}}">terms and conditions of sale</a>. It does not limit any rights or remedies provided under applicable laws, including in cases involving defective or non-conforming products.'
						)
					)
				),
			) );

		/* ------------------------------------------------- Confidentialité */
		case 'privacy':
			$c     = function_exists( 'morgane_contact_info' ) ? morgane_contact_info() : array();
			$ville = isset( $c['city'][ $lang ] ) ? $c['city'][ $lang ] : 'Montréal';

			return implode( "\n\n", array(
				mg_hero(
					$fr ? 'Vos renseignements' : 'Your information',
					$fr ? 'Politique de confidentialité' : 'Privacy policy',
					array(
						$fr
							? 'Ce site ne recueille que le nécessaire pour traiter une commande ou répondre à un message. Aucune donnée n’est vendue, cédée ou partagée.'
							: 'This site collects only what is necessary to process an order or reply to a message. No data is sold, shared, or transferred.',
					),
					array(),   // pas de bouton
					array(),   // pas de photo
					array(),   // pas d'œuvres flottantes
					'44vh'
				),
				mg_legal(
					mg_legal_part(
						$fr ? 'Responsable' : 'Responsible party',
						mg_p(
							$fr
								? 'Morgane Cachia, travailleuse autonome, ' . $ville . '. Pour toute question : ' . $mg_lien_mail . '.'
								: 'Morgane Cachia, self-employed, ' . $ville . '. For any questions: ' . $mg_lien_mail . '.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Données recueillies' : 'Data collected',
						mg_bullets( $fr ? array(
							'<strong>Commande</strong> : nom, adresse de livraison, courriel, contenu du panier. Le paiement est géré par un prestataire externe ; je ne vois jamais vos coordonnées bancaires.',
							'<strong>Formulaires</strong> (commissions, collaborations, murales) : vos réponses me parviennent par courriel et ne sont pas stockées sur le site.',
							'<strong>Témoins (cookies)</strong> : panier et langue choisie, uniquement pour le fonctionnement du site.',
							'<strong>Préférence de thème</strong> (clair/sombre) : enregistrée dans votre navigateur, jamais transmise.',
							'<strong>Empreinte IP</strong> (chiffrée) : utilisée uniquement pour limiter les envois automatisés de formulaires ; effacée après 1 heure.',
						) : array(
							'<strong>Orders</strong>: name, shipping address, email, cart contents. Payments are handled by an external provider; I never see your card details.',
							'<strong>Forms</strong> (commissions, collaborations, murals): your responses are sent to me by email and are not stored on the site.',
							'<strong>Cookies</strong>: cart contents and selected language, used only for site functionality.',
							'<strong>Theme preference</strong> (light/dark): saved in your browser, never transmitted to me.',
							'<strong>IP fingerprint</strong> (hashed): used solely to limit automated form submissions; deleted after 1 hour.',
						) )
						. "\n\n" . mg_p(
							$fr
								? 'Aucun outil d’analyse, aucun pixel publicitaire, aucun réseau social intégré. Aucune ressource n’est chargée depuis un tiers.'
								: 'No analytics tools, no advertising pixels, no embedded social media. No resources are loaded from third parties.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Conservation' : 'Retention',
						mg_p(
							$fr
								? 'Les données de commande sont conservées selon les obligations légales (comptabilité, fiscalité). Les courriels sont gardés tant qu’utiles, puis supprimés.'
								: 'Order data is kept as required by legal obligations (accounting, tax). Emails are retained only as long as useful, then deleted.'
						)
					)
					. "\n\n" . mg_legal_part(
						$fr ? 'Vos droits' : 'Your rights',
						mg_p(
							$fr
								? 'Vous pouvez demander l’accès, la correction ou la suppression de vos données en écrivant à ' . $mg_lien_mail . '. Réponse sous 30 jours. Si besoin, vous pouvez contacter la Commission d’accès à l’information du Québec.'
								: 'You may request access to, correction of, or deletion of your data by emailing ' . $mg_lien_mail . '. I will reply within 30 days. If needed, you may contact the Commission d’accès à l’information du Québec.'
						)
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
	'contact', 'terms', 'returns', 'privacy',
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

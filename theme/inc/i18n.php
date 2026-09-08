<?php
/**
 * Bilingue FR / EN.
 *
 * Les textes d'interface du thème (libellés de menu, de formulaires, messages)
 * vivent dans des tableaux `array( 'fr' => …, 'en' => … )` plutôt que dans des
 * fichiers .po : pas d'étape de compilation dans Docker, et Morgane voit les
 * deux langues côte à côte quand elle relit un libellé.
 *
 * Les contenus de page, eux, restent dans WordPress (pages traduites Polylang).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Langue active. Retombe sur 'fr' (langue par défaut du site, Loi 96).
 */
function morgane_lang() {
	/*
	 * Point d'entrée du banc d'essai : en ligne de commande il n'y a pas de
	 * langue de page, et rien ne permettrait sinon de vérifier le rendu
	 * anglais. Aucun appelant du thème n'accroche ce filtre.
	 */
	$forcee = apply_filters( 'morgane_langue_forcee', '' );
	if ( $forcee ) {
		return (string) $forcee;
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();
		if ( $lang ) {
			return $lang;
		}
	}
	if ( function_exists( 'pll_default_language' ) ) {
		$lang = pll_default_language();
		if ( $lang ) {
			return $lang;
		}
	}
	return 'fr';
}

/**
 * Choisit la variante linguistique d'un libellé.
 *
 * @param array $strings array( 'fr' => '…', 'en' => '…' ).
 */
function morgane_t( array $strings ) {
	$lang = morgane_lang();
	if ( isset( $strings[ $lang ] ) ) {
		return $strings[ $lang ];
	}
	return isset( $strings['fr'] ) ? $strings['fr'] : reset( $strings );
}

/**
 * URL d'une page du site à partir de son chemin **français**, dans la langue
 * active. Sert au menu et aux appels à l'action : on ne code jamais une URL en
 * dur, sinon la version anglaise renvoie vers les pages françaises.
 *
 * @param string $fr_path Chemin FR, ex. 'portfolio/illustration-jeunesse'.
 * @return string URL, ou l'accueil de la langue si la page n'existe pas encore.
 */
function morgane_url( $fr_path ) {
	static $cache = array();

	$lang = morgane_lang();
	$key  = $lang . '|' . $fr_path;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$url  = '';
	$page = get_page_by_path( $fr_path );

	if ( $page ) {
		$id = $page->ID;
		if ( function_exists( 'pll_get_post' ) ) {
			$translated = pll_get_post( $id, $lang );
			if ( $translated ) {
				$id = $translated;
			}
		}
		$url = (string) get_permalink( $id );
	}

	if ( ! $url ) {
		$url = function_exists( 'pll_home_url' ) ? pll_home_url( $lang ) : home_url( '/' );
	}

	$cache[ $key ] = $url;
	return $url;
}

/**
 * Racine d'une langue, ex. « http://site/en/ ».
 *
 * `pll_home_url()` ne renvoie pas la racine de la langue mais l'URL de la page
 * d'accueil traduite. Les deux coïncident quand Polylang est réglé pour
 * (option « redirect_lang »), et divergent sinon : on récupère alors
 * « /en/home/ », et tout ce qu'on lui accole donne « /en/home/boutique/ » —
 * une redirection dans le meilleur cas, une 404 dans les autres.
 *
 * On retire donc le nom de la page d'accueil s'il est là. Le thème ne dépend
 * plus d'un réglage de Polylang pour construire des adresses justes.
 */
function morgane_lang_root( $lang ) {
	if ( ! function_exists( 'pll_home_url' ) ) {
		return home_url( '/' );
	}

	$root  = (string) pll_home_url( $lang );
	$front = (int) get_option( 'page_on_front' );
	if ( ! $front ) {
		return $root;
	}

	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $front, $lang );
		if ( $translated ) {
			$front = $translated;
		}
	}

	$slug = get_post_field( 'post_name', $front );
	if ( ! $slug ) {
		return $root;
	}

	$motif = '~/' . preg_quote( $slug, '~' ) . '/?$~';
	return preg_match( $motif, $root ) ? preg_replace( $motif, '/', $root ) : $root;
}

/**
 * URL d'une page WooCommerce (boutique, panier…) dans la langue active.
 *
 * Polylang gratuit ne traduit pas les pages WooCommerce (l'extension qui le
 * fait est payante) : ce sont des pages uniques. Elles répondent quand même
 * sous /en/, et le filtre de langue sur les produits fait le reste — on
 * préfixe donc l'URL avec la racine de la langue active.
 *
 * @param string      $page 'shop', 'cart', 'checkout'…
 * @param string|null $lang Langue visée. Par défaut, la langue active. Le
 *                          sélecteur de langue s'en sert pour construire
 *                          l'adresse de la boutique dans l'*autre* langue.
 */
function morgane_wc_page_url( $page, $lang = null ) {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return morgane_url( 'boutique' );
	}

	$id = wc_get_page_id( $page );
	if ( $id <= 0 ) {
		return morgane_url( 'boutique' );
	}

	if ( ! $lang ) {
		$lang = morgane_lang();
	}

	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $id, $lang );
		if ( $translated ) {
			return (string) get_permalink( $translated );
		}
	}

	$url = (string) get_permalink( $id );

	if ( function_exists( 'pll_home_url' ) ) {
		$path = wp_make_link_relative( $url );          // « /boutique/ », ou « /en/boutique/ »

		/*
		 * Le permalien porte le préfixe de la langue à laquelle la page est
		 * rattachée — pas celui de la langue visée. Une page boutique rattachée
		 * à l'anglais donnait « /en/boutique/ », et le menu français y collait
		 * la racine française : le bouton « Boutique » d'un site en français
		 * renvoyait à la boutique anglaise. On retire le préfixe d'origine
		 * avant de poser le bon.
		 */
		if ( function_exists( 'pll_get_post_language' ) ) {
			$source = pll_get_post_language( $id );
			if ( $source ) {
				$racine = wp_make_link_relative( morgane_lang_root( $source ) );
				if ( '/' !== $racine && 0 === strpos( $path, $racine ) ) {
					$path = '/' . substr( $path, strlen( $racine ) );
				}
			}
		}

		$url = untrailingslashit( morgane_lang_root( $lang ) ) . $path;
	}

	return $url;
}

/**
 * Raccourci : URL de la boutique.
 */
function morgane_shop_url() {
	return morgane_wc_page_url( 'shop' );
}

/**
 * URL d'une catégorie de produits, dans la langue active.
 *
 * Contrairement aux pages de WooCommerce, les catégories *sont* traduites par
 * Polylang, même en version gratuite : « prints » a son jumeau « prints-en »,
 * « originaux » le sien. On part donc toujours du slug français — celui qui est
 * écrit en dur dans le thème — et on demande à Polylang son équivalent.
 *
 * @param string $slug_fr Slug français, ex. 'originaux'.
 * @return WP_Term|null Terme dans la langue active, ou null.
 */
function morgane_categorie_terme( $slug_fr ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return null;
	}

	/*
	 * `get_term_by()` passe par le filtre de langue de Polylang : sous /en/ il
	 * cherche « prints » parmi les catégories anglaises, dont le slug est
	 * « prints-en », et ne trouve rien — les deux rayons disparaissaient donc
	 * de tout le site anglais. On interroge sans filtre (`lang => ''`) pour
	 * retrouver le terme français, dont on demande ensuite la traduction.
	 */
	$termes = get_terms( array(
		'taxonomy'   => 'product_cat',
		'slug'       => $slug_fr,
		'hide_empty' => false,
		'lang'       => '',
	) );

	if ( is_wp_error( $termes ) || empty( $termes ) ) {
		return null;
	}

	$terme = $termes[0];

	if ( function_exists( 'pll_get_term' ) ) {
		$traduit = pll_get_term( $terme->term_id, morgane_lang() );
		if ( $traduit ) {
			$autre = get_term( $traduit );
			if ( $autre && ! is_wp_error( $autre ) ) {
				$terme = $autre;
			}
		}
	}

	return ( $terme instanceof WP_Term ) ? $terme : null;
}

/**
 * URL d'une catégorie de produits, dans la langue active.
 *
 * @param string $slug_fr Slug français, ex. 'originaux'.
 * @return string URL, ou '' si la catégorie n'existe pas (ou pas encore).
 */
function morgane_categorie_url( $slug_fr ) {
	$terme = morgane_categorie_terme( $slug_fr );
	if ( ! $terme ) {
		return '';
	}

	$url = get_term_link( $terme );

	return is_wp_error( $url ) ? '' : (string) $url;
}

/**
 * La page WooCommerce actuellement affichée, s'il y en a une.
 *
 * Sert au sélecteur de langue : ces pages n'ont pas de traduction à trouver
 * (Polylang gratuit ne les traduit pas), et la boutique n'est même pas une page
 * « singulière » mais une archive de produits. Sans ce repérage, changer de
 * langue depuis le panier ou le compte renvoyait à l'accueil.
 *
 * @return string 'shop', 'cart', 'checkout', 'myaccount' — ou '' ailleurs.
 */
function morgane_wc_page_courante() {
	if ( ! function_exists( 'is_shop' ) ) {
		return '';
	}
	if ( is_shop() || is_product_taxonomy() ) {
		return 'shop';
	}
	if ( is_cart() ) {
		return 'cart';
	}
	if ( is_checkout() ) {
		return 'checkout';
	}
	if ( is_account_page() ) {
		return 'myaccount';
	}
	return '';
}

/**
 * L'adresse de la page courante dans une autre langue.
 *
 * Trois cas, dans cet ordre : la traduction de la page si elle existe ; l'une des
 * pages de WooCommerce, que Polylang gratuit ne traduit pas et dont l'adresse se
 * reconstruit ; l'accueil de la langue en dernier recours.
 *
 * Sert au sélecteur de langue et à la balise x-default, qui doivent répondre la
 * même chose — les faire diverger reviendrait à annoncer à Google une adresse
 * que le bouton ne donne pas.
 *
 * @param string $langue Code de langue visé.
 */
function morgane_url_courante_dans( $langue ) {
	if ( is_singular() && function_exists( 'pll_get_post' ) ) {
		$traduite = pll_get_post( get_queried_object_id(), $langue );
		if ( $traduite ) {
			return (string) get_permalink( $traduite );
		}
	}

	$wc = morgane_wc_page_courante();
	if ( $wc ) {
		return morgane_wc_page_url( $wc, $langue );
	}

	return function_exists( 'pll_home_url' ) ? (string) pll_home_url( $langue ) : home_url( '/' );
}

/**
 * hreflang="x-default" : la version à servir quand la langue du visiteur ne
 * correspond à aucune des nôtres.
 *
 * Polylang déclare bien « fr » et « en », mais ni lui ni SEOPress ne posent
 * celle-ci. Sans elle, Google choisit seul ce qu'il montre à un visiteur
 * hispanophone ou allemand. Le français étant la langue par défaut du site, et
 * celle qu'impose la Loi 96, c'est lui qui doit porter l'étiquette.
 */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	$defaut = pll_default_language();
	if ( ! $defaut ) {
		return;
	}

	printf(
		'<link rel="alternate" href="%s" hreflang="x-default" />' . "\n",
		esc_url( morgane_url_courante_dans( $defaut ) )
	);
}, 10 );

/**
 * Canonique de l'accueil : la bonne langue.
 *
 * Sur « /en/ », SEOPress annonçait « <link rel="canonical" href="/"> » — la page
 * française. Une canonique déclare la version de référence : celle-ci disait à
 * Google « n'indexe pas cette page, indexe l'autre », et faisait sortir tout
 * l'accueil anglais des résultats. Les autres pages anglaises, elles, sont
 * correctes : le défaut ne touche que la page d'accueil, dont l'adresse se
 * calcule à partir du site et non de la page affichée.
 *
 * On reconstruit l'adresse depuis la racine de la langue active.
 * `get_permalink()` ne conviendrait pas : sur la traduction de l'accueil il
 * renvoie « /en/home/ » et non « /en/ ».
 */
add_filter( 'seopress_titles_canonical', function ( $balise ) {
	if ( ! is_front_page() ) {
		return $balise;
	}

	return '<link rel="canonical" href="' . esc_url( morgane_lang_root( morgane_lang() ) ) . '">';
} );

/**
 * Slogan du site, dans la langue de la page.
 *
 * Le slogan part dans la balise `<title>`, dans les résultats de recherche et
 * dans les aperçus de partage. Sans ce filtre, tout le site anglais annonçait
 * « Illustratrice, artiste peintre et muraliste à Montréal ».
 *
 * Le nom du site n'est volontairement pas filtré : « Morgane Cachia – Art » est
 * un nom propre, il s'écrit pareil dans les deux langues.
 *
 * Côté administration on ne touche à rien : Morgane doit voir et modifier la
 * valeur qu'elle a saisie, pas une traduction posée par le thème.
 */
add_filter( 'option_blogdescription', function ( $value ) {
	if ( is_admin() || 'fr' === morgane_lang() ) {
		return $value;
	}
	return morgane_t( array(
		'fr' => $value,
		'en' => 'Illustrator, painter and mural artist based in Montreal',
	) );
} );

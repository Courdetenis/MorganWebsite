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
 * @param string $page 'shop', 'cart', 'checkout'…
 */
function morgane_wc_page_url( $page ) {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return morgane_url( 'boutique' );
	}

	$id = wc_get_page_id( $page );
	if ( $id <= 0 ) {
		return morgane_url( 'boutique' );
	}

	$lang = morgane_lang();

	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $id, $lang );
		if ( $translated ) {
			return (string) get_permalink( $translated );
		}
	}

	$url = (string) get_permalink( $id );

	if ( function_exists( 'pll_home_url' ) ) {
		$path = wp_make_link_relative( $url );          // /boutique/
		$url  = untrailingslashit( morgane_lang_root( $lang ) ) . $path;
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

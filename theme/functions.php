<?php
/**
 * Morgane Cachia – Art — thème bloc.
 * Direction : clair sable / sombre prune, interface calme,
 * les œuvres au centre.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( 'inc/i18n.php' );
require_once get_theme_file_path( 'inc/blocks.php' );
require_once get_theme_file_path( 'inc/forms.php' );
require_once get_theme_file_path( 'inc/contact.php' );
require_once get_theme_file_path( 'inc/securite.php' );
require_once get_theme_file_path( 'inc/boutique.php' );

// L'installateur ne sert qu'à l'administration : inutile de le charger sur le
// site public, où personne ne peut l'atteindre.
if ( is_admin() ) {
	require_once get_theme_file_path( 'inc/installer.php' );
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/app.css' );
	load_theme_textdomain( 'morgane', get_template_directory() . '/languages' );

	// WooCommerce : markup géré par le thème.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
} );

/**
 * WooCommerce accroche automatiquement son bloc « compte client » à côté de la
 * navigation (Block Hooks). Il déborde de l'en-tête et l'espace compte n'est
 * pas encore conçu — on le retire.
 * À réactiver (dans le menu) quand le parcours client sera fait.
 */
add_filter( 'hooked_block_types', function ( $hooked_blocks, $position, $anchor_block ) {
	if ( 'core/navigation' === $anchor_block ) {
		$hooked_blocks = array_diff( $hooked_blocks, array( 'woocommerce/customer-account' ) );
	}
	return $hooked_blocks;
}, 20, 3 );

/**
 * Polylang ne filtre pas les requêtes produits de WooCommerce (l'extension
 * officielle qui le fait est payante). On force la langue courante ici :
 * sans ça, la boutique anglaise liste aussi les produits français.
 */
add_filter( 'woocommerce_shortcode_products_query', function ( $args ) {
	$lang = morgane_lang();
	if ( $lang && function_exists( 'pll_current_language' ) ) {
		$args['lang'] = $lang;
	}
	return $args;
} );

add_action( 'wp_enqueue_scripts', function () {
	$ver = wp_get_theme()->get( 'Version' );
	// ponytail: filemtime = cache-bust auto en dev. En prod, un versionnage figé suffit.
	$css_ver = (string) @filemtime( get_theme_file_path( 'assets/css/app.css' ) ) ?: $ver;
	$js_ver  = (string) @filemtime( get_theme_file_path( 'assets/js/app.js' ) ) ?: $ver;

	/**
	 * Polices, feuilles de style, GSAP — tout est servi depuis le thème.
	 *
	 * Rien n'est appelé chez un tiers : ni Google Fonts, ni jsDelivr. Deux
	 * raisons, et la seconde est la plus sérieuse. D'abord la Loi 25 — une
	 * requête vers Google transmet l'adresse IP de chaque visiteur. Ensuite le
	 * paiement : les scripts enfilés ici s'exécutent aussi sur le panier et la
	 * validation de commande, avec les mêmes droits que notre propre code. Un
	 * fichier altéré chez un hébergeur de CDN y lirait ce que le client saisit.
	 * Servir les fichiers nous-mêmes supprime ce risque au lieu de le surveiller.
	 *
	 * Les polices sont chargées avant `app.css` : elle s'en sert.
	 */
	$fonts_ver = (string) @filemtime( get_theme_file_path( 'assets/css/fonts.css' ) ) ?: $ver;

	wp_enqueue_style( 'morgane-fonts', get_theme_file_uri( 'assets/css/fonts.css' ), array(), $fonts_ver );
	wp_enqueue_style( 'morgane-style', get_stylesheet_uri(), array( 'morgane-fonts' ), $ver );
	wp_enqueue_style( 'morgane-app', get_theme_file_uri( 'assets/css/app.css' ), array( 'morgane-fonts' ), $css_ver );

	$gsap_ver = (string) @filemtime( get_theme_file_path( 'assets/js/vendor/gsap.min.js' ) ) ?: '3.13.0';

	wp_enqueue_script( 'gsap', get_theme_file_uri( 'assets/js/vendor/gsap.min.js' ), array(), $gsap_ver, true );
	wp_enqueue_script( 'gsap-st', get_theme_file_uri( 'assets/js/vendor/ScrollTrigger.min.js' ), array( 'gsap' ), $gsap_ver, true );
	wp_enqueue_script( 'morgane-app', get_theme_file_uri( 'assets/js/app.js' ), array( 'gsap', 'gsap-st' ), $js_ver, true );
} );

/**
 * En-têtes de sécurité, sur le site public uniquement.
 *
 * Posés ici pour qu'ils suivent le thème d'un serveur à l'autre : la
 * configuration Apache ou Nginx d'un hébergement mutualisé n'est pas toujours
 * accessible. Si le serveur les pose déjà, il gagne — ces trois-là sont
 * idempotents.
 *
 * Volontairement absents : `Strict-Transport-Security`, qui n'a de sens qu'en
 * HTTPS et se pose au niveau du serveur, et la politique de sécurité de contenu,
 * qui demande d'inventorier les scripts de WooCommerce et de la passerelle de
 * paiement avant de pouvoir être stricte. Voir docs/deploiement.md.
 */
add_filter( 'wp_headers', function ( $headers ) {
	if ( is_admin() ) {
		return $headers;
	}
	// Empêche un navigateur de deviner le type d'un fichier et de l'exécuter.
	$headers['X-Content-Type-Options'] = 'nosniff';
	// Le site ne s'affiche pas dans le cadre d'un autre : pas de détournement de clic.
	$headers['X-Frame-Options']        = 'SAMEORIGIN';
	// L'adresse complète d'une page ne fuite pas vers les sites tiers liés.
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
	return $headers;
} );

/**
 * Style de galerie « défilement horizontal », pour les portfolios.
 * Un style de bloc plutôt qu'un bloc maison : Morgane insère une galerie
 * normale et choisit le style dans la barre latérale. Rien à apprendre.
 */
add_action( 'init', function () {
	register_block_style(
		'core/gallery',
		array(
			'name'  => 'strip',
			'label' => __( 'Défilement horizontal', 'morgane' ),
		)
	);
} );

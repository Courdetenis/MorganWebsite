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

	wp_enqueue_style( 'morgane-style', get_stylesheet_uri(), array(), $ver );
	wp_enqueue_style( 'morgane-app', get_theme_file_uri( 'assets/css/app.css' ), array(), $css_ver );

	/**
	 * Polices + GSAP.
	 * ponytail: CDN pour itérer vite en dev.
	 * AVANT PROD : auto-héberger (.woff2 + gsap.min.js locaux) — perf + Loi 25.
	 */
	wp_enqueue_style(
		'morgane-fonts-dev',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..600&family=DM+Sans:opsz,wght@9..40,400..700&family=Caveat:wght@500;600&display=swap',
		array(),
		null
	);

	wp_enqueue_script( 'gsap', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', array(), '3.13.0', true );
	wp_enqueue_script( 'gsap-st', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', array( 'gsap' ), '3.13.0', true );
	wp_enqueue_script( 'morgane-app', get_theme_file_uri( 'assets/js/app.js' ), array( 'gsap', 'gsap-st' ), $js_ver, true );
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

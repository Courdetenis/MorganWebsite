<?php
/** Diagnostic Polylang. */

WP_CLI::log( 'pll_the_languages existe : ' . ( function_exists( 'pll_the_languages' ) ? 'oui' : 'non' ) );
WP_CLI::log( 'pll_languages_list : ' . ( function_exists( 'pll_languages_list' ) ? wp_json_encode( pll_languages_list() ) : 'n/a' ) );
WP_CLI::log( 'pll_default_language : ' . ( function_exists( 'pll_default_language' ) ? pll_default_language() : 'n/a' ) );

$terms = get_terms( array( 'taxonomy' => 'language', 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) {
	WP_CLI::log( 'taxonomie language : ' . $terms->get_error_message() );
} else {
	WP_CLI::log( 'termes language : ' . count( $terms ) );
	foreach ( $terms as $t ) {
		WP_CLI::log( '  - ' . $t->slug . ' / ' . $t->name );
	}
}

$opts = get_option( 'polylang' );
WP_CLI::log( 'options type : ' . gettype( $opts ) );
if ( is_array( $opts ) ) {
	WP_CLI::log( '  default_lang : ' . ( isset( $opts['default_lang'] ) ? $opts['default_lang'] : 'absent' ) );
	WP_CLI::log( '  force_lang : ' . ( isset( $opts['force_lang'] ) ? $opts['force_lang'] : 'absent' ) );
}

// Contenus sans langue assignée
$pages = get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids' ) );
$prods = get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids' ) );
WP_CLI::log( 'pages : ' . count( $pages ) . ' | produits : ' . count( $prods ) );

if ( function_exists( 'pll_get_post_language' ) ) {
	$sans = 0;
	foreach ( array_merge( $pages, $prods ) as $id ) {
		if ( ! pll_get_post_language( $id ) ) {
			$sans++;
		}
	}
	WP_CLI::log( 'contenus sans langue : ' . $sans );
}

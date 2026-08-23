<?php
/**
 * Déclare les produits WooCommerce (et leurs taxonomies) traduisibles par Polylang.
 * Sans ça, Polylang n'assigne ni ne filtre les produits par langue :
 * la boutique anglaise afficherait aussi les produits français.
 * Idempotent.
 */

$opts = get_option( 'polylang' );
if ( ! is_array( $opts ) ) {
	WP_CLI::error( 'Options Polylang inattendues (' . gettype( $opts ) . ').' );
}

$post_types = isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : array();
$taxonomies = isset( $opts['taxonomies'] ) && is_array( $opts['taxonomies'] ) ? $opts['taxonomies'] : array();

foreach ( array( 'product' ) as $pt ) {
	if ( ! in_array( $pt, $post_types, true ) ) {
		$post_types[] = $pt;
	}
}
foreach ( array( 'product_cat', 'product_tag' ) as $tax ) {
	if ( ! in_array( $tax, $taxonomies, true ) ) {
		$taxonomies[] = $tax;
	}
}

$opts['post_types'] = array_values( $post_types );
$opts['taxonomies'] = array_values( $taxonomies );
update_option( 'polylang', $opts );

WP_CLI::log( 'post_types : ' . implode( ', ', $opts['post_types'] ) );
WP_CLI::log( 'taxonomies : ' . implode( ', ', $opts['taxonomies'] ) );
WP_CLI::success( 'Produits déclarés traduisibles.' );

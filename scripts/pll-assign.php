<?php
/**
 * Assigne la langue par défaut (fr) à tous les contenus et termes qui n'en ont pas.
 * Équivalent du bouton « assigner la langue par défaut » de l'admin Polylang.
 * Idempotent.
 */

if ( ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang inactif.' );
}

$default = pll_default_language();
if ( ! $default ) {
	WP_CLI::error( 'Aucune langue par défaut.' );
}

$post_types = array( 'post', 'page', 'product' );
$n_posts    = 0;

foreach ( $post_types as $pt ) {
	$ids = get_posts(
		array(
			'post_type'   => $pt,
			'numberposts' => -1,
			'post_status' => 'any',
			'fields'      => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		if ( ! pll_get_post_language( $id ) ) {
			pll_set_post_language( $id, $default );
			$n_posts++;
		}
	}
}

$taxonomies = array( 'category', 'post_tag', 'product_cat', 'product_tag' );
$n_terms    = 0;

foreach ( $taxonomies as $tax ) {
	if ( ! taxonomy_exists( $tax ) ) {
		continue;
	}
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		continue;
	}
	foreach ( $terms as $t ) {
		if ( ! pll_get_term_language( $t->term_id ) ) {
			pll_set_term_language( $t->term_id, $default );
			$n_terms++;
		}
	}
}

WP_CLI::success( sprintf( '%d contenus et %d termes passés en « %s ».', $n_posts, $n_terms, $default ) );

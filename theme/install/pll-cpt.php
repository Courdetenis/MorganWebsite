<?php
/**
 * Retire les produits WooCommerce (et leurs taxonomies) des types traduisibles
 * par Polylang.
 *
 * Une œuvre ne doit avoir qu'un seul article, donc un seul stock : la laisser
 * traduisible ferait exister une fiche française et une fiche anglaise pour la
 * même pièce, avec chacune son compteur — un original pourrait alors se vendre
 * deux fois. Les catégories suivent pour la même raison : un produit rattaché
 * au terme français n'apparaîtrait pas dans l'archive anglaise si elle restait
 * un terme distinct.
 *
 * Idempotent.
 */

$opts = get_option( 'polylang' );
if ( ! is_array( $opts ) ) {
	WP_CLI::error( 'Options Polylang inattendues (' . gettype( $opts ) . ').' );
}

/*
 * Les produits sortent de Polylang : une œuvre = un article = un stock.
 * Y laisser `product` recréerait deux fiches, donc deux compteurs — et un
 * original vendu deux fois. Les catégories suivent : un produit rattaché au
 * terme français n'apparaîtrait pas dans l'archive anglaise.
 *
 * Idempotent : on retire ce qui s'y trouve, on ne suppose pas l'état de départ.
 */
$retirer_pt = array( 'product' );
$retirer_tx = array( 'product_cat', 'product_tag' );

$opts['post_types'] = array_values( array_diff(
	isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : array(),
	$retirer_pt
) );
$opts['taxonomies'] = array_values( array_diff(
	isset( $opts['taxonomies'] ) && is_array( $opts['taxonomies'] ) ? $opts['taxonomies'] : array(),
	$retirer_tx
) );

update_option( 'polylang', $opts );

WP_CLI::log( 'post_types : ' . ( implode( ', ', $opts['post_types'] ) ?: '(aucun)' ) );
WP_CLI::log( 'taxonomies : ' . ( implode( ', ', $opts['taxonomies'] ) ?: '(aucune)' ) );
WP_CLI::success( 'Produits et taxonomies produit retirés de Polylang.' );

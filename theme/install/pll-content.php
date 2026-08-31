<?php
/**
 * Traduit en anglais chaque produit de démonstration.
 * Les pages, elles, sont créées par pages.php.
 * Idempotent — relançable sans créer de doublons.
 */

if ( ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang inactif.' );
}

// --- Produits ----------------------------------------------------------
$map = array(
	'Aurore boréale — tirage'  => 'Northern Lights — print',
	'Forêt en hiver — tirage'  => 'Winter Forest — print',
	'Fleurs séchées — dessin'  => 'Dried Flowers — drawing',
	'Marée basse — tirage'     => 'Low Tide — print',
);

// Toutes langues confondues : on cherche les produits à traduire.
$products = get_posts(
	array(
		'lang'           => '',
		'post_type'   => 'product',
		'numberposts' => -1,
		'post_status' => 'any',
		'fields'      => 'ids',
	)
);

$created = 0;
foreach ( $products as $pid ) {
	if ( pll_get_post_language( $pid ) !== 'fr' ) {
		continue;
	}
	if ( pll_get_post( $pid, 'en' ) ) {
		continue; // traduction déjà là
	}

	$title_fr = get_the_title( $pid );
	$title_en = isset( $map[ $title_fr ] ) ? $map[ $title_fr ] : $title_fr . ' (EN)';

	$new_id = wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_title'  => $title_en,
			'post_status' => 'publish',
		)
	);
	if ( is_wp_error( $new_id ) ) {
		continue;
	}

	// Recopie les données commerciales du produit source.
	foreach ( array( '_regular_price', '_price', '_manage_stock', '_stock', '_stock_status' ) as $meta ) {
		$val = get_post_meta( $pid, $meta, true );
		if ( '' !== $val ) {
			update_post_meta( $new_id, $meta, $val );
		}
	}
	wp_set_object_terms( $new_id, 'simple', 'product_type' );

	pll_set_post_language( $new_id, 'en' );
	pll_save_post_translations( array( 'fr' => $pid, 'en' => $new_id ) );
	$created++;
}

WP_CLI::success( $created . ' produits traduits en anglais.' );

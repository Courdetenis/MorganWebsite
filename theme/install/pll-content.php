<?php
/**
 * Traduit en anglais chaque produit de démonstration.
 * Les pages, elles, sont créées par pages.php.
 * Idempotent — relançable sans créer de doublons.
 */

if ( ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang inactif.' );
}

/*
 * Les produits ne sont plus traduits : une œuvre = un article = un stock.
 * Voir theme/inc/produits.php. Ce script ne s'occupe donc plus que des
 * contenus qui restent traduisibles.
 */

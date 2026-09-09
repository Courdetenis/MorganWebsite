<?php
/**
 * Vérifie que Polylang est actif avant l'étape suivante de l'installation.
 * Ne traduit plus rien : les produits sont une fiche unique bilingue (voir
 * theme/inc/produits.php) depuis la migration, ils ne passent plus par
 * Polylang. Étape conservée pour ne pas renuméroter les autres.
 */

if ( ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang inactif.' );
}

/*
 * Les produits ne sont plus traduits : une œuvre = un article = un stock.
 * Voir theme/inc/produits.php. Ce script ne s'occupe donc plus que des
 * contenus qui restent traduisibles.
 */

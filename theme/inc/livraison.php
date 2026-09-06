<?php
/**
 * Livraison : l'option « lettre » ne s'affiche que quand elle est possible.
 *
 * Postes Canada ne cote pas la lettre par son API : elle ne connaît que le
 * colis. L'option lettre est donc un tarif forfaitaire posé à la main dans une
 * zone de livraison — et un forfait, par nature, s'affiche pour tout le monde,
 * y compris pour une commande qui ne peut pas partir en lettre.
 *
 * Ce filtre pose les deux conditions qui manquent :
 *   - tout le panier appartient à la classe « lettre » — les tirages, pas les
 *     originaux, qui demandent une boîte et un suivi ;
 *   - l'envoi tient sous le poids admis en courrier, enveloppe comprise.
 *
 * L'une des deux tombe et l'option disparaît, laissant les tarifs de colis.
 * Prudent par défaut : un produit sans classe fait disparaître la lettre plutôt
 * que de proposer un envoi impossible à poster.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'woocommerce_package_rates',
	function ( $rates, $package ) {
		$classe_lettre = 'lettre';
		$poids_max     = 0.5;  // kilos admis en courrier surdimensionné.
		$enveloppe     = 0.1;  // l'enveloppe garnie ; le panier ne pèse que les tirages.

		$poids       = $enveloppe;
		$postable    = true;

		foreach ( $package['contents'] as $ligne ) {
			$produit = $ligne['data'];

			if ( $classe_lettre !== $produit->get_shipping_class() ) {
				$postable = false;
				break;
			}

			$poids += (float) $produit->get_weight() * (int) $ligne['quantity'];
		}

		if ( $postable && $poids <= $poids_max ) {
			return $rates;
		}

		// Le forfait, c'est la lettre. Les tarifs de colis viennent de Postes
		// Canada et ne sont jamais retirés.
		return array_filter(
			$rates,
			function ( $tarif ) {
				return 'flat_rate' !== $tarif->get_method_id();
			}
		);
	},
	10,
	2
);

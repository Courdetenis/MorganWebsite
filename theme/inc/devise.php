<?php
/**
 * La devise, dans le prix lui-même.
 *
 * La boutique vend en dollars canadiens (`woocommerce_currency` = CAD, posé
 * par l'installateur), mais WooCommerce n'affiche pour CAD qu'un « $ » nu : un
 * visiteur des États-Unis ou d'Europe lit un prix en dollars américains. Chaque
 * prix porte donc la mention, dans la forme de sa langue :
 *
 *   fr   45,00 $ CA   1 234,50 $ CA   symbole après le montant (norme OQLF)
 *   en   CA$45.00     CA$1,234.50     symbole avant, usage canadien-anglais
 *
 * Pourquoi quatre filtres et pas un seul sur `wc_price` : le panier et la
 * commande sont des blocs WooCommerce, qui formatent les prix dans le
 * navigateur à partir de ce que le Store API leur envoie — et le Store API lit
 * exactement ces quatre choses : le symbole, l'option de position et les deux
 * séparateurs. Filtrés à la source, vignettes, fiche, panier, commande,
 * courriels et administration disent tous la même chose.
 *
 * Rien n'est écrit en base : tout prend effet dès que le thème est en place,
 * sans rejouer l'installateur.
 *
 * Limite connue, partagée avec les noms et les liens des œuvres : en REST le
 * thème répond en français (voir `morgane_produit_en_actif()`,
 * inc/produits.php). Sur le panier anglais, après un changement de quantité,
 * les prix reviennent donc de « CA$ » à « $ CA ». Le « CA », lui, reste.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * La forme des prix dans la langue active.
 *
 * Les symboles sont des entités HTML, comme ceux de WooCommerce : `wc_price()`
 * les insère tels quels dans le balisage, et le Store API les décode.
 *
 * @return array{symbole: string, position: string, decimale: string, milliers: string}
 */
function morgane_forme_prix() {
	if ( 'en' === morgane_lang() ) {
		return array(
			'symbole'  => 'CA&#36;',
			'position' => 'left',
			'decimale' => '.',
			'milliers' => ',',
		);
	}

	// Espace fine insécable entre les milliers, insécable entre « $ » et
	// « CA » : « 1 234,50 $ CA » ne se coupe jamais en fin de ligne.
	return array(
		'symbole'  => '&#36;&nbsp;CA',
		'position' => 'right_space',
		'decimale' => ',',
		'milliers' => "\u{202F}",
	);
}

// Le symbole, seul élément propre au dollar canadien : une autre devise garde
// le sien.
add_filter( 'woocommerce_currency_symbol', function ( $symbole, $devise ) {
	return 'CAD' === $devise ? morgane_forme_prix()['symbole'] : $symbole;
}, 10, 2 );

// La position vaut pour n'importe quelle devise : c'est une convention de la
// langue, pas de la monnaie.
add_filter( 'option_woocommerce_currency_pos', function () {
	return morgane_forme_prix()['position'];
} );

/*
 * Les séparateurs suivent la langue partout, sauf dans l'administration : le
 * champ « Prix » de la fiche produit n'accepte que le séparateur décimal de la
 * boutique, et « 45.50 » y deviendrait « 4550 » si la décimale passait à la
 * virgule. Morgane continue donc d'y saisir les prix comme avant.
 */
add_filter( 'wc_get_price_decimal_separator', function ( $separateur ) {
	return is_admin() ? $separateur : morgane_forme_prix()['decimale'];
} );

add_filter( 'wc_get_price_thousand_separator', function ( $separateur ) {
	return is_admin() ? $separateur : morgane_forme_prix()['milliers'];
} );

<?php
/**
 * Banc d'essai de la fiche produit unique bilingue.
 * Le dépôt n'a pas de PHPUnit : ce fichier joue les assertions dans un
 * WordPress chargé, ce qui est ce qu'on veut vérifier de toute façon.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
 */

$GLOBALS['mg_resultats'] = array();

function mg_verif( $nom, $ok, $detail = '' ) {
	$GLOBALS['mg_resultats'][] = array( $nom, (bool) $ok, $detail );
	printf( "%s %s%s\n", $ok ? '  ok  ' : '  ÉCHEC', $nom, $detail ? ' — ' . $detail : '' );
}

function mg_verif_bilan() {
	$echecs = 0;
	foreach ( $GLOBALS['mg_resultats'] as $r ) {
		if ( ! $r[1] ) {
			$echecs++;
		}
	}
	printf( "\n%d assertion(s), %d échec(s).\n", count( $GLOBALS['mg_resultats'] ), $echecs );
	return $echecs;
}

// --- 1. Un seul article par œuvre -----------------------------------------
$produits = get_posts( array(
	'post_type'   => 'product',
	'numberposts' => -1,
	'post_status' => 'any',
	'fields'      => 'ids',
	'lang'        => '',
) );

$en = array_filter( $produits, function ( $id ) {
	return function_exists( 'pll_get_post_language' ) && 'en' === pll_get_post_language( $id );
} );

mg_verif(
	'Aucun produit rattaché à l\'anglais',
	0 === count( $en ),
	count( $en ) . ' trouvé(s) : ' . implode( ',', $en )
);

// --- 2. Les trois métas anglaises se lisent et s'écrivent ------------------
$echantillon = $produits ? (int) $produits[0] : 0;

mg_verif(
	'morgane_texte_en() existe',
	function_exists( 'morgane_texte_en' )
);

if ( function_exists( 'morgane_texte_en' ) && $echantillon ) {
	update_post_meta( $echantillon, MORGANE_META_NOM_EN, 'Northern Lights — print' );
	mg_verif(
		'Le nom anglais se relit',
		'Northern Lights — print' === morgane_texte_en( $echantillon, MORGANE_META_NOM_EN )
	);
	mg_verif(
		'Une méta absente rend une chaîne vide',
		'' === morgane_texte_en( $echantillon, MORGANE_META_RESUME_EN )
	);
	delete_post_meta( $echantillon, MORGANE_META_NOM_EN );
}

mg_verif_bilan();

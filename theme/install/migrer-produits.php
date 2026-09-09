<?php
/**
 * Replie les fiches produit anglaises dans leur jumelle française, puis les
 * supprime.
 *
 * À jouer une fois, après avoir chargé le thème qui sort les produits de
 * Polylang. Rejouable sans dommage : une fois les fiches anglaises parties,
 * il n'y a plus rien à replier.
 *
 * Aucune commande ne portait sur ces fiches au moment de la migration
 * (constat de Morgane, 2026-09-08) : la suppression n'a donc touché aucun
 * historique de commande. Ce script ne le vérifie pas lui-même — il n'y a
 * aucun contrôle de commande dans le code ci-dessous. Qui le rejoue ailleurs,
 * sur un jeu de données différent, doit s'assurer au préalable qu'aucune
 * commande ne référence les fiches anglaises visées.
 */

if ( ! function_exists( 'pll_get_post_language' ) ) {
	WP_CLI::error( 'Polylang introuvable : impossible de reconnaître les fiches anglaises.' );
}

$tous = get_posts( array(
	'post_type'   => 'product',
	'numberposts' => -1,
	'post_status' => 'any',
	'fields'      => 'ids',
	'lang'        => '',
) );

$replies    = 0;
$supprimes  = 0;
$conserves  = 0;

foreach ( $tous as $id ) {
	if ( 'en' !== pll_get_post_language( $id ) ) {
		continue;
	}

	$fr = function_exists( 'pll_get_post' ) ? (int) pll_get_post( $id, 'fr' ) : 0;

	if ( ! $fr || ! get_post( $fr ) ) {
		WP_CLI::warning( sprintf( 'Fiche EN #%d sans jumelle française : conservée, à traiter à la main.', $id ) );
		$conserves++;
		continue;
	}

	$en_post = get_post( $id );

	// On n'écrase jamais un texte anglais déjà saisi à la main.
	$paires = array(
		MORGANE_META_NOM_EN    => $en_post->post_title,
		MORGANE_META_RESUME_EN => $en_post->post_excerpt,
		MORGANE_META_DESC_EN   => $en_post->post_content,
	);

	foreach ( $paires as $cle => $texte ) {
		$texte = trim( (string) $texte );
		if ( '' !== $texte && '' === trim( (string) get_post_meta( $fr, $cle, true ) ) ) {
			update_post_meta( $fr, $cle, $texte );
		}
	}

	$replies++;

	wp_delete_post( $id, true );
	$supprimes++;
	WP_CLI::log( sprintf( 'Fiche EN #%d repliée dans #%d, puis supprimée.', $id, $fr ) );
}

WP_CLI::success( sprintf(
	'%d fiche(s) repliée(s), %d supprimée(s), %d conservée(s) pour examen.',
	$replies,
	$supprimes,
	$conserves
) );

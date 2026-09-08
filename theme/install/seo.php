<?php
/**
 * Titres et descriptions pour les moteurs de recherche.
 *
 * WordPress ne compose que « Titre de la page – Nom du site ». « Murales –
 * Morgane Cachia – Art » ne dit à personne, ni à Google, que Morgane peint des
 * fresques à Montréal — et sans description, Google fabrique lui-même la phrase
 * affichée sous le lien, en piochant au hasard dans la page.
 *
 * Ces textes sont écrits pour l’arbitrage retenu : ce sont les murales et les
 * commandes qui se référencent, pas les tirages. « affiche art » appartient à
 * Etsy et se vend 55 $ ; « muraliste Montréal » est une requête locale, gagnable,
 * et une seule conversion vaut cent tirages.
 *
 * Longueurs visées : 60 signes pour un titre, 155 pour une description — au-delà,
 * Google coupe, et ce qui tombe est justement la fin, là où on met ses mots-clés
 * quand on écrit trop long.
 *
 * Rien n’est écrasé : un texte déjà saisi depuis l’administration est laissé
 * intact, sauf si MORGANE_FORCE est posé. Les pages sont retrouvées par leur
 * chemin français, et la version anglaise par la traduction que Polylang connaît
 * — jamais par un slug deviné.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/seo.php
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forcer = (string) getenv( 'MORGANE_FORCE' );

/*
 * Chemin français => textes, par langue. array( titre, description ).
 * L’accueil n’est pas ici : son titre est un réglage global de SEOPress, à
 * traduire dans Langues > Traductions de chaînes.
 */
$metas = array(
	'murales' => array(
		'fr' => array(
			'Muraliste à Montréal — fresques murales sur mesure',
			'Fresques murales sur mesure à Montréal, pour commerces, restaurants et particuliers. Du premier échange à la pose, un projet mené de bout en bout.',
		),
		'en' => array(
			'Mural Artist in Montreal — Custom Wall Murals',
			'Custom wall murals in Montreal for businesses, restaurants and homes. From the first conversation to the finished wall, a project handled end to end.',
		),
	),
	'commissions-et-collaborations' => array(
		'fr' => array(
			'Commander une œuvre sur mesure — Morgane Cachia',
			'Illustration et peinture sur commande à Montréal. Portraits, pièces uniques et collaborations de marque, encadrés par une entente écrite.',
		),
		'en' => array(
			'Commission a Custom Artwork — Morgane Cachia',
			'Custom illustration and painting from Montreal. Portraits, one-of-a-kind pieces and brand collaborations, framed by a written agreement.',
		),
	),
	'a-propos' => array(
		'fr' => array(
			'Morgane Cachia, illustratrice et peintre à Montréal',
			'Illustratrice, artiste peintre et muraliste établie à Montréal. Un univers doux et féerique, en gouache, aquarelle et encre.',
		),
		'en' => array(
			'About Morgane Cachia — Illustrator and Painter',
			'Illustrator, painter and mural artist based in Montreal. A soft, dreamlike world painted in gouache, watercolour and ink.',
		),
	),
	'portfolio' => array(
		'fr' => array(
			'Portfolio — illustrations, peintures et murales',
			'Une sélection d’illustrations, de peintures et de murales réalisées à Montréal et ailleurs. Le travail avant les mots.',
		),
		'en' => array(
			'Portfolio — Illustration, Painting and Murals',
			'A selection of illustrations, paintings and murals made in Montreal and beyond. The work, before the words.',
		),
	),
	'boutique' => array(
		'fr' => array(
			'Tirages d’art et œuvres originales — Morgane Cachia',
			'Tirages d’art sur papier beaux-arts et œuvres originales, formats A4 et A5. Emballage soigné et expédition depuis Montréal.',
		),
		'en' => array(
			'Fine Art Prints and Original Artwork — Morgane Cachia',
			'Fine art prints on acid-free paper and one-of-a-kind originals in A4 and A5. Carefully packed and shipped from Montreal.',
		),
	),
);

/**
 * Pose un titre et une description sur une page, sans écraser une saisie
 * existante. Signale les longueurs qui dépassent ce que Google affiche.
 */
$poser = function ( $id, $langue, $titre, $description ) use ( $forcer ) {
	foreach ( array( '_seopress_titles_title' => $titre, '_seopress_titles_desc' => $description ) as $cle => $valeur ) {
		$actuel = (string) get_post_meta( $id, $cle, true );

		if ( '' !== $actuel && ! $forcer ) {
			continue; // Saisi à la main : on n’y touche pas.
		}

		update_post_meta( $id, $cle, $valeur );
	}

	$lt = mb_strlen( $titre );
	$ld = mb_strlen( $description );
	$alerte = ( $lt > 60 || $ld > 155 ) ? '  ← trop long, Google coupera' : '';

	WP_CLI::log( sprintf( '  %s : titre %d signes, description %d%s', $langue, $lt, $ld, $alerte ) );
};

if ( ! function_exists( 'pll_get_post' ) ) {
	WP_CLI::warning( 'Polylang inactif : seule la version française sera posée.' );
}

$posees = 0;

foreach ( $metas as $chemin => $langues ) {
	$page = get_page_by_path( $chemin );

	if ( ! $page ) {
		WP_CLI::warning( 'Page introuvable : ' . $chemin . ' — étape sautée.' );
		continue;
	}

	WP_CLI::log( $chemin );

	foreach ( $langues as $langue => $textes ) {
		$id = (int) $page->ID;

		if ( 'fr' !== $langue ) {
			$traduite = function_exists( 'pll_get_post' ) ? (int) pll_get_post( $page->ID, $langue ) : 0;

			if ( ! $traduite ) {
				WP_CLI::log( sprintf( '  %s : pas de traduction, ignoré', $langue ) );
				continue;
			}

			$id = $traduite;
		}

		$poser( $id, $langue, $textes[0], $textes[1] );
		$posees++;
	}
}

/*
 * La boutique n’est pas une page pour SEOPress mais une archive de produits —
 * la même particularité de WooCommerce qui cassait déjà la bascule de langue.
 * Son titre ne vient donc pas de la fiche de la page mais d’un réglage global,
 * qui vaut par défaut « %%cpt_plural%% %%current_pagination%% %%sep%% » et
 * s’affiche « Boutique  - ».
 *
 * On ne remplace que ce gabarit d’origine : un titre écrit à la main ne contient
 * pas de jetons, et reste intact.
 *
 * Réglage global, donc une seule langue : la version anglaise se saisit dans
 * Langues > Traductions de chaînes, groupe « wp-seopress ».
 */
$titres = get_option( 'seopress_titles_option_name' );

if ( is_array( $titres ) ) {
	$actuel  = $titres['seopress_titles_archive_titles']['product']['title'] ?? '';
	$intouche = ( '' === $actuel ) || ( false !== strpos( $actuel, '%%cpt_plural%%' ) );

	if ( $intouche || $forcer ) {
		$titres['seopress_titles_archive_titles']['product']['title']       = $metas['boutique']['fr'][0];
		$titres['seopress_titles_archive_titles']['product']['description'] = $metas['boutique']['fr'][1];
		update_option( 'seopress_titles_option_name', $titres );
		WP_CLI::log( 'archive boutique : titre et description posés (réglage global).' );
		$posees++;
	} else {
		WP_CLI::log( 'archive boutique : titre déjà écrit à la main, laissé intact.' );
	}
}

WP_CLI::success( $posees . ' titres et descriptions posés.' );

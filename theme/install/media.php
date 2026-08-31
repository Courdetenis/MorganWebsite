<?php
/**
 * Convertit les photos de /pictures en WebP et les importe dans la médiathèque.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/media.php
 *
 * Idempotent : une photo déjà importée est retrouvée par sa méta `_morgane_photo`
 * et n'est ni reconvertie ni réimportée. Pour tout refaire (dev) :
 *
 *   docker compose run --rm -e MORGANE_FORCE=1 --entrypoint wp wpcli eval-file /scripts/media.php
 *
 * Le script écrit la table « numéro de photo → identifiant d'attachement » dans
 * l'option `morgane_media`. C'est elle que `pages.php` lit pour poser les images
 * dans les pages : les deux scripts ne partagent que des numéros, jamais d'URL.
 *
 * Le WebP est produit par l'éditeur d'images de WordPress (GD ou Imagick), pas
 * par une extension : aucune dépendance nouvelle, et les miniatures que WordPress
 * génère ensuite héritent du même format.
 */

if ( ! function_exists( 'wp_get_image_editor' ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
}
require_once ABSPATH . 'wp-admin/includes/image.php';

if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
	WP_CLI::error( 'Ni GD ni Imagick ne sait écrire du WebP dans ce conteneur.' );
}

/**
 * Où sont les photos d'origine.
 *
 * En développement, `/pictures` est monté par docker-compose. Sur un
 * hébergement, il n'y a ni volume ni SSH : on cherche alors un dossier déposé
 * par FTP dans les téléversements. Le premier qui existe gagne.
 *
 * Le filtre laisse la porte ouverte à un troisième emplacement sans toucher au
 * script.
 */
$upload_base = wp_upload_dir();
$candidats   = (array) apply_filters( 'morgane_photos_dir', array(
	'/pictures',
	trailingslashit( $upload_base['basedir'] ) . 'morgane-photos',
) );

$source_dir = '';
foreach ( $candidats as $candidat ) {
	if ( is_dir( $candidat ) ) {
		$source_dir = untrailingslashit( $candidat );
		break;
	}
}

if ( '' === $source_dir ) {
	WP_CLI::error(
		'Aucun dossier de photos trouvé. Déposez les fichiers dans '
		. trailingslashit( $upload_base['basedir'] ) . 'morgane-photos/'
	);
}

// Longueur maximale du plus grand côté. Les photos d'origine font 3000 à 4000 px
// pour 1 à 6 Mo : bien au-delà de ce qu'affiche la plus large section (1240 px).
// WordPress fabrique ensuite ses propres tailles intermédiaires à partir de ce fichier.
$max_side = 2000;
$quality  = 82;

$force = (bool) getenv( 'MORGANE_FORCE' );

/*
 * Une entrée par photo :
 *   slug  → nom de fichier, donc morceau d'URL : décrit l'image, pas son rang ;
 *   title → titre dans la médiathèque, pour s'y retrouver côté admin ;
 *   alt   → texte alternatif français, posé sur l'attachement.
 * Les textes alternatifs anglais vivent dans pages.php, au plus près du balisage
 * de chaque page : une seule image en médiathèque, un alt par langue à l'affichage.
 */
// La table des photos vit à côté : l'installateur la lit aussi.
$photos = require __DIR__ . '/photos.php';

/**
 * Retrouve l'attachement déjà importé pour une photo.
 */
function mg_media_existing( $n ) {
	// Toutes langues confondues : voir mg_find() dans pages.php.
	$found = get_posts(
		array(
		'lang'           => '',
			'post_type'        => 'attachment',
			'post_status'      => 'any',
			'numberposts'      => 1,
			'meta_key'         => '_morgane_photo',
			'meta_value'       => (string) $n,
			'suppress_filters' => true,
		)
	);
	return $found ? (int) $found[0]->ID : 0;
}

/**
 * Le fichier d'origine, quelle que soit la casse de son extension.
 * Les photos arrivent en « 3 - site internet.jpg » ou « .JPG » selon l'appareil.
 */
function mg_media_source( $dir, $n ) {
	foreach ( scandir( $dir ) as $file ) {
		if ( preg_match( '/^' . $n . '\s*-/', $file ) && preg_match( '/\.(jpe?g|png|webp)$/i', $file ) ) {
			return $dir . '/' . $file;
		}
	}
	return '';
}

$map     = $force ? array() : (array) get_option( 'morgane_media', array() );
$done    = 0;
$skipped = 0;

/*
 * L'installateur de wp-admin traite une photo par requête : convertir les
 * vingt-trois d'un coup dépasse la limite de temps d'un hébergement mutualisé,
 * et une requête coupée en cours de route laisse la table à moitié écrite.
 * En ligne de commande, la variable n'est pas posée et la boucle passe tout.
 */
$seulement = isset( $GLOBALS['morgane_install_only'] ) ? (int) $GLOBALS['morgane_install_only'] : 0;
if ( $seulement ) {
	$photos = isset( $photos[ $seulement ] ) ? array( $seulement => $photos[ $seulement ] ) : array();
}

foreach ( $photos as $n => $photo ) {

	if ( ! $force ) {
		$existing = mg_media_existing( $n );
		if ( $existing ) {
			$map[ $n ] = $existing;
			$skipped++;
			continue;
		}
	}

	$src = mg_media_source( $source_dir, $n );
	if ( ! $src ) {
		WP_CLI::warning( 'Photo ' . $n . ' : aucun fichier trouvé dans ' . $source_dir );
		continue;
	}

	$upload  = wp_upload_dir();
	$dest    = trailingslashit( $upload['path'] ) . $photo['slug'] . '.webp';
	$est_web = (bool) preg_match( '/\.webp$/i', $src );

	/*
	 * Une WebP déjà à la bonne taille est copiée telle quelle. La faire repasser
	 * par l'éditeur d'images la ré-encoderait — une deuxième perte sur un format
	 * déjà destructif, pour un résultat plus lourd et une seconde de calcul de
	 * plus. Sur un hébergement mutualisé, ces secondes-là sont comptées.
	 */
	$copie_directe = false;
	if ( $est_web ) {
		$dim = @getimagesize( $src );
		if ( $dim && max( $dim[0], $dim[1] ) <= $max_side ) {
			$copie_directe = copy( $src, $dest );
		}
	}

	if ( ! $copie_directe ) {
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			WP_CLI::warning( 'Photo ' . $n . ' : ' . $editor->get_error_message() );
			continue;
		}

		$editor->set_quality( $quality );

		$size = $editor->get_size();
		if ( max( $size['width'], $size['height'] ) > $max_side ) {
			// false = on garde les proportions, pas de recadrage.
			$editor->resize( $max_side, $max_side, false );
		}

		$saved = $editor->save( $dest, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			WP_CLI::warning( 'Photo ' . $n . ' : ' . $saved->get_error_message() );
			continue;
		}
	}

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $photo['title'],
			'post_name'      => $photo['slug'],
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest
	);

	if ( is_wp_error( $id ) || ! $id ) {
		WP_CLI::warning( 'Photo ' . $n . ' : import impossible.' );
		continue;
	}

	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $photo['alt'] );
	update_post_meta( $id, '_morgane_photo', (string) $n );

	// Sans langue, une image n'apparaît pas dans la médiathèque filtrée de
	// Polylang. Le rendu des pages, lui, passe par des identifiants et s'en moque.
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $id, 'fr' );
	}

	$map[ $n ] = (int) $id;
	$done++;

	$before = round( filesize( $src ) / 1024 );
	$after  = round( filesize( $dest ) / 1024 );
	WP_CLI::log( sprintf(
		'Photo %2d → %s.webp  (%d Ko → %d Ko%s)',
		$n,
		$photo['slug'],
		$before,
		$after,
		$copie_directe ? ', copiée telle quelle' : ''
	) );
}

ksort( $map );
update_option( 'morgane_media', $map, false );

WP_CLI::success( sprintf( '%d photo(s) converties, %d déjà en place, %d dans la table.', $done, $skipped, count( $map ) ) );

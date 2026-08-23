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

$source_dir = '/pictures';
if ( ! is_dir( $source_dir ) ) {
	WP_CLI::error( $source_dir . ' introuvable : le dossier pictures est-il monté dans docker-compose.yml ?' );
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
$photos = array(
	// Photo 1 : plus posée dans aucune page (le bandeau d'accueil a été retiré),
	// mais gardée en médiathèque — c'est un portrait utilisable ailleurs.
	1  => array(
		'slug'  => 'morgane-cachia-artiste-peintre-oeuvre-encadree',
		'title' => 'Morgane Cachia avec une de ses peintures',
		'alt'   => 'Morgane Cachia tenant une de ses peintures à la gouache encadrée, une fée dans un jardin de roses',
	),
	2  => array(
		'slug'  => 'tirages-illustrations-gouache-morgane-cachia',
		'title' => 'Tirages d’art',
		'alt'   => 'Ensemble de tirages d’art des illustrations à la gouache de Morgane Cachia',
	),
	3  => array(
		'slug'  => 'peinture-personnalisee-epouvantail-tournesols',
		'title' => 'Épouvantail dans les tournesols — peinture sur commande',
		'alt'   => 'Peinture d’un épouvantail coiffé d’un chapeau fleuri dans un champ de tournesols, réalisée sur commande',
	),
	4  => array(
		'slug'  => 'morgane-cachia-muraliste-montreal',
		'title' => 'Morgane Cachia devant une de ses murales',
		'alt'   => 'Morgane Cachia devant sa murale : un enfant en imperméable jaune qui creuse la terre',
	),
	5  => array(
		'slug'  => 'morgane-cachia-illustratrice-montreal',
		'title' => 'Portrait de Morgane Cachia',
		'alt'   => 'Morgane Cachia, illustratrice et artiste peintre à Montréal, assise dans l’herbe avec une de ses peintures',
	),
	6  => array(
		'slug'  => 'illustration-gouache-lune-foret-hiver',
		'title' => 'La lune dans la forêt d’hiver',
		'alt'   => 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée',
	),
	7  => array(
		'slug'  => 'illustration-gouache-miroir-foret-enchantee',
		'title' => 'Le miroir de la forêt',
		'alt'   => 'Illustration à la gouache : un miroir ancien au sommet d’un escalier de pierre, dans une forêt enchantée',
	),
	8  => array(
		'slug'  => 'illustration-gouache-bibliotheque-enchantee',
		'title' => 'La bibliothèque enchantée',
		'alt'   => 'Illustration à la gouache : une femme aux cheveux dorés flottants dans une bibliothèque envahie de lierre',
	),
	9  => array(
		'slug'  => 'illustration-gouache-fee-pivoine',
		'title' => 'La fée de la pivoine',
		'alt'   => 'Illustration à la gouache : une fée assise au cœur d’une pivoine rose immense',
	),
	10 => array(
		'slug'  => 'illustration-gouache-fee-jardin-lierre',
		'title' => 'La fée au jardin',
		'alt'   => 'Illustration à la gouache : une fée éclaire une fleur de lierre au creux de sa main, dans un jardin de roses',
	),
	11 => array(
		'slug'  => 'illustration-jeunesse-faon-chiot-neige',
		'title' => 'Le faon et le chiot',
		'alt'   => 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes',
	),
	12 => array(
		'slug'  => 'illustration-jeunesse-souris-arrosoir',
		'title' => 'La souris et les primevères',
		'alt'   => 'Illustration jeunesse : une souris arrose des primevères devant sa maison creusée dans un arbre',
	),
	13 => array(
		'slug'  => 'illustration-jeunesse-pique-nique-ourson',
		'title' => 'Le pique-nique',
		'alt'   => 'Illustration jeunesse : une petite fille en robe rose serre son ourson pendant un pique-nique',
	),
	14 => array(
		'slug'  => 'illustration-jeunesse-ourson-reverbere-neige',
		'title' => 'L’allumeur de réverbères',
		'alt'   => 'Illustration jeunesse : un ourson grimpe à une échelle pour allumer un réverbère dans une ville enneigée',
	),
	15 => array(
		'slug'  => 'illustration-jeunesse-fillette-oie-jardin',
		'title' => 'La fillette et l’oie',
		'alt'   => 'Illustration jeunesse : une fillette plante une pousse dans un jardin de roses, observée par une oie blanche',
	),
	16 => array(
		'slug'  => 'dessin-encre-vieil-homme-ecrivain',
		'title' => 'L’écrivain',
		'alt'   => 'Dessin à l’encre : un vieil homme barbu écrit à la plume dans une bibliothèque sombre',
	),
	17 => array(
		'slug'  => 'dessin-encre-montagne-cascade',
		'title' => 'La cascade',
		'alt'   => 'Dessin à l’encre : une cascade dévale une paroi de montagne sous des nuages en volutes',
	),
	18 => array(
		'slug'  => 'dessin-encre-fillette-pissenlit',
		'title' => 'Le pissenlit',
		'alt'   => 'Dessin à l’encre : une fillette en bonnet souffle sur un pissenlit',
	),
	19 => array(
		'slug'  => 'dessin-encre-nature-morte-violoncelle',
		'title' => 'Nature morte au violoncelle',
		'alt'   => 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée',
	),
	20 => array(
		'slug'  => 'murale-enfant-impermeable-bleu',
		'title' => 'Murale — l’enfant à la fleur',
		'alt'   => 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres',
	),
	21 => array(
		'slug'  => 'murale-enfant-impermeable-jaune',
		'title' => 'Murale — la graine lumineuse',
		'alt'   => 'Murale : un enfant en imperméable jaune déterre une graine lumineuse',
	),
	22 => array(
		'slug'  => 'murale-arbre-du-savoir',
		'title' => 'Murale « L’arbre du savoir » (2024)',
		'alt'   => 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres',
	),
	23 => array(
		'slug'  => 'illustration-gouache-atelier-alchimiste',
		'title' => 'L’atelier de l’alchimiste',
		'alt'   => 'Illustration à la gouache : une table d’alchimiste éclairée à la bougie, fioles fumantes et vieux livres',
	),
);

/**
 * Retrouve l'attachement déjà importé pour une photo.
 */
function mg_media_existing( $n ) {
	$found = get_posts(
		array(
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
		if ( preg_match( '/^' . $n . '\s*-/', $file ) && preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
			return $dir . '/' . $file;
		}
	}
	return '';
}

$map     = $force ? array() : (array) get_option( 'morgane_media', array() );
$done    = 0;
$skipped = 0;

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

	$upload = wp_upload_dir();
	$saved  = $editor->save( trailingslashit( $upload['path'] ) . $photo['slug'] . '.webp', 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		WP_CLI::warning( 'Photo ' . $n . ' : ' . $saved->get_error_message() );
		continue;
	}

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $photo['title'],
			'post_name'      => $photo['slug'],
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$saved['path']
	);

	if ( is_wp_error( $id ) || ! $id ) {
		WP_CLI::warning( 'Photo ' . $n . ' : import impossible.' );
		continue;
	}

	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $saved['path'] ) );
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
	$after  = round( filesize( $saved['path'] ) / 1024 );
	WP_CLI::log( sprintf( 'Photo %2d → %s.webp  (%d Ko → %d Ko)', $n, $photo['slug'], $before, $after ) );
}

ksort( $map );
update_option( 'morgane_media', $map, false );

WP_CLI::success( sprintf( '%d photo(s) converties, %d déjà en place, %d dans la table.', $done, $skipped, count( $map ) ) );

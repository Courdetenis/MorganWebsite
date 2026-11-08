<?php
/**
 * Structure de la boutique WooCommerce :
 *   - deux catégories produits : Prints / Originaux ;
 *   - un attribut global « Taille », partagé par tous les prints ;
 *   - la classe de livraison « Lettre ».
 *
 * Rien de plus, et **aucun produit** : ni créé, ni modifié, ni rangé. Un print
 * = un produit variable avec des variations de taille ; un original = un
 * produit simple, stock 1. Morgane les crée et les range elle-même.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/shop.php
 */

if ( ! function_exists( 'wc_create_attribute' ) ) {
	WP_CLI::error( 'WooCommerce inactif.' );
}
if ( ! function_exists( 'pll_set_term_language' ) ) {
	WP_CLI::error( 'Polylang inactif. Lancer pll-setup.php et pll-cpt.php d\'abord.' );
}

/* -------------------------------------------------------------------------
   1. Catégories produits
   ------------------------------------------------------------------------- */

/*
 * Une seule catégorie par rayon, en français. `product_cat` n'est plus une
 * taxonomie traduite par Polylang (voir install/pll-cpt.php et le verrou posé
 * dans inc/produits.php) : elle n'a donc plus de langue à porter, et il n'y a
 * plus qu'un terme à créer par rayon.
 *
 * Une version antérieure de ce script créait ici un second terme anglais
 * (« prints-en », « originals ») et le liait au terme français comme
 * traduction Polylang. Deux termes fantômes en résultaient — jamais rattachés
 * à la moindre œuvre, invisibles de la boutique, mais bien réels dans
 * l'éditeur produit : Morgane y voyait quatre rayons à cocher au lieu de deux,
 * et une œuvre rangée par erreur dans l'un des fantômes n'apparaîtrait dans
 * aucun rayon de la vitrine. L'anglais du rayon se lit déjà ailleurs :
 * `morgane_categorie_url()` (inc/i18n.php) donne l'adresse anglaise du même
 * terme français, et `morgane_rayons_boutique()` (inc/boutique.php) son
 * libellé anglais.
 */
$categories = array(
	/*
	 * Pas de description pour les tirages : Morgane n'en veut pas, le titre du
	 * rayon suffit. Laissée vide ici, elle est aussi effacée de la base au
	 * prochain passage — sinon l'ancienne phrase resterait affichée.
	 */
	'prints'    => array(
		'name'        => 'Prints',
		'slug'        => 'prints',
		'description' => '',
		'description_en' => '',
	),
	'originaux' => array(
		'name'        => 'Originaux',
		'slug'        => 'originaux',
		'description' => 'Œuvres originales, pièces uniques. Gouache, aquarelle ou encre sur papier beaux-arts. Chaque œuvre part emballée avec soin, en livraison suivie et assurée, accompagnée de son certificat d’authenticité.',
		'description_en' => 'Original works, one of a kind. Gouache, watercolour or ink on fine art paper. Each piece ships carefully packed, tracked and insured, with its certificate of authenticity.',
	),
);

/**
 * L'anglais du rayon vit dans une métadonnée du terme, comme celui des produits
 * vit dans une métadonnée de la fiche. Sans elle, le site anglais affichait le
 * titre traduit — « Prints » — au-dessus d'un texte resté français.
 *
 * On ne l'écrase jamais : ce que Morgane a écrit dans l'écran de la catégorie
 * a toujours raison sur le texte livré ici.
 *
 * Sauf pour un rayon livré sans description : le français vient d'y être vidé,
 * et un anglais laissé en place s'afficherait seul sur le site anglais.
 */
function morgane_poser_description_en( $terme_id, $texte ) {
	if ( ! defined( 'MORGANE_META_CAT_DESC_EN' ) ) {
		return;
	}
	if ( ! $texte ) {
		delete_term_meta( $terme_id, MORGANE_META_CAT_DESC_EN );
		return;
	}
	$deja = get_term_meta( $terme_id, MORGANE_META_CAT_DESC_EN, true );
	if ( '' === trim( (string) $deja ) ) {
		update_term_meta( $terme_id, MORGANE_META_CAT_DESC_EN, $texte );
	}
}

foreach ( $categories as $key => $def ) {
	$existing = get_term_by( 'slug', $def['slug'], 'product_cat' );

	if ( $existing ) {
		$term_id = (int) $existing->term_id;
		wp_update_term( $term_id, 'product_cat', array( 'description' => $def['description'] ) );
	} else {
		$res = wp_insert_term(
			$def['name'],
			'product_cat',
			array( 'slug' => $def['slug'], 'description' => $def['description'] )
		);
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( $def['slug'] . ' : ' . $res->get_error_message() );
			continue;
		}
		$term_id = (int) $res['term_id'];
		WP_CLI::log( 'Catégorie créée : ' . $def['slug'] );
	}

	morgane_poser_description_en( $term_id, isset( $def['description_en'] ) ? $def['description_en'] : '' );
}

/* -------------------------------------------------------------------------
   2. Attribut global « Taille »
   Global (et non par produit) : les tailles se réutilisent d'un tirage à
   l'autre et deviennent filtrables dans la boutique.
   ------------------------------------------------------------------------- */

$taxonomy = 'pa_taille';
$exists   = false;

foreach ( wc_get_attribute_taxonomies() as $attribute ) {
	if ( 'taille' === $attribute->attribute_name ) {
		$exists = true;
		break;
	}
}

if ( ! $exists ) {
	$res = wc_create_attribute(
		array(
			'name'         => 'Taille',
			'slug'         => 'taille',
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		)
	);
	if ( is_wp_error( $res ) ) {
		WP_CLI::error( 'Attribut Taille : ' . $res->get_error_message() );
	}
	WP_CLI::log( 'Attribut global « Taille » créé.' );
}

// L'attribut vient d'être créé : sa taxonomie n'est pas encore enregistrée
// dans cette requête, il faut le faire à la main avant d'insérer les termes.
if ( ! taxonomy_exists( $taxonomy ) ) {
	register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false ) );
}

$sizes = array(
	'a5' => 'A5 — 14,8 × 21 cm',
	'a4' => 'A4 — 21 × 29,7 cm',
);

$term_ids = array();

foreach ( $sizes as $slug => $name ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( ! $term ) {
		$res = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( 'Taille ' . $slug . ' : ' . $res->get_error_message() );
			continue;
		}
		$term_ids[ $slug ] = (int) $res['term_id'];
		continue;
	}

	$term_ids[ $slug ] = (int) $term->term_id;
}

/*
 * Une ancienne version posait les tailles sur le produit sous forme de slugs
 * plutôt que d'identifiants. WordPress ne les reconnaissait pas et créait à
 * chaque fois un terme jumeau nommé « 5-x-7-po », de slug « 5-x-7-po-2 », que
 * plus aucune variation ne réclamait. On les enlève : le terme légitime, lui,
 * s'appelle « 5 × 7 po » et ne peut pas être confondu.
 */
foreach ( array_keys( $sizes ) as $slug ) {
	$jumeau = get_term_by( 'name', $slug, $taxonomy );
	if ( $jumeau && ! isset( $term_ids[ $jumeau->slug ] ) ) {
		wp_delete_term( (int) $jumeau->term_id, $taxonomy );
		WP_CLI::log( 'Terme en double supprimé : ' . $jumeau->slug );
	}
}

WP_CLI::log( 'Tailles : ' . implode( ', ', $sizes ) );

/* -------------------------------------------------------------------------
   3. Classe de livraison « Lettre »
   ------------------------------------------------------------------------- */

/*
 * Un tirage plat part en lettre, un original en colis suivi et protégé. La
 * classe est créée ici pour que la boutique ne dépende pas d'une donnée saisie
 * à la main ; c'est Morgane qui la pose sur ses tirages, dans la fiche.
 */
if ( ! get_term_by( 'slug', 'lettre', 'product_shipping_class' ) ) {
	wp_insert_term(
		'Lettre',
		'product_shipping_class',
		array( 'slug' => 'lettre', 'description' => 'Tirages plats, postables en courrier.' )
	);
	WP_CLI::log( 'Classe de livraison « Lettre » créée.' );
}

/*
 * Ce script ne touche plus à aucun produit.
 *
 * Il rangeait d'office dans « Prints » tout produit qui n'y était pas, à chaque
 * relance de l'installation : les originaux de Morgane s'y retrouvaient, et
 * il fallait les retirer à la main après chaque mise à jour du thème. Il
 * créait aussi un produit modèle à dupliquer, puis en réécrivait les
 * variations. Les œuvres sont désormais en ligne : ce qui est dans la
 * boutique appartient à Morgane, l'installation n'y écrit plus.
 */

WP_CLI::success( 'Boutique structurée : catégories, tailles, classe de livraison.' );

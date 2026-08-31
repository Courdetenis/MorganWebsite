<?php
/**
 * Structure de la boutique WooCommerce :
 *   - deux catégories produits : Prints / Originaux (traduites FR ↔ EN) ;
 *   - un attribut global « Taille », partagé par tous les prints ;
 *   - un produit variable d'exemple, à dupliquer pour chaque nouveau tirage.
 *
 * Rien de plus : pas de type de contenu maison, pas de champ personnalisé.
 * Un print = un produit variable avec des variations de taille ; un original
 * = un produit simple, stock 1. Morgane duplique, change le titre, la photo,
 * les prix — et c'est en ligne.
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

$categories = array(
	'prints'    => array(
		'fr' => array(
			'name'        => 'Prints',
			'slug'        => 'prints',
			'description' => 'Tirages d’art imprimés sur papier beaux-arts sans acide, 250 g/m², à l’encre pigmentaire — un rendu mat, des couleurs stables dans le temps. Chaque tirage existe en plusieurs formats.',
		),
		'en' => array(
			'name'        => 'Prints',
			'slug'        => 'prints-en',
			'description' => 'Fine art prints on acid-free 250 gsm paper, printed with pigment inks — a matte finish and colours that hold over time. Each print comes in several sizes.',
		),
	),
	'originaux' => array(
		'fr' => array(
			'name'        => 'Originaux',
			'slug'        => 'originaux',
			'description' => 'Œuvres originales, pièces uniques. Gouache, aquarelle ou encre sur papier beaux-arts. Chaque œuvre part emballée avec soin, en livraison suivie et assurée, accompagnée de son certificat d’authenticité.',
		),
		'en' => array(
			'name'        => 'Originals',
			'slug'        => 'originals',
			'description' => 'Original artworks, one of a kind. Gouache, watercolour or ink on fine art paper. Every piece ships carefully packaged, with tracked and insured delivery, together with its certificate of authenticity.',
		),
	),
);

$cat_ids = array();

foreach ( $categories as $key => $langs ) {
	foreach ( $langs as $lang => $def ) {
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

		pll_set_term_language( $term_id, $lang );
		$cat_ids[ $key ][ $lang ] = $term_id;
	}

	if ( ! empty( $cat_ids[ $key ]['fr'] ) && ! empty( $cat_ids[ $key ]['en'] ) ) {
		pll_save_term_translations( $cat_ids[ $key ] );
	}
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
	'5-x-7-po'   => '5 × 7 po',
	'8-x-10-po'  => '8 × 10 po',
	'11-x-14-po' => '11 × 14 po',
	'12-x-18-po' => '12 × 18 po',
);

foreach ( $sizes as $slug => $name ) {
	if ( ! get_term_by( 'slug', $slug, $taxonomy ) ) {
		wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	}
}
WP_CLI::log( 'Tailles : ' . implode( ', ', $sizes ) );

/* -------------------------------------------------------------------------
   3. Produits de démonstration rangés dans « Prints »
   ------------------------------------------------------------------------- */

$demo = get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any', 'lang' => '' ) );
foreach ( $demo as $pid ) {
	$lang = pll_get_post_language( $pid );
	$cat  = $cat_ids['prints'][ $lang ] ?? null;
	if ( $cat && ! has_term( $cat, 'product_cat', $pid ) ) {
		wp_set_object_terms( $pid, array( (int) $cat ), 'product_cat', true );
	}
}

/* -------------------------------------------------------------------------
   4. Modèle : un print en plusieurs tailles
   Morgane duplique ce produit pour chaque nouveau tirage.
   ------------------------------------------------------------------------- */

$model_slug = 'modele-print-plusieurs-tailles';

if ( ! get_page_by_path( $model_slug, OBJECT, 'product' ) ) {
	$product = new WC_Product_Variable();
	$product->set_name( 'Modèle — print en plusieurs tailles' );
	$product->set_slug( $model_slug );
	$product->set_status( 'draft' ); // brouillon : c'est un gabarit, pas une œuvre en vente
	$product->set_description(
		"Imprimé sur papier beaux-arts sans acide 250 g/m², à l’encre pigmentaire. "
		. "Rendu mat, couleurs stables dans le temps. Livré à plat, protégé, sans cadre.\n\n"
		. "Dupliquez ce produit pour chaque nouveau tirage : changez le titre, la photo, "
		. "la description et les prix des variations."
	);
	$product->set_short_description( 'Tirage d’art, plusieurs formats au choix.' );
	$product->set_category_ids( array( (int) $cat_ids['prints']['fr'] ) );

	$attribute = new WC_Product_Attribute();
	$attribute->set_id( wc_attribute_taxonomy_id_by_name( 'taille' ) );
	$attribute->set_name( $taxonomy );
	$attribute->set_options( array_keys( $sizes ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$product->set_attributes( array( $attribute ) );

	$product_id = $product->save();

	$prices = array( '5-x-7-po' => 25, '8-x-10-po' => 40, '11-x-14-po' => 65, '12-x-18-po' => 90 );
	foreach ( $prices as $slug => $price ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_attributes( array( 'taille' => $slug ) );
		$variation->set_regular_price( (string) $price );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 20 );
		$variation->save();
	}

	pll_set_post_language( $product_id, 'fr' );
	WP_CLI::log( 'Modèle de print créé (brouillon) : #' . $product_id );
}

WP_CLI::success( 'Boutique structurée : catégories, tailles, modèle de print.' );

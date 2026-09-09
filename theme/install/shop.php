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
	'prints'    => array(
		'name'        => 'Prints',
		'slug'        => 'prints',
		'description' => 'Tirages d’art imprimés sur papier beaux-arts sans acide, 250 g/m², à l’encre pigmentaire — un rendu mat, des couleurs stables dans le temps. Chaque tirage existe en plusieurs formats.',
	),
	'originaux' => array(
		'name'        => 'Originaux',
		'slug'        => 'originaux',
		'description' => 'Œuvres originales, pièces uniques. Gouache, aquarelle ou encre sur papier beaux-arts. Chaque œuvre part emballée avec soin, en livraison suivie et assurée, accompagnée de son certificat d’authenticité.',
	),
);

$cat_ids = array();

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

	$cat_ids[ $key ]['fr'] = $term_id;
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

/*
 * Prix, poids et dimensions de chaque format — en kilos et en centimètres, les
 * unités de la boutique. Sans poids ni dimensions, aucun transporteur ne sait
 * chiffrer l'envoi : le client arrive au paiement et ne voit aucun mode de
 * livraison. C'est la panne la plus fréquente d'une boutique d'art.
 *
 * Le poids est celui du tirage seul — papier 250 g/m² et pochette — jamais
 * celui du colis. L'enveloppe rigide est déclarée une fois pour toutes du côté
 * du transporteur ; la compter ici la ferait payer autant de fois qu'il y a de
 * tirages dans la commande. L'épaisseur reste mince pour que trois tirages
 * tiennent dans une seule enveloppe plutôt que dans trois.
 *
 * Les prix, eux, ne sont que des repères : le gabarit est un brouillon,
 * Morgane pose les siens sur chaque tirage.
 */
$formats = array(
	//              prix  poids  long.  larg.  épais.
	'a5' => array( 30,   0.02,  21.0,  14.8,  0.2 ),
	'a4' => array( 55,   0.03,  29.7,  21.0,  0.2 ),
);

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
	$attribute->set_options( array_values( $term_ids ) ); // des identifiants, pas des slugs
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$product->set_attributes( array( $attribute ) );

	$product_id = $product->save();

	foreach ( $formats as $slug => $format ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_attributes( array( $taxonomy => $slug ) );
		$variation->set_regular_price( (string) $format[0] );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 20 );
		$variation->save();
	}

	pll_set_post_language( $product_id, 'fr' );
	WP_CLI::log( 'Modèle de print créé (brouillon) : #' . $product_id );
}

/* -------------------------------------------------------------------------
   5. Taille, poids et dimensions des variations du modèle

   Rejoué à chaque exécution, et non seulement à la création : une boutique
   déjà installée se répare, et chaque tirage dupliqué depuis le modèle part
   avec les bonnes valeurs.
   ------------------------------------------------------------------------- */

$modele_post = get_page_by_path( $model_slug, OBJECT, 'product' );
$modele      = $modele_post ? wc_get_product( $modele_post->ID ) : null;

/*
 * La classe de livraison « lettre » : c'est elle que `inc/livraison.php`
 * interroge pour décider si l'option courrier a le droit de s'afficher. Un
 * tirage l'a, un original ne l'a pas — il part en colis, suivi et protégé.
 * Créée ici pour que le thème ne dépende pas d'une donnée saisie à la main.
 */
if ( ! get_term_by( 'slug', 'lettre', 'product_shipping_class' ) ) {
	wp_insert_term(
		'Lettre',
		'product_shipping_class',
		array( 'slug' => 'lettre', 'description' => 'Tirages plats, postables en courrier.' )
	);
	WP_CLI::log( 'Classe de livraison « Lettre » créée.' );
}

if ( $modele ) {
	// Remettre les bons termes sur le produit, à la place des éventuels jumeaux.
	wp_set_object_terms( $modele->get_id(), array_values( $term_ids ), $taxonomy );

	// Le modèle est un tirage : tout ce qui en est dupliqué naît postable.
	wp_set_object_terms( $modele->get_id(), 'lettre', 'product_shipping_class' );

	$corrigees = 0;
	$vues      = array();

	foreach ( $modele->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			continue;
		}

		/*
		 * La variation annonce sa taille sous « pa_taille », le nom de la
		 * taxonomie. Une ancienne version l'écrivait sous « taille » : le produit
		 * parent cherchait alors une variation qu'il ne trouvait jamais, le
		 * sélecteur de format restait sans réponse et le tirage était invendable.
		 */
		$slug = get_post_meta( $variation_id, 'attribute_' . $taxonomy, true );
		if ( ! $slug ) {
			$slug = get_post_meta( $variation_id, 'attribute_taille', true );
		}

		/*
		 * Une variation d'un format qui n'existe plus au catalogue — les tailles
		 * en pouces d'avant le passage au A4/A5 — resterait proposée au client
		 * sans poids ni dimensions, donc sans tarif de livraison. Le gabarit
		 * appartient à ce script : il le remet en accord avec les formats.
		 */
		if ( ! isset( $formats[ $slug ] ) ) {
			$variation->delete( true );
			WP_CLI::log( 'Variation retirée du modèle : format « ' . $slug .' » hors catalogue.' );
			continue;
		}

		$vues[] = $slug;

		list( , $poids, $longueur, $largeur, $epaisseur ) = $formats[ $slug ];

		$variation->set_attributes( array( $taxonomy => $slug ) );
		$variation->set_weight( (string) $poids );
		$variation->set_length( (string) $longueur );
		$variation->set_width( (string) $largeur );
		$variation->set_height( (string) $epaisseur );
		$variation->save();

		$corrigees++;
	}

	// Et créer celles qui manquent, pour que le gabarit couvre tous les formats.
	foreach ( $formats as $slug => $format ) {
		if ( in_array( $slug, $vues, true ) ) {
			continue;
		}

		list( $prix, $poids, $longueur, $largeur, $epaisseur ) = $format;

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $modele->get_id() );
		$variation->set_attributes( array( $taxonomy => $slug ) );
		$variation->set_regular_price( (string) $prix );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 20 );
		$variation->set_weight( (string) $poids );
		$variation->set_length( (string) $longueur );
		$variation->set_width( (string) $largeur );
		$variation->set_height( (string) $epaisseur );
		$variation->save();

		WP_CLI::log( 'Variation ajoutée au modèle : ' . $slug );
		$corrigees++;
	}

	wc_delete_product_transients( $modele->get_id() );
	WP_CLI::log( 'Modèle : ' . $corrigees . ' variations avec taille, poids et dimensions.' );
}

WP_CLI::success( 'Boutique structurée : catégories, tailles, modèle de print.' );

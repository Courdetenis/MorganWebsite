<?php
/**
 * Export du site vers Shopify.
 *
 * Lit le WordPress installé et écrit, dans dist/shopify/ :
 *   pages/<lang>/<handle>.html   le corps de chaque page, balisage de blocs rendu
 *   pages.csv                    l'index : clé, langue, handle, titre, gabarit
 *   products.csv                 les produits, au format d'import Shopify
 *   images/                      les fichiers image réellement utilisés
 *   LISEZMOI.txt                 les deux commandes de remplacement à passer
 *
 * Lancer :
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/export-shopify.php
 *
 * Réglages par variables d'environnement :
 *   MORGANE_OUT      dossier de sortie             (défaut /dist/shopify)
 *   MORGANE_PREFIX   préfixe d'URL de la 2e langue (défaut /en-ca)
 *
 * @package Morgane
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

$out    = getenv( 'MORGANE_OUT' ) ?: '/dist/shopify';
$prefix = getenv( 'MORGANE_PREFIX' );
if ( false === $prefix ) {
	$prefix = '/en-ca';
}

foreach ( array( $out, "$out/pages", "$out/pages/fr", "$out/pages/en", "$out/images" ) as $dir ) {
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) && ! is_dir( $dir ) ) {
		WP_CLI::error( "Impossible de créer $dir — le dossier dist/ est-il monté dans le conteneur ?" );
	}
}

/* -------------------------------------------------------------------------
   1. Les pages
   ------------------------------------------------------------------------- */

/*
 * Les pages que WooCommerce se crée pour lui-même n'ont pas à être exportées :
 * Shopify a les siennes (panier, paiement, compte), et elles sont vides de
 * contenu rédigé.
 */
$exclues = array( 'panier', 'cart', 'commande', 'checkout', 'mon-compte', 'my-account', 'boutique', 'shop' );

$pages = get_posts( array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'suppress_filters' => true,
) );

$lang_de = function ( $id ) {
	if ( function_exists( 'pll_get_post_language' ) ) {
		$l = pll_get_post_language( $id, 'slug' );
		if ( $l ) {
			return $l;
		}
	}
	return 'fr';
};

// Table permalien -> chemin Shopify, dans les deux langues.
$map = array();
foreach ( $pages as $p ) {
	if ( in_array( $p->post_name, $exclues, true ) ) {
		continue;
	}
	$lang = $lang_de( $p->ID );
	$base = ( 'fr' === $lang ) ? '' : $prefix;
	$map[ untrailingslashit( get_permalink( $p->ID ) ) ] = $base . '/pages/' . $p->post_name;
}
// L'accueil et la boutique ne sont pas des pages Shopify.
if ( function_exists( 'pll_home_url' ) ) {
	foreach ( array( 'fr' => '/', 'en' => $prefix . '/' ) as $l => $dest ) {
		$map[ untrailingslashit( pll_home_url( $l ) ) ] = $dest;
	}
} else {
	$map[ untrailingslashit( home_url( '/' ) ) ] = '/';
}
if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
	$map[ untrailingslashit( get_permalink( wc_get_page_id( 'shop' ) ) ) ] = '/collections/all';
}

$images = array();
$index  = array( array( 'langue', 'handle', 'titre', 'gabarit', 'fichier' ) );
$n      = 0;

foreach ( $pages as $p ) {
	if ( in_array( $p->post_name, $exclues, true ) ) {
		continue;
	}

	$lang = $lang_de( $p->ID );
	$html = do_blocks( $p->post_content );
	$html = do_shortcode( $html );

	// Liens internes : vers les chemins Shopify.
	foreach ( $map as $de => $vers ) {
		$html = str_replace( array( $de . '/', $de ), $vers, $html );
	}

	/*
	 * Images. Le srcset est retiré : Shopify sert les fichiers déposés dans
	 * « Contenu › Fichiers » tels quels, sans jeu de tailles — un srcset qui
	 * pointe vers des variantes inexistantes ne donnerait que des images
	 * cassées. Le src devient un jeton, remplacé après le dépôt (LISEZMOI).
	 */
	$html = preg_replace( '/\s+(srcset|sizes)="[^"]*"/i', '', $html );

	$html = preg_replace_callback(
		'/(src=")([^"]+)(")/i',
		function ( $m ) use ( &$images ) {
			$url = $m[2];
			if ( false === strpos( $url, '/wp-content/uploads/' ) ) {
				return $m[0];
			}
			$fichier = basename( parse_url( $url, PHP_URL_PATH ) );
			$images[ $fichier ] = $url;
			return $m[1] . 'IMG_BASE/' . $fichier . $m[3];
		},
		$html
	);

	$gabarit = get_post_meta( $p->ID, '_wp_page_template', true );
	$gabarit = ( 'page-no-title' === $gabarit ) ? 'page.sans-titre' : 'page';
	if ( in_array( $p->post_name, array( 'contact', 'contact-us' ), true ) ) {
		$gabarit = 'page.contact';
	}

	$fichier = "pages/$lang/{$p->post_name}.html";
	file_put_contents( "$out/$fichier", $html );
	$index[] = array( $lang, $p->post_name, $p->post_title, $gabarit, $fichier );
	$n++;
}

/* -------------------------------------------------------------------------
   2. Les fichiers image réellement utilisés
   ------------------------------------------------------------------------- */
$racine = wp_get_upload_dir();
$copiees = 0;
foreach ( $images as $fichier => $url ) {
	$chemin = str_replace( $racine['baseurl'], $racine['basedir'], $url );
	if ( is_readable( $chemin ) && copy( $chemin, "$out/images/$fichier" ) ) {
		$copiees++;
	}
}

/* -------------------------------------------------------------------------
   3. Les produits, au format d'import Shopify
   ------------------------------------------------------------------------- */
$lignes = array( array(
	'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Type', 'Tags', 'Published',
	'Option1 Name', 'Option1 Value', 'Variant SKU', 'Variant Grams',
	'Variant Inventory Tracker', 'Variant Inventory Qty', 'Variant Inventory Policy',
	'Variant Fulfillment Service', 'Variant Price', 'Variant Compare At Price',
	'Variant Requires Shipping', 'Variant Taxable', 'Image Src', 'Image Position',
	'Image Alt Text', 'Status',
) );

$produits = 0;
if ( function_exists( 'wc_get_products' ) ) {
	$unite = get_option( 'woocommerce_weight_unit', 'kg' );
	$en_g  = array( 'g' => 1, 'kg' => 1000, 'lbs' => 453.592, 'oz' => 28.3495 );
	$coef  = isset( $en_g[ $unite ] ) ? $en_g[ $unite ] : 1000;

	foreach ( wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) ) as $prod ) {
		$handle = $prod->get_slug();
		$poids  = $prod->get_weight();
		$grammes = $poids ? (int) round( (float) $poids * $coef ) : 0;

		$cats = array();
		foreach ( wp_get_post_terms( $prod->get_id(), 'product_cat', array( 'fields' => 'names' ) ) as $c ) {
			$cats[] = $c;
		}

		$ids_img = array_merge(
			$prod->get_image_id() ? array( $prod->get_image_id() ) : array(),
			$prod->get_gallery_image_ids()
		);
		$urls_img = array();
		foreach ( $ids_img as $iid ) {
			$u = wp_get_attachment_url( $iid );
			if ( $u ) {
				$f = basename( parse_url( $u, PHP_URL_PATH ) );
				$images[ $f ] = $u;
				$urls_img[]   = array( 'IMG_BASE/' . $f, get_post_meta( $iid, '_wp_attachment_image_alt', true ) );
			}
		}

		$premiere = array_shift( $urls_img );

		$lignes[] = array(
			$handle,
			$prod->get_name(),
			$prod->get_description(),
			'Morgane Cachia',
			$prod->get_type() === 'variable' ? 'Œuvre' : 'Œuvre',
			implode( ', ', $cats ),
			'TRUE',
			'Title',
			'Default Title',
			$prod->get_sku(),
			$grammes,
			'shopify',
			$prod->get_manage_stock() ? (int) $prod->get_stock_quantity() : 1,
			'deny',
			'manual',
			$prod->get_regular_price(),
			$prod->get_sale_price(),
			'TRUE',
			'TRUE',
			$premiere ? $premiere[0] : '',
			$premiere ? 1 : '',
			$premiere ? $premiere[1] : '',
			'active',
		);

		// Les images suivantes : une ligne par image, handle seul.
		$pos = 2;
		foreach ( $urls_img as $img ) {
			$vide = array_fill( 0, count( $lignes[0] ), '' );
			$vide[0]  = $handle;
			$vide[19] = $img[0];
			$vide[20] = $pos++;
			$vide[21] = $img[1];
			$lignes[] = $vide;
		}
		$produits++;
	}

	// Les images de produits arrivées après la première boucle.
	foreach ( $images as $fichier => $url ) {
		if ( file_exists( "$out/images/$fichier" ) ) {
			continue;
		}
		$chemin = str_replace( $racine['baseurl'], $racine['basedir'], $url );
		if ( is_readable( $chemin ) && copy( $chemin, "$out/images/$fichier" ) ) {
			$copiees++;
		}
	}
}

$csv = function ( $chemin, array $lignes ) {
	$fh = fopen( $chemin, 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" ); // BOM : Excel et Shopify lisent alors l'UTF-8.
	foreach ( $lignes as $l ) {
		fputcsv( $fh, $l );
	}
	fclose( $fh );
};

$csv( "$out/pages.csv", $index );
$csv( "$out/products.csv", $lignes );

file_put_contents( "$out/LISEZMOI.txt", <<<TXT
Export vers Shopify — {$n} pages, {$produits} produits, {$copiees} images.

1. Déposer TOUT le contenu de images/ dans Shopify : Contenu › Fichiers.
2. Ouvrir n'importe quel fichier déposé, copier le début de son adresse,
   jusqu'au dernier « / » inclus. Par exemple :
     https://cdn.shopify.com/s/files/1/0123/4567/8901/files/
3. Remplacer le jeton IMG_BASE/ par cette adresse, partout :

   cd dist/shopify
   PREFIXE='https://cdn.shopify.com/s/files/1/0123/4567/8901/files/'
   grep -rl 'IMG_BASE/' . | xargs sed -i "s|IMG_BASE/|\$PREFIXE|g"

4. products.csv s'importe dans Produits › Importer.
5. Les fichiers de pages/fr/ se collent dans Shopify en mode HTML
   (Contenu › Pages › la page › bouton <> de l'éditeur).
   Ceux de pages/en/ se collent dans Traduire et adapter, même bouton.

Le gabarit à choisir pour chaque page est dans pages.csv.
TXT
);

WP_CLI::success( "Export terminé : $n pages, $produits produits, $copiees images -> $out" );

<?php
/**
 * Boutique : les retouches d'interface de WooCommerce.
 *
 * Les flèches du champ quantité sont dessinées par le navigateur, pas par le
 * CSS. Même en lui déclarant le bon `color-scheme`, elles restent minuscules et
 * étrangères à une page dessinée au trait. On les remplace par deux boutons
 * ronds, qui parlent la même langue que le reste des commandes du site.
 *
 * Le balisage est posé par les crochets de WooCommerce plutôt que par une copie
 * du gabarit : une surcharge de `quantity-input.php` figerait une version du
 * fichier qu'il faudrait resuivre à chaque mise à jour de l'extension.
 *
 * Le comportement vit dans `assets/js/app.js`, l'habillage dans `app.css`.
 * Sans JavaScript, les boutons ne font rien mais le champ reste saisissable —
 * on ne perd donc aucune fonction, seulement le confort.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Un des deux boutons de quantité.
 *
 * `type="button"` n'est pas décoratif : sans lui, un bouton dans un formulaire
 * vaut « envoyer », et cliquer sur « + » ajouterait l'article au panier.
 *
 * @param string $sens 'down' ou 'up'.
 */
function morgane_bouton_quantite( $sens ) {
	$libelle = ( 'up' === $sens )
		? morgane_t( array( 'fr' => 'Augmenter la quantité', 'en' => 'Increase quantity' ) )
		: morgane_t( array( 'fr' => 'Diminuer la quantité',  'en' => 'Decrease quantity' ) );

	printf(
		'<button class="qty-step" type="button" data-qty-step="%s" aria-label="%s"><span aria-hidden="true">%s</span></button>',
		esc_attr( $sens ),
		esc_attr( $libelle ),
		( 'up' === $sens ) ? '+' : '&minus;'
	);
}

add_action( 'woocommerce_before_quantity_input_field', function () {
	morgane_bouton_quantite( 'down' );
} );

add_action( 'woocommerce_after_quantity_input_field', function () {
	morgane_bouton_quantite( 'up' );
} );

/**
 * Les pages de WooCommerce dans la langue de la page.
 *
 * Boutique, Panier, Commande et Mon compte sont des pages uniques : Polylang
 * gratuit ne les traduit pas, et leur titre restait français sur tout le site
 * anglais — « Panier » au-dessus d'un tableau dont les colonnes disaient
 * « Subtotal ».
 *
 * Une seule table de libellés, lue par les trois filtres ci-dessous : le titre
 * d'archive de la boutique, le fil d'Ariane, et le titre des pages. Les trois
 * ne peuvent donc pas se contredire.
 *
 * @return array<int, array<string, string>> Identifiant de page => libellés.
 */
function morgane_libelles_pages_wc() {
	static $table = null;

	if ( null !== $table ) {
		return $table;
	}

	$table = array();

	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $table;
	}

	$libelles = array(
		'shop'      => array( 'fr' => 'Boutique',   'en' => 'Shop' ),
		'cart'      => array( 'fr' => 'Panier',     'en' => 'Cart' ),
		'checkout'  => array( 'fr' => 'Commande',   'en' => 'Checkout' ),
		'myaccount' => array( 'fr' => 'Mon compte', 'en' => 'My account' ),
	);

	foreach ( $libelles as $cle => $textes ) {
		$id = (int) wc_get_page_id( $cle );
		if ( $id > 0 ) {
			$table[ $id ] = $textes;
		}
	}

	return $table;
}

/**
 * Le libellé traduit d'une page de WooCommerce, ou '' si ce n'en est pas une.
 */
function morgane_libelle_page_wc( $id ) {
	$table = morgane_libelles_pages_wc();
	return isset( $table[ $id ] ) ? morgane_t( $table[ $id ] ) : '';
}

/**
 * Le titre affiché de ces pages.
 *
 * `the_title` s'exécute partout — menus, flux, administration. Trois garde-fous
 * le confinent à ce qu'on vise : jamais dans l'administration, jamais dans une
 * requête REST (l'éditeur de blocs y liste les pages, et Morgane doit y lire le
 * nom qu'elle a saisi), et seulement pour les quatre identifiants connus.
 *
 * Le nom des pages n'est donc pas modifié : seul son affichage public l'est.
 */
add_filter( 'the_title', function ( $titre, $id = 0 ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! $id ) {
		return $titre;
	}

	$traduit = morgane_libelle_page_wc( (int) $id );

	return '' !== $traduit ? $traduit : $titre;
}, 10, 2 );

/**
 * Le titre de la boutique dans la langue de la page.
 *
 * La page « Boutique » est unique : Polylang gratuit ne traduit pas les pages de
 * WooCommerce, et son titre restait donc français sur tout le site anglais —
 * « Boutique » au-dessus d'une grille dont les boutons disaient « Add to cart ».
 *
 * On ne touche qu'au titre affiché, jamais à la page elle-même : son nom dans
 * l'administration reste « Boutique », et c'est lui que Morgane cherche.
 */
function morgane_titre_boutique() {
	$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$traduit = $id > 0 ? morgane_libelle_page_wc( $id ) : '';

	return '' !== $traduit ? $traduit : morgane_t( array( 'fr' => 'Boutique', 'en' => 'Shop' ) );
}

add_filter( 'woocommerce_page_title', function ( $titre ) {
	/*
	 * `is_shop()` seulement, et non les archives de catégorie : sur « Prints »,
	 * c'est le nom de la catégorie qu'il faut afficher, pas « Boutique ».
	 */
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return morgane_titre_boutique();
	}
	return $titre;
} );

/**
 * Le même nom dans le fil d'Ariane.
 *
 * Le fil est un tableau de couples [ libellé, adresse ]. On reconnaît la miette
 * de la boutique par son adresse — et, quand elle désigne la page courante, elle
 * n'a pas d'adresse : on la reconnaît alors par son libellé brut.
 */
add_filter( 'woocommerce_get_breadcrumb', function ( $miettes ) {
	$boutique = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	if ( $boutique <= 0 ) {
		return $miettes;
	}

	$permalien = untrailingslashit( (string) get_permalink( $boutique ) );
	$brut      = get_the_title( $boutique );

	foreach ( $miettes as $i => $miette ) {
		$par_adresse = ! empty( $miette[1] ) && untrailingslashit( $miette[1] ) === $permalien;
		$par_libelle = isset( $miette[0] ) && $miette[0] === $brut;

		if ( $par_adresse || $par_libelle ) {
			$miettes[ $i ][0] = morgane_titre_boutique();
		}
	}

	return $miettes;
} );

/**
 * Les titres écrits en dur dans la page « Panier ».
 *
 * Le bloc « Panier » de WooCommerce n'est pas entièrement dynamique : trois de
 * ses titres sont des blocs `core/heading` ordinaires, enregistrés dans le
 * contenu de la page au moment où WooCommerce la crée, dans la langue active ce
 * jour-là. Ce sont du contenu, pas de l'interface : ni le paquet de langue ni
 * `the_title` ne les touchent, et ils transparaissent donc dans l'autre langue —
 * « Votre panier est actuellement vide ! » au milieu d'une page anglaise, ou
 * l'inverse selon la langue de l'installation.
 *
 * Polylang gratuit ne traduisant pas les pages de WooCommerce, il n'y a qu'une
 * page Panier pour les deux langues : les corriger à la main dans l'éditeur ne
 * ferait que choisir laquelle des deux langues serait fausse. On les réécrit
 * donc au rendu.
 *
 * Le repérage se fait par bloc parent et par position, jamais sur le texte
 * lui-même : une reformulation de WooCommerce, ou une locale un peu différente
 * (fr_CA contre fr_FR), casserait une comparaison de chaînes.
 *
 * @param array $bloc Bloc analysé.
 * @return array<int, array<string, string>> Libellés attendus, dans l'ordre des
 *                                            titres du bloc. Vide ailleurs.
 */
function morgane_titres_bloc_panier( $bloc ) {
	$nom = isset( $bloc['blockName'] ) ? $bloc['blockName'] : '';

	if ( 'woocommerce/empty-cart-block' === $nom ) {
		return array(
			array( 'fr' => 'Votre panier est actuellement vide !', 'en' => 'Your cart is currently empty!' ),
			array( 'fr' => 'Nouveau dans la boutique',             'en' => 'New in store' ),
		);
	}

	/*
	 * Les ventes croisées sont une collection de produits parmi d'autres — même
	 * bloc que la grille de la boutique. C'est l'attribut `collection` qui les
	 * distingue, pas le nom du bloc.
	 */
	if ( 'woocommerce/product-collection' === $nom
		&& isset( $bloc['attrs']['collection'] )
		&& 'woocommerce/product-collection/cross-sells' === $bloc['attrs']['collection']
	) {
		return array(
			array( 'fr' => 'Vous aimerez peut-être…', 'en' => 'You may be interested in…' ),
		);
	}

	return array();
}

add_filter( 'render_block', function ( $html, $bloc ) {
	$titres = morgane_titres_bloc_panier( $bloc );
	if ( ! $titres ) {
		return $html;
	}

	$rang = 0;

	/*
	 * `wp-block-heading` restreint la reprise aux titres saisis dans l'éditeur.
	 * Les deux blocs visés contiennent aussi une grille de produits, dont les
	 * titres sortent en `wp-block-post-title` : ils gardent le nom de l'œuvre,
	 * qui n'a pas à être traduit.
	 */
	return preg_replace_callback(
		'#(<h2\b[^>]*\bclass="[^"]*\bwp-block-heading\b[^"]*"[^>]*>)(.*?)(</h2>)#s',
		function ( $trouve ) use ( $titres, &$rang ) {
			$i = $rang++;

			return isset( $titres[ $i ] )
				? $trouve[1] . esc_html( morgane_t( $titres[ $i ] ) ) . $trouve[3]
				: $trouve[0];
		},
		$html
	);
}, 10, 2 );

/* -------------------------------------------------------------------------
   Les conditions de vente, au moment de payer.
   ------------------------------------------------------------------------- */

/*
 * Une case à cocher, et non une simple phrase.
 *
 * Les conditions générales de vente disent que l'acceptation se fait par le
 * clic sur « Commander », après avoir coché une case prévue à cet effet. Le bloc
 * « Conditions » de la page Commande n'affiche par défaut qu'une phrase — « En
 * passant commande, vous acceptez… » — sans case. La case est un attribut du
 * bloc, enregistré dans le contenu de la page Commande : on la force au rendu
 * plutôt que de réécrire une page qui vit dans la base, où une modification
 * faite depuis l'éditeur la ferait disparaître sans bruit.
 *
 * WooCommerce recopie les attributs de ses blocs en `data-*` sur leur balisage,
 * et c'est là que le script de la page les relit : les changer ici suffit.
 * La commande « classique » (code court) n'est pas concernée : la case y
 * apparaît d'elle-même dès qu'une page de conditions est désignée.
 */
add_filter( 'render_block_data', function ( $bloc ) {
	if ( isset( $bloc['blockName'] ) && 'woocommerce/checkout-terms-block' === $bloc['blockName'] ) {
		$bloc['attrs']['checkbox'] = true;
	}
	return $bloc;
} );

/**
 * Les pages de conditions et de confidentialité dans la langue de la page.
 *
 * WooCommerce les connaît par un identifiant unique, celui de la page
 * française : sur `/en/commande/`, « Terms and Conditions » menait au texte
 * français. On rend la traduction que Polylang connaît, dans la langue lue dans
 * l'adresse — la page Commande est rattachée au français, et Polylang y répond
 * « fr » même sous `/en/` (voir morgane_lang()).
 *
 * Jamais dans l'administration : les réglages de WooCommerce doivent y montrer
 * la page choisie, pas sa traduction.
 *
 * @param int|string $id Identifiant de page, tel que WooCommerce le lit.
 */
function morgane_page_legale_traduite( $id ) {
	if ( is_admin() || ! $id || ! function_exists( 'pll_get_post' ) ) {
		return $id;
	}
	$traduite = pll_get_post( (int) $id, morgane_lang() );
	return $traduite ? (int) $traduite : $id;
}
add_filter( 'woocommerce_get_terms_page_id', 'morgane_page_legale_traduite' );
add_filter( 'woocommerce_privacy_policy_page_id', 'morgane_page_legale_traduite' );

/* -------------------------------------------------------------------------
   La remise en main propre, au Québec seulement.
   ------------------------------------------------------------------------- */

/*
 * Morgane remet ses œuvres en main propre au Québec ; ailleurs, l'option n'a
 * pas de sens. Le tarif de retrait n'est donc calculé que pour une adresse au
 * Québec, et le bouton « Remise en main propre » de la page Commande disparaît
 * avec lui : WooCommerce ne l'affiche que si chaque colis peut être retiré.
 * Une adresse pas encore saisie ne compte pas comme québécoise.
 *
 * Le tri se fait ici, au calcul des tarifs, et non dans la page : une commande
 * qui passerait outre l'interface ne trouverait pas de retrait à choisir. En
 * mode retrait, WooCommerce prend l'adresse de facturation comme adresse de
 * livraison — c'est alors elle qui décide (voir aussi assets/js/app.js, § 7).
 */
add_filter( 'woocommerce_shipping_pickup_location_is_available', function ( $disponible, $colis ) {
	$pays     = $colis['destination']['country'] ?? '';
	$province = $colis['destination']['state'] ?? '';

	return $disponible && 'CA' === $pays && 'QC' === $province;
}, 10, 2 );

/* -------------------------------------------------------------------------
   Les deux rayons : les tirages d'un côté, les pièces uniques de l'autre.
   ------------------------------------------------------------------------- */

/**
 * Les rayons de la boutique, dans la langue active.
 *
 * Une seule table décide de leur nombre, de leur ordre et de leurs libellés.
 * Le déroulant du menu et la vitrine la lisent tous les deux : ils ne peuvent
 * donc pas se contredire, et renommer un rayon se fait à un seul endroit.
 *
 * Un rayon dont la catégorie n'existe pas est simplement absent de la liste —
 * le thème ne pose jamais un lien qui mènerait à une page vide.
 *
 * @return array<int, array<string, mixed>>
 */
function morgane_rayons_boutique() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$definis = array(
		'prints'    => array(
			'label' => array( 'fr' => 'Prints',    'en' => 'Prints' ),
			'note'  => array( 'fr' => 'Tirages d’art', 'en' => 'Fine art prints' ),
		),
		'originaux' => array(
			'label' => array( 'fr' => 'Originaux', 'en' => 'Originals' ),
			'note'  => array( 'fr' => 'Pièces uniques', 'en' => 'One-of-a-kind pieces' ),
		),
	);

	$cache = array();

	foreach ( $definis as $slug => $rayon ) {
		$url = morgane_categorie_url( $slug );
		if ( '' !== $url ) {
			$cache[] = array(
				'slug'  => $slug,
				'url'   => $url,
				'label' => $rayon['label'],
				'note'  => $rayon['note'],
			);
		}
	}

	return $cache;
}

/**
 * La boutique s'ouvre-t-elle sur ses rayons plutôt que sur le catalogue ?
 *
 * @param WP_Query|null $query Requête à examiner, à `pre_get_posts` — où les
 *                             marqueurs globaux ne sont pas encore posés.
 */
function morgane_boutique_vitrine_demandee( $query = null ) {
	// Sans deux rayons, la vitrine n'a rien à présenter : la boutique reste
	// une liste d'œuvres, comme avant.
	if ( count( morgane_rayons_boutique() ) < 2 ) {
		return false;
	}

	// Échappatoire : « ?tout=1 » rend le catalogue entier, tous rayons confondus.
	if ( isset( $_GET['tout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}

	if ( $query instanceof WP_Query ) {
		/*
		 * `is_post_type_archive` distingue la boutique des archives de
		 * catégorie : « Prints » et « Originaux » doivent, elles, continuer
		 * d'afficher leurs œuvres — c'est tout l'intérêt du déroulant.
		 */
		return $query->is_main_query()
			&& $query->is_post_type_archive( 'product' )
			&& ! $query->is_search();
	}

	return function_exists( 'is_shop' ) && is_shop() && ! is_search();
}

/*
 * On vide la requête plutôt que de démonter la boucle. WooCommerce bascule
 * alors de lui-même sur `woocommerce_no_products_found`, un point d'entrée
 * prévu pour ça — et le compteur de résultats comme le tri, qui vivent dans la
 * branche « il y a des œuvres », ne s'affichent pas non plus. C'est ce qu'on
 * veut : une vitrine n'a rien à trier.
 */
add_action( 'woocommerce_product_query', function ( $query ) {
	/*
	 * Polylang continue de filtrer par langue la requête principale d'une
	 * archive de taxonomie produit, même après le retrait de `product` et
	 * `product_cat` de ses types traduits (voir install/pll-cpt.php et le
	 * verrou posé dans inc/produits.php) : `PLL_Query::filter_query()` décide
	 * s'il faut filtrer en regardant le nom de la taxonomie interrogée, pas si
	 * elle est encore gérée par Polylang. Résultat vérifié : `/en/product-
	 * category/prints/` ne trouvait aucune œuvre, alors qu'aucune n'est jamais
	 * rattachée à l'anglais — toutes vivent en français, texte anglais compris
	 * dans leurs métas (inc/produits.php).
	 *
	 * Même remède que celui déjà posé « par prudence » dans
	 * `morgane_categorie_terme()` (inc/i18n.php) pour `get_terms()`. L'archive
	 * française, elle, était déjà filtrée par Polylang sur `lang => 'fr'` —
	 * mais restait juste, puisque les cinq produits sont tous en français : le
	 * filtre ne retirait donc rien. Vider `lang` ici ne change rien pour elle,
	 * seulement pour l'anglais, où il retirait tout.
	 */
	if ( is_product_taxonomy() ) {
		$query->set( 'lang', '' );
	}

	if ( morgane_boutique_vitrine_demandee( $query ) ) {
		$query->set( 'post__in', array( 0 ) );
	}
} );

add_action( 'wp', function () {
	if ( ! morgane_boutique_vitrine_demandee() ) {
		return;
	}

	// Sans ce retrait, WooCommerce annoncerait « aucun produit ne correspond ».
	remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
	add_action( 'woocommerce_no_products_found', 'morgane_boutique_vitrine' );
} );

/**
 * L'image d'un rayon : celle de la catégorie, sinon celle d'une de ses œuvres.
 *
 * Morgane n'a pas à poser une image de catégorie pour que la vitrine tienne
 * debout — et si elle en pose une, c'est elle qui gagne.
 */
function morgane_rayon_image( $slug ) {
	$terme = morgane_categorie_terme( $slug );
	if ( ! $terme ) {
		return '';
	}

	$image = (int) get_term_meta( $terme->term_id, 'thumbnail_id', true );

	if ( ! $image ) {
		$oeuvres = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $terme->term_id,
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ),
			),
		) );

		if ( $oeuvres ) {
			$image = (int) get_post_thumbnail_id( $oeuvres[0] );
		}
	}

	if ( ! $image ) {
		// Rien à montrer : le cadre garde son dégradé, comme une carte sans photo.
		return '';
	}

	/*
	 * `alt` vide, volontairement : le nom du rayon est écrit juste dessous,
	 * dans le même lien. Le répéter ferait entendre deux fois la même chose.
	 */
	return wp_get_attachment_image( $image, 'woocommerce_single', false, array(
		'alt'   => '',
		'sizes' => '(max-width: 780px) 100vw, 390px',
	) );
}

/**
 * La vitrine : les rayons en cartes, et de quoi voir quand même tout d'un coup.
 */
function morgane_boutique_vitrine() {
	$cartes = '';

	foreach ( morgane_rayons_boutique() as $rayon ) {
		$cartes .= sprintf(
			'<div class="nav-card"><a href="%s">'
			. '<span class="nav-card__frame">%s</span>'
			. '<span class="nav-card__label">%s</span>'
			. '<span class="nav-card__note">%s</span>'
			. '</a></div>',
			esc_url( $rayon['url'] ),
			morgane_rayon_image( $rayon['slug'] ),
			esc_html( morgane_t( $rayon['label'] ) ),
			esc_html( morgane_t( $rayon['note'] ) )
		);
	}

	printf(
		'<div class="nav-cards alignwide boutique-rayons">%s</div>'
		. '<p class="boutique-rayons__tout"><a href="%s">%s</a></p>',
		$cartes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé pièce par pièce ci-dessus.
		esc_url( add_query_arg( 'tout', '1', morgane_shop_url() ) ),
		esc_html( morgane_t( array( 'fr' => 'Voir tout le catalogue', 'en' => 'Browse everything' ) ) )
	);
}

/**
 * Le nom d'un rayon dans la langue active.
 *
 * Les catégories ne sont plus traduites par Polylang : il n'existe qu'un terme
 * « Originaux », dont le nom doit se lire « Originals » sur le site anglais.
 * Les libellés viennent de `morgane_rayons_boutique()`, déjà seule source de
 * vérité pour le menu et la vitrine.
 */
function morgane_nom_categorie( $terme ) {
	if ( ! ( $terme instanceof WP_Term ) ) {
		return '';
	}

	foreach ( morgane_rayons_boutique() as $rayon ) {
		if ( $rayon['slug'] === $terme->slug ) {
			return morgane_t( $rayon['label'] );
		}
	}

	return $terme->name;
}

/*
 * `product_cat` est une taxonomie personnalisée, pas la taxonomie native
 * « category » : `get_the_archive_title()` (titre de l'archive) passe par
 * `single_term_title()`, qui applique le filtre `single_term_title` — pas
 * `single_cat_title`, réservé à la taxonomie « category ». On ne touche pas à
 * `get_term` lui-même : il s'exécute dans l'administration comme ailleurs, et
 * Morgane doit y lire le nom qu'elle a saisi.
 *
 * Le fil d'Ariane de WooCommerce, lui, NE passe PAS par `single_term_title()` :
 * `WC_Breadcrumb::add_crumbs_product_category()` (`class-wc-breadcrumb.php`)
 * lit `$current_term->name` directement. Une phrase antérieure de ce
 * commentaire affirmait le contraire — c'était faux, vérifié dans le source de
 * l'extension. Le fil réclame donc son propre filtre, posé plus bas sur
 * `woocommerce_get_breadcrumb`.
 */
add_filter( 'single_term_title', function ( $titre ) {
	if ( is_admin() || ! is_tax( 'product_cat' ) ) {
		return $titre;
	}

	$terme = get_queried_object();

	return ( $terme instanceof WP_Term ) ? morgane_nom_categorie( $terme ) : $titre;
} );

/**
 * Le nom de la catégorie dans le fil d'Ariane.
 *
 * Un second filtre sur `woocommerce_get_breadcrumb`, plutôt qu'une extension
 * de celui qui traduit la miette de la page Boutique plus haut : les deux
 * repèrent leur miette par un critère différent (une page WooCommerce unique,
 * par son adresse ou son libellé brut ; ici, un terme de taxonomie, par la
 * page couramment affichée) et n'ont rien d'autre en commun. Les mélanger
 * ferait porter à une même fonction deux responsabilités sans rapport. Les
 * deux filtres reçoivent et renvoient le même tableau l'un après l'autre :
 * ils cohabitent sans se défaire.
 *
 * On reconnaît la miette par son libellé brut (le nom du terme, tel que
 * `WC_Breadcrumb` l'a posé) plutôt que par son adresse : sur l'archive de la
 * catégorie, cette miette désigne la page courante et porte pourtant un lien
 * (contrairement à celle de la boutique) — comparer les libellés est donc à
 * la fois suffisant et plus simple ici.
 */
add_filter( 'woocommerce_get_breadcrumb', function ( $miettes ) {
	if ( ! is_tax( 'product_cat' ) ) {
		return $miettes;
	}

	$terme = get_queried_object();
	if ( ! ( $terme instanceof WP_Term ) ) {
		return $miettes;
	}

	$brut = $terme->name;

	foreach ( $miettes as $i => $miette ) {
		if ( isset( $miette[0] ) && $miette[0] === $brut ) {
			$miettes[ $i ][0] = morgane_nom_categorie( $terme );
		}
	}

	return $miettes;
} );

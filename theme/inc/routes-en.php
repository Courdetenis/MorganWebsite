<?php
/**
 * Les adresses anglaises des produits et de leurs catégories.
 *
 * En sortant `product`, `product_cat` et `product_tag` de Polylang — ce qui
 * donne une fiche unique, donc un seul stock — on lui a aussi retiré les règles
 * de réécriture qui faisaient exister les adresses préfixées. Polylang ne
 * réécrit que ce qu'il traduit.
 *
 * Conséquence constatée en production : `/en/product/…` et
 * `/en/product-category/…` répondaient 404, alors que le thème fabrique
 * précisément ces liens. Le défaut ne s'était pas vu en développement parce que
 * les anciennes règles, écrites du temps où Polylang routait les produits,
 * traînaient encore en base — une purge des permaliens les révélait aussitôt.
 *
 * On réécrit donc nous-mêmes ce que Polylang ne fait plus, pour chaque langue
 * autre que celle qui vit à la racine.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Les langues préfixées, c'est-à-dire toutes sauf celle posée à la racine.
 *
 * @return array<int, string>
 */
function morgane_langues_prefixees() {
	if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_default_language' ) ) {
		return array();
	}

	$langues = (array) pll_languages_list();
	$defaut  = pll_default_language();

	$options = get_option( 'polylang' );
	$cachee  = is_array( $options ) || $options instanceof ArrayAccess
		? ! empty( $options['hide_default'] )
		: true;

	// Sans masquage de la langue par défaut, elle aussi porte un préfixe.
	return $cachee
		? array_values( array_diff( $langues, array( $defaut ) ) )
		: $langues;
}

add_action( 'init', function () {
	$langues = morgane_langues_prefixees();
	if ( ! $langues ) {
		return;
	}

	// Les bases viennent des réglages de permaliens de WooCommerce : elles sont
	// traduites sur un site français (« categorie-produit »), et les coder en
	// dur enverrait vers des adresses qui n'existent pas.
	$permaliens = (array) get_option( 'woocommerce_permalinks' );
	$produit    = ! empty( $permaliens['product_base'] ) ? trim( $permaliens['product_base'], '/' ) : 'product';
	$categorie  = ! empty( $permaliens['category_base'] ) ? trim( $permaliens['category_base'], '/' ) : 'product-category';
	$etiquette  = ! empty( $permaliens['tag_base'] ) ? trim( $permaliens['tag_base'], '/' ) : 'product-tag';

	/*
	 * La page Boutique doit se rendre comme l'archive des produits, pas comme
	 * une page. Sans cette règle, `/en/boutique/` affichait le gabarit de page :
	 * un titre « Shop » et rien dessous — ni vitrine, ni œuvres — parce que la
	 * boucle produit n'y tourne jamais.
	 */
	$boutique = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$slug_boutique = $boutique > 0 ? get_post_field( 'post_name', $boutique ) : '';

	foreach ( $langues as $langue ) {
		$p = preg_quote( $langue, '/' );

		if ( '' !== $slug_boutique ) {
			add_rewrite_rule(
				'^' . $p . '/' . preg_quote( $slug_boutique, '/' ) . '/page/?([0-9]{1,})/?$',
				'index.php?post_type=product&paged=$matches[1]&lang=' . $langue,
				'top'
			);
			add_rewrite_rule(
				'^' . $p . '/' . preg_quote( $slug_boutique, '/' ) . '/?$',
				'index.php?post_type=product&lang=' . $langue,
				'top'
			);
		}

		// La fiche produit. `[^/]+` et non `.+` : un produit n'a pas de parent.
		add_rewrite_rule(
			'^' . $p . '/' . preg_quote( $produit, '/' ) . '/([^/]+)/?$',
			'index.php?product=$matches[1]&lang=' . $langue,
			'top'
		);

		/*
		 * Les catégories sont hiérarchiques : `(.+?)` laisse passer
		 * « prints/encadres ». La pagination est déclarée séparément, sinon
		 * « /page/2/ » serait avalé par le slug du terme.
		 */
		foreach ( array( $categorie => 'product_cat', $etiquette => 'product_tag' ) as $base => $taxonomie ) {
			add_rewrite_rule(
				'^' . $p . '/' . preg_quote( $base, '/' ) . '/(.+?)/page/?([0-9]{1,})/?$',
				'index.php?' . $taxonomie . '=$matches[1]&paged=$matches[2]&lang=' . $langue,
				'top'
			);
			add_rewrite_rule(
				'^' . $p . '/' . preg_quote( $base, '/' ) . '/(.+?)/?$',
				'index.php?' . $taxonomie . '=$matches[1]&lang=' . $langue,
				'top'
			);
		}
	}
}, 20 );

/**
 * `lang` doit survivre au tri des variables de requête, sinon Polylang ne voit
 * pas la langue que nos règles viennent de poser et sert le site en français.
 */
add_filter( 'query_vars', function ( $vars ) {
	if ( ! in_array( 'lang', $vars, true ) ) {
		$vars[] = 'lang';
	}
	return $vars;
} );

/**
 * L'adresse de l'archive des produits, dans la langue active.
 *
 * WooCommerce redirige la page Boutique vers `get_post_type_archive_link()`.
 * `product` n'étant plus traduit, Polylang n'y met plus de préfixe : partir de
 * `/en/boutique/` menait donc à un 301 vers `/boutique/`, et la boutique
 * anglaise était injoignable. Ce n'était pas la redirection canonique de
 * WordPress — la couper n'y changeait rien — mais bien celle de WooCommerce.
 */
add_filter( 'post_type_archive_link', function ( $lien, $type ) {
	if ( 'product' !== $type || ! morgane_produit_en_actif() ) {
		return $lien;
	}

	$racine = untrailingslashit( morgane_lang_root( morgane_lang() ) );
	$chemin = wp_make_link_relative( $lien );

	return $racine . $chemin;
}, 10, 2 );

/**
 * Garder le préfixe de langue sur les pages de WooCommerce.
 *
 * Boutique, Panier, Commande et Mon compte sont des pages uniques rattachées au
 * français : Polylang gratuit ne les traduit pas. Sa propre canonisation
 * (`PLL_Canonical::check_canonical_url`) juge donc `/en/boutique/` illégitime
 * et renvoie un 301 vers `/boutique/`. Le visiteur anglophone repartait en
 * français, et la boutique anglaise était injoignable.
 *
 * Ce n'est ni la canonisation de WordPress ni celle de WooCommerce — les couper
 * n'y changeait rien. C'est le filtre de Polylang qu'il faut désarmer, et pour
 * ces seules pages : tout le reste continue d'être canonisé normalement.
 */
add_filter( 'pll_check_canonical_url', function ( $redirection, $langue ) {
	if ( ! function_exists( 'is_shop' ) ) {
		return $redirection;
	}

	$pages_wc = is_shop()
		|| ( function_exists( 'is_cart' ) && is_cart() )
		|| ( function_exists( 'is_checkout' ) && is_checkout() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() );

	return $pages_wc ? false : $redirection;
}, 10, 2 );

<?php
/**
 * Ne pas donner les noms de compte.
 *
 * Forcer un mot de passe demande deux choses : un identifiant et un mot de
 * passe. WordPress livre le premier gratuitement, par quatre chemins distincts
 * — et un robot les essaie tous avant de commencer à deviner. Les fermer ne
 * remplace pas la double authentification, mais retire à l'attaquant la moitié
 * du travail, et fait disparaître la boutique des balayages automatisés qui
 * cherchent des cibles faciles.
 *
 * Rien ici ne gêne une personne connectée : l'administration continue de voir
 * ses utilisateurs normalement.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. L'API REST publiait la liste des comptes.
 *
 * `/wp-json/wp/v2/users` répondait 200 à n'importe qui, avec le nom du compte
 * administrateur. On retire la route aux visiteurs non connectés seulement :
 * l'éditeur de blocs, qui s'en sert pour la liste des auteurs, continue de
 * fonctionner puisqu'il n'y accède que connecté.
 */
add_filter( 'rest_endpoints', function ( $routes ) {
	if ( is_user_logged_in() ) {
		return $routes;
	}

	unset( $routes['/wp/v2/users'] );
	unset( $routes['/wp/v2/users/(?P<id>[\d]+)'] );

	return $routes;
} );

/**
 * 2. « /?author=1 » redirigeait vers « /author/<nom>/ ».
 *
 * La redirection elle-même révélait le nom, avant même l'affichage de la page.
 * On répond 404 au lieu de rediriger — et à une priorité plus haute que
 * `redirect_canonical`, qui s'exécute sur ce même crochet en priorité 10 :
 * arriver après lui laisserait le nom fuiter dans l'en-tête `Location`.
 *
 * Le site n'a pas de blogue et n'affiche aucune signature d'auteur : ces
 * archives ne servent à personne.
 */
add_action( 'template_redirect', function () {
	if ( ! is_author() || is_user_logged_in() ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 1 );

/**
 * 3. Le plan du site publiait un index des auteurs.
 *
 * `wp-sitemap.xml` annonçait « wp-sitemap-users-1.xml », qui listait
 * « /author/<nom>/ ». Autant l'écrire dans le pied de page. On retire le
 * fournisseur : les pages et les produits, eux, restent bien référencés.
 */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $nom ) {
	return ( 'users' === $nom ) ? false : $provider;
}, 10, 2 );

/**
 * 4. La page de connexion disait si le compte existait.
 *
 * « l'identifiant X n'est pas inscrit sur ce site » d'un côté, « ce mot de passe
 * ne correspond pas à l'identifiant X » de l'autre : de quoi trier une liste de
 * noms sans jamais entrer. Un seul message pour les deux cas, et l'essai ne
 * renseigne plus.
 *
 * La contrepartie est assumée : en cas de faute de frappe sur l'identifiant, on
 * ne sait plus lequel des deux champs est en cause. C'est le prix, et il est
 * bas — vous vous connectez avec un gestionnaire de mots de passe.
 */
add_filter( 'login_errors', function () {
	return morgane_t( array(
		'fr' => 'Identifiant ou mot de passe incorrect.',
		'en' => 'Incorrect username or password.',
	) );
} );

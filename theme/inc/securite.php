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
 * Et limiter les essais (§ 5 à 7) : connexions ratées, demandes de mot de
 * passe oublié, et — depuis inc/comptes.php — inscriptions.
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
	/*
	 * Deux messages passent quand même, parce qu'ils ne trahissent rien : trop
	 * d'essais depuis cette adresse (§ 6, quel que soit le compte visé), et
	 * adresse courriel à confirmer (inc/comptes.php, donné seulement à qui a
	 * le bon mot de passe). WooCommerce passe son formulaire par ce filtre.
	 */
	if ( isset( $GLOBALS['morgane_message_connexion'] ) ) {
		return $GLOBALS['morgane_message_connexion'];
	}

	return morgane_t( array(
		'fr' => 'Identifiant ou mot de passe incorrect.',
		'en' => 'Incorrect username or password.',
	) );
} );

/**
 * 5. Quotas par adresse IP.
 *
 * Un compteur par action et par adresse, qui s'efface de lui-même : les
 * formulaires de contact (inc/forms.php), l'inscription (inc/comptes.php), la
 * connexion et le mot de passe oublié ci-dessous.
 *
 * L'adresse n'est pas stockée telle quelle mais hachée avec les clés de
 * sécurité du site : le transient ne contient rien de réidentifiable.
 *
 * Derrière un service qui relaie les visiteurs (Cloudflare, proxy de
 * l'hébergeur), `REMOTE_ADDR` est l'adresse du relais, la même pour tout le
 * monde : ces plafonds s'appliqueraient alors au site entier. Le lire ici,
 * c'est vérifier ce point avant de mettre un tel service devant le site.
 */
function morgane_quota_cle( $nom ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	return '' === $ip ? '' : 'morgane_' . $nom . '_' . substr( wp_hash( $ip ), 0, 20 );
}

/**
 * Le plafond est-il atteint pour cette adresse ?
 */
function morgane_quota_atteint( $nom, $max ) {
	$cle = morgane_quota_cle( $nom );

	return '' !== $cle && (int) get_transient( $cle ) >= $max;
}

/**
 * Compte un essai de plus. Chaque essai repousse l'effacement : qui insiste
 * reste bloqué, qui s'arrête retrouve l'accès au bout de la durée.
 */
function morgane_quota_compter( $nom, $duree ) {
	$cle = morgane_quota_cle( $nom );
	if ( '' !== $cle ) {
		set_transient( $cle, (int) get_transient( $cle ) + 1, $duree );
	}
}

/**
 * 6. Deviner un mot de passe demande des milliers d'essais.
 *
 * Dix échecs en un quart d'heure depuis une même adresse, et elle attend :
 * assez pour les fautes de frappe d'une vraie personne, trop peu pour un
 * robot. Le blocage passe après la vérification du mot de passe (priorité
 * 100) et l'emporte sur elle : même le bon mot de passe est refusé tant que
 * l'adresse attend — sinon le robot saurait quand il a trouvé.
 *
 * Vaut pour tous les formulaires de connexion : « Mon compte », wp-login.php.
 */
add_action( 'wp_login_failed', function () {
	morgane_quota_compter( 'connexion', 15 * MINUTE_IN_SECONDS );
} );

add_filter( 'authenticate', function ( $user ) {
	if ( ! morgane_quota_atteint( 'connexion', 10 ) ) {
		return $user;
	}

	$GLOBALS['morgane_message_connexion'] = morgane_t( array(
		'fr' => 'Trop de tentatives de connexion. Réessayez dans un quart d’heure.',
		'en' => 'Too many login attempts. Please try again in 15 minutes.',
	) );

	return new WP_Error( 'morgane_trop_d_essais', $GLOBALS['morgane_message_connexion'] );
}, 100 );

/**
 * 7. « Mot de passe oublié » envoie un courriel à l'adresse saisie : sans
 * plafond, de quoi inonder la boîte de quelqu'un depuis le site, et abîmer la
 * réputation d'envoi du domaine. Cinq demandes par heure et par adresse.
 */
add_action( 'lostpassword_post', function ( $erreurs ) {
	if ( morgane_quota_atteint( 'oubli', 5 ) ) {
		$erreurs->add( 'morgane_trop_de_demandes', morgane_t( array(
			'fr' => 'Trop de demandes depuis votre connexion. Réessayez dans une heure.',
			'en' => 'Too many requests from your connection. Please try again in an hour.',
		) ) );
		return;
	}

	morgane_quota_compter( 'oubli', HOUR_IN_SECONDS );
} );

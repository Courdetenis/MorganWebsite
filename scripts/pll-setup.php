<?php
/**
 * Crée les langues Polylang : français (défaut) + anglais.
 * Idempotent. Lancer via : docker compose run --rm --entrypoint "wp eval-file /scripts/pll-setup.php" wpcli
 */

if ( ! function_exists( 'PLL' ) ) {
	WP_CLI::error( 'Polylang introuvable.' );
}

$pll = PLL();
if ( ! is_object( $pll ) || ! isset( $pll->model ) ) {
	WP_CLI::error( 'PLL() non initialisé en CLI.' );
}

$model = $pll->model;

// En CLI, Polylang charge PLL_Model (lecture seule). add_language() vit dans
// PLL_Admin_Model : on l'instancie avec les mêmes options.
if ( ! method_exists( $model, 'add_language' ) ) {
	$admin_model_file = WP_PLUGIN_DIR . '/polylang/admin/admin-model.php';
	if ( ! class_exists( 'PLL_Admin_Model' ) && file_exists( $admin_model_file ) ) {
		require_once $admin_model_file;
	}
	if ( ! class_exists( 'PLL_Admin_Model' ) ) {
		WP_CLI::error( 'PLL_Admin_Model introuvable.' );
	}
	$model = new PLL_Admin_Model( $pll->options );
	WP_CLI::log( 'Modèle admin instancié.' );
}

$langs = array(
	array(
		'name'       => 'Français',
		'slug'       => 'fr',
		'locale'     => 'fr_CA',
		'rtl'        => 0,
		'term_group' => 0,
		'flag'       => 'ca',
	),
	array(
		'name'       => 'English',
		'slug'       => 'en',
		'locale'     => 'en_US',
		'rtl'        => 0,
		'term_group' => 1,
		'flag'       => 'us',
	),
);

foreach ( $langs as $lang ) {
	$existing = $model->get_language( $lang['slug'] );
	if ( $existing ) {
		WP_CLI::log( 'Déjà présent : ' . $lang['slug'] );
		continue;
	}
	$res = $model->add_language( $lang );
	if ( is_wp_error( $res ) ) {
		WP_CLI::warning( $lang['slug'] . ' : ' . $res->get_error_message() );
	} else {
		WP_CLI::log( 'Créé : ' . $lang['slug'] . ' (' . $lang['locale'] . ')' );
	}
}

// Français par défaut + options de base.
$options = get_option( 'polylang' );
if ( is_array( $options ) ) {
	$options['default_lang'] = 'fr';
	$options['force_lang']   = 1; // langue dans l'URL
	/*
	 * Le français, langue par défaut, reste à la racine ; l'anglais vit sous
	 * /en/. Avec hide_default = 0, Polylang ne fait pas le lien entre /en/ et
	 * la traduction de la page d'accueil statique : /en/ tombe sur le fil des
	 * articles au lieu de l'accueil anglais.
	 */
	$options['hide_default'] = 1;
	/*
	 * Sans ça, l'accueil anglais s'annonce partout comme /en/home/ (le nom de
	 * la page traduite) au lieu de /en/. Les deux répondent, mais un seul est
	 * propre — et deux URL pour la même page, c'est du contenu dupliqué.
	 */
	$options['redirect_lang'] = true;

	update_option( 'polylang', $options );

	// Les langues mettent leur URL d'accueil en cache : à jeter après coup.
	delete_transient( 'pll_languages_list' );
	delete_option( 'rewrite_rules' ); // régénérées à la prochaine visite, filtres Polylang inclus
	WP_CLI::log( 'Options Polylang : défaut=fr (racine), anglais sous /en/' );
} else {
	WP_CLI::warning( 'Options Polylang au format objet — configurer via l\'admin.' );
}

WP_CLI::success( 'Langues Polylang configurées.' );

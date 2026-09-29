<?php
/**
 * Banc d'essai de la devise : chaque prix dit qu'il est en dollars canadiens,
 * dans la forme de sa langue (voir theme/inc/devise.php).
 * Même principe que verif-produits.php : pas de PHPUnit, les assertions se
 * jouent dans un WordPress chargé.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-devise.php
 */

$GLOBALS['mg_resultats'] = array();

function mg_verif( $nom, $ok, $detail = '' ) {
	$GLOBALS['mg_resultats'][] = array( $nom, (bool) $ok, $detail );
	printf( "%s %s%s\n", $ok ? '  ok  ' : '  ÉCHEC', $nom, $detail ? ' — ' . $detail : '' );
}

function mg_verif_bilan() {
	$echecs = 0;
	foreach ( $GLOBALS['mg_resultats'] as $r ) {
		if ( ! $r[1] ) {
			$echecs++;
		}
	}
	printf( "\n%d assertion(s), %d échec(s).\n", count( $GLOBALS['mg_resultats'] ), $echecs );
	return $echecs;
}

/**
 * Le prix tel qu'un visiteur le lit : sans balises, entités décodées.
 * Les espaces insécables (U+00A0, U+202F) restent tels quels — ils font partie
 * de ce qu'on vérifie.
 */
function mg_prix_lisible( $montant ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $montant ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Joue $rappel en anglais, par le filtre prévu pour ça, puis remet le français.
 */
function mg_en_anglais( $rappel ) {
	$forcer_en = function () {
		return 'en';
	};
	add_filter( 'morgane_langue_forcee', $forcer_en );
	$rappel();
	remove_filter( 'morgane_langue_forcee', $forcer_en );
}

$nbsp  = "\u{00A0}"; // espace insécable
$fine  = "\u{202F}"; // espace fine insécable

// --- 0. Préalable ----------------------------------------------------------
mg_verif( 'WooCommerce est chargé', function_exists( 'wc_price' ) );
mg_verif( 'La devise de la boutique est CAD', 'CAD' === get_woocommerce_currency(), get_woocommerce_currency() );

// --- 1. Le symbole dit « CA » ---------------------------------------------
mg_verif(
	'En français, le symbole est « $ CA »',
	'$' . $nbsp . 'CA' === html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
	get_woocommerce_currency_symbol()
);

mg_en_anglais( function () {
	mg_verif(
		'En anglais, le symbole est « CA$ »',
		'CA$' === html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
		get_woocommerce_currency_symbol()
	);
} );

// --- 2. Le prix rendu, dans la forme de sa langue -------------------------
mg_verif(
	'En français : « 1 234,50 $ CA »',
	'1' . $fine . '234,50' . $nbsp . '$' . $nbsp . 'CA' === mg_prix_lisible( 1234.5 ),
	mg_prix_lisible( 1234.5 )
);
mg_verif(
	'En français, un prix rond garde ses deux décimales : « 45,00 $ CA »',
	'45,00' . $nbsp . '$' . $nbsp . 'CA' === mg_prix_lisible( 45 ),
	mg_prix_lisible( 45 )
);

mg_en_anglais( function () {
	mg_verif(
		'En anglais : « CA$1,234.50 »',
		'CA$1,234.50' === mg_prix_lisible( 1234.5 ),
		mg_prix_lisible( 1234.5 )
	);
} );

// --- 3. Ce que le Store API lit (panier et commande, formatés côté navigateur)
mg_verif( 'En français, position « right_space »', 'right_space' === get_option( 'woocommerce_currency_pos' ), get_option( 'woocommerce_currency_pos' ) );
mg_verif( 'En français, décimale « , »', ',' === wc_get_price_decimal_separator(), wc_get_price_decimal_separator() );
mg_verif( 'En français, milliers séparés par une espace fine insécable', $fine === wc_get_price_thousand_separator(), bin2hex( wc_get_price_thousand_separator() ) );

mg_en_anglais( function () {
	mg_verif( 'En anglais, position « left »', 'left' === get_option( 'woocommerce_currency_pos' ), get_option( 'woocommerce_currency_pos' ) );
	mg_verif( 'En anglais, décimale « . »', '.' === wc_get_price_decimal_separator(), wc_get_price_decimal_separator() );
	mg_verif( 'En anglais, milliers séparés par « , »', ',' === wc_get_price_thousand_separator(), wc_get_price_thousand_separator() );
} );

// --- 4. Une autre devise n'est pas touchée --------------------------------
mg_verif(
	'Le symbole de l\'euro reste l\'euro',
	'€' === html_entity_decode( get_woocommerce_currency_symbol( 'EUR' ), ENT_QUOTES, 'UTF-8' ),
	get_woocommerce_currency_symbol( 'EUR' )
);

// --- 5. Dans l'administration, on saisit les prix comme avant --------------
// Le champ « Prix » de la fiche produit refuse tout autre séparateur décimal
// que celui de la boutique ; « 45.50 » y deviendrait « 4550 » si la décimale
// passait à la virgule. Les séparateurs ne bougent donc pas dans wp-admin.
if ( ! function_exists( 'set_current_screen' ) ) {
	// En ligne de commande, les fonctions d'écran de wp-admin ne sont pas chargées.
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}
set_current_screen( 'edit-product' );
mg_verif( 'is_admin() simulé', is_admin() );
mg_verif( 'Dans wp-admin, décimale « . » comme avant', '.' === wc_get_price_decimal_separator(), wc_get_price_decimal_separator() );
mg_verif( 'Dans wp-admin, milliers « , » comme avant', ',' === wc_get_price_thousand_separator(), wc_get_price_thousand_separator() );
mg_verif(
	'Dans wp-admin, le symbole dit quand même « CA »',
	'$' . $nbsp . 'CA' === html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
	get_woocommerce_currency_symbol()
);
set_current_screen( 'front' );
mg_verif( 'Retour hors administration', ! is_admin() );

exit( mg_verif_bilan() ? 1 : 0 );

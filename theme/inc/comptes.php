<?php
/**
 * Comptes clients : le mot de passe se choisit à l'inscription, l'adresse se
 * confirme par courriel.
 *
 * WooCommerce fait l'inverse par défaut : il invente un mot de passe et envoie
 * un lien pour en choisir un autre. Le client crée un compte… et doit aller
 * dans sa boîte avant de pouvoir s'en servir, avec un mot de passe qu'il n'a
 * pas choisi. Ici, le mot de passe se choisit dans le formulaire, et le
 * courriel ne sert qu'à prouver que l'adresse est la bonne.
 *
 * Tant que l'adresse n'est pas confirmée, le compte existe mais ne s'ouvre pas.
 * Se connecter avec le bon mot de passe renvoie le lien. Réinitialiser son mot
 * de passe confirme aussi l'adresse : ce lien-là arrive dans la même boîte.
 *
 * Seule la création d'un compte côté client — formulaire « Mon compte », ou
 * case « Créer un compte » au paiement — pose la marque « à confirmer ». Les
 * comptes antérieurs, et ceux créés depuis l'administration, n'en ont pas.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MORGANE_META_A_CONFIRMER = '_morgane_courriel_a_confirmer';

/*
 * Le mot de passe se choisit dans le formulaire. Forcé ici plutôt que réglé en
 * base : tout ce qui suit en dépend, et la case de WooCommerce (Réglages >
 * Comptes et confidentialité) se recoche en un clic. Le paiement suit le même
 * réglage : la case « Créer un compte » y fait apparaître le champ.
 */
add_filter( 'pre_option_woocommerce_registration_generate_password', function () {
	return 'no';
} );

/*
 * Le courriel « Nouveau compte » de WooCommerce ferait doublon avec le nôtre,
 * qui annonce le compte et porte le lien : un seul message à l'inscription.
 */
add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );

/**
 * Empreinte d'une clé de confirmation. Seule l'empreinte est gardée en base :
 * qui lirait la table des métadonnées ne pourrait pas confirmer un compte à la
 * place de son titulaire.
 */
function morgane_empreinte_confirmation( $cle ) {
	return hash_hmac( 'sha256', (string) $cle, wp_salt( 'auth' ) );
}

/**
 * Pose une nouvelle clé sur le compte et envoie le lien. Chaque envoi remplace
 * la clé précédente : seul le dernier lien reçu fonctionne.
 *
 * Le courriel part dans la langue de la page où le compte a été créé, et le
 * lien ramène à la page « Mon compte » de cette langue.
 */
function morgane_envoyer_confirmation( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! function_exists( 'WC' ) ) {
		return;
	}

	$cle = wp_generate_password( 32, false );
	update_user_meta( $user_id, MORGANE_META_A_CONFIRMER, array(
		'empreinte' => morgane_empreinte_confirmation( $cle ),
		'envoi'     => time(),
	) );

	$lien = add_query_arg(
		array(
			'confirmer-compte' => $user_id,
			'cle'              => $cle,
		),
		morgane_wc_page_url( 'myaccount' )
	);

	$titre = morgane_t( array(
		'fr' => 'Confirmez votre adresse',
		'en' => 'Confirm your email address',
	) );
	$corps = morgane_t( array(
		'fr' => '<p>Bonjour,</p>'
			. '<p>Votre compte est créé. Pour l’activer, confirmez votre adresse courriel :</p>'
			. '<p><a href="%s">Confirmer mon adresse</a></p>'
			. '<p>Si vous n’avez pas créé de compte, ignorez simplement ce message.</p>',
		'en' => '<p>Hello,</p>'
			. '<p>Your account has been created. To activate it, please confirm your email address:</p>'
			. '<p><a href="%s">Confirm my email address</a></p>'
			. '<p>If you did not create an account, simply ignore this message.</p>',
	) );

	$mailer = WC()->mailer();
	$mailer->send(
		$user->user_email,
		$titre . ' — ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$mailer->wrap_message( $titre, sprintf( $corps, esc_url( $lien ) ) )
	);
}

add_action( 'woocommerce_created_customer', function ( $user_id ) {
	morgane_envoyer_confirmation( $user_id );
} );

/*
 * Pas de connexion d'office après l'inscription depuis « Mon compte » : le
 * compte attend sa confirmation. Ce filtre passe juste après l'avis de
 * WooCommerce — « vos identifiants vous ont été envoyés », faux ici — qu'on
 * remplace. Sans connexion, WooCommerce ne redirige pas : il réaffiche la page
 * Mon compte avec l'avis.
 *
 * Le paiement ne passe pas par ce filtre : qui crée son compte en commandant
 * reste connecté le temps de sa commande, et confirme pour les fois suivantes.
 */
add_filter( 'woocommerce_registration_auth_new_customer', function ( $connecter, $user_id ) {
	$user = get_userdata( $user_id );

	wc_clear_notices();
	wc_add_notice( sprintf(
		morgane_t( array(
			'fr' => 'Votre compte est créé. Pour l’activer, cliquez sur le lien que nous venons d’envoyer à %s.',
			'en' => 'Your account has been created. To activate it, click the link we just sent to %s.',
		) ),
		'<strong>' . esc_html( $user ? $user->user_email : '' ) . '</strong>'
	) );

	return false;
}, 10, 2 );

/*
 * Le compte ne s'ouvre pas tant que l'adresse n'est pas confirmée.
 *
 * Priorité 30 : après la vérification du mot de passe (20). Le lien n'est donc
 * renvoyé qu'à qui connaît le mot de passe — pas à n'importe qui tapant une
 * adresse — et au plus une fois toutes les dix minutes.
 */
add_filter( 'authenticate', function ( $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return $user;
	}

	$attente = get_user_meta( $user->ID, MORGANE_META_A_CONFIRMER, true );
	if ( ! is_array( $attente ) ) {
		return $user;
	}

	if ( empty( $attente['envoi'] ) || time() - (int) $attente['envoi'] > 10 * MINUTE_IN_SECONDS ) {
		morgane_envoyer_confirmation( $user->ID );
	}

	// Rangé là où inc/securite.php le laisse passer : son filtre `login_errors`
	// remplace sinon tout échec par « identifiant ou mot de passe incorrect ».
	$GLOBALS['morgane_message_connexion'] = sprintf(
		morgane_t( array(
			'fr' => 'Votre adresse n’est pas encore confirmée. Cliquez sur le lien envoyé à %s pour activer votre compte.',
			'en' => 'Your email address is not confirmed yet. Click the link sent to %s to activate your account.',
		) ),
		esc_html( $user->user_email )
	);

	return new WP_Error( 'morgane_courriel_a_confirmer', $GLOBALS['morgane_message_connexion'] );
}, 30 );

/*
 * Le lien reçu par courriel : il confirme l'adresse et ouvre la session, comme
 * le ferait un lien de réinitialisation — il prouve la même chose.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['confirmer-compte'] ) || empty( $_GET['cle'] ) || ! function_exists( 'wc_set_customer_auth_cookie' ) ) {
		return;
	}

	$user_id = absint( $_GET['confirmer-compte'] );
	$cle     = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['cle'] ) );
	$attente = get_user_meta( $user_id, MORGANE_META_A_CONFIRMER, true );
	$compte  = morgane_wc_page_url( 'myaccount' );

	// Déjà confirmé (lien cliqué deux fois) : rien à faire, on montre le compte.
	if ( ! is_array( $attente ) ) {
		wp_safe_redirect( $compte );
		exit;
	}

	if ( empty( $attente['empreinte'] ) || ! hash_equals( $attente['empreinte'], morgane_empreinte_confirmation( $cle ) ) ) {
		wp_safe_redirect( add_query_arg( 'compte', 'lien-invalide', $compte ) );
		exit;
	}

	delete_user_meta( $user_id, MORGANE_META_A_CONFIRMER );
	wc_set_customer_auth_cookie( $user_id );

	wp_safe_redirect( add_query_arg( 'compte', 'confirme', $compte ) );
	exit;
} );

/*
 * Les deux issues du lien, annoncées sur la page Mon compte. Par l'adresse et
 * non par la session : un visiteur pas encore connecté n'en a pas toujours.
 */
add_action( 'woocommerce_account_content', function () {
	if ( isset( $_GET['compte'] ) && 'confirme' === $_GET['compte'] ) {
		wc_print_notice( morgane_t( array(
			'fr' => 'Votre adresse est confirmée : votre compte est actif.',
			'en' => 'Your email address is confirmed: your account is active.',
		) ), 'success' );
	}
}, 6 );

add_action( 'woocommerce_before_customer_login_form', function () {
	if ( isset( $_GET['compte'] ) && 'lien-invalide' === $_GET['compte'] ) {
		wc_print_notice( morgane_t( array(
			'fr' => 'Ce lien de confirmation n’est plus valide. Connectez-vous : un nouveau lien vous sera envoyé.',
			'en' => 'This confirmation link is no longer valid. Log in and a new link will be sent to you.',
		) ), 'error' );
	}
}, 11 );

/*
 * Réinitialiser son mot de passe passe par un lien envoyé à la même adresse :
 * c'est une confirmation, inutile d'en exiger une seconde.
 */
add_action( 'password_reset', function ( $user ) {
	if ( $user instanceof WP_User ) {
		delete_user_meta( $user->ID, MORGANE_META_A_CONFIRMER );
	}
} );

/* -------------------------------------------------------------------------
   Le mot de passe : une règle, affichée et vérifiée partout où il se choisit.
   ------------------------------------------------------------------------- */

/**
 * La règle en une phrase, telle qu'elle s'affiche sous le champ.
 */
function morgane_regle_mot_de_passe() {
	return morgane_t( array(
		'fr' => 'Au moins 8 caractères, dont une majuscule, un chiffre et un caractère spécial (! ? @ # …).',
		'en' => 'At least 8 characters, including an uppercase letter, a number and a special character (! ? @ # …).',
	) );
}

/**
 * Le message d'erreur si le mot de passe ne suit pas la règle, '' s'il la suit.
 *
 * « Spécial » : tout ce qui n'est ni lettre, ni chiffre, ni espace. Les lettres
 * accentuées sont des lettres comme les autres — « É » compte pour la majuscule.
 */
function morgane_mot_de_passe_refuse( $mdp ) {
	$mdp = (string) $mdp;

	if ( mb_strlen( $mdp ) >= 8
		&& preg_match( '/\p{Lu}/u', $mdp )
		&& preg_match( '/\p{N}/u', $mdp )
		&& preg_match( '/[^\p{L}\p{N}\s]/u', $mdp )
	) {
		return '';
	}

	return morgane_t( array(
		'fr' => 'Le mot de passe doit faire au moins 8 caractères, dont une majuscule, un chiffre et un caractère spécial.',
		'en' => 'Your password must be at least 8 characters long and include an uppercase letter, a number and a special character.',
	) );
}

/**
 * Un mot de passe du formulaire en cours, tel que tapé. WordPress ajoute des
 * barres obliques à tout ce qui arrive par POST : sans wp_unslash(), un mot de
 * passe contenant un guillemet ne serait plus égal à sa confirmation.
 */
function morgane_mdp_saisi( $champ ) {
	return isset( $_POST[ $champ ] ) ? (string) wp_unslash( $_POST[ $champ ] ) : ''; // phpcs:ignore WordPress.Security
}

/*
 * L'indicateur de solidité de WooCommerce applique sa propre règle — douze
 * caractères conseillés, et un refus d'après une note que personne ne voit
 * venir : un mot de passe conforme à la nôtre pouvait rester bloqué. Une seule
 * règle, affichée sous le champ et vérifiée par le serveur.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_script( 'wc-password-strength-meter' );
}, 20 );

/*
 * Inscription : la règle sous le champ « Mot de passe », puis sa confirmation.
 * Le crochet passe juste après ce champ, et avant le texte de confidentialité
 * que WooCommerce y accroche en priorité 20.
 */
add_action( 'woocommerce_register_form', function () {
	?>
	<p class="mg-regle-mdp"><?php echo esc_html( morgane_regle_mot_de_passe() ); ?></p>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_password2"><?php echo esc_html( morgane_t( array( 'fr' => 'Confirmer le mot de passe', 'en' => 'Confirm password' ) ) ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php echo esc_html( morgane_t( array( 'fr' => 'Obligatoire', 'en' => 'Required' ) ) ); ?></span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password2" id="reg_password2" autocomplete="new-password" required aria-required="true" />
	</p>
	<?php
}, 5 );

/*
 * Inscription, côté serveur. D'abord le plafond : dix essais par heure et par
 * adresse IP (inc/securite.php, § 5). Chaque compte créé envoie un courriel à
 * l'adresse saisie — sans plafond, n'importe qui pourrait s'en servir pour
 * inonder une boîte, et le site y perdrait sa réputation d'envoi. Dix laisse la
 * place à plusieurs mots de passe refusés d'affilée.
 */
add_filter( 'woocommerce_process_registration_errors', function ( $erreurs ) {
	if ( morgane_quota_atteint( 'inscription', 10 ) ) {
		$erreurs->add( 'morgane_trop_d_inscriptions', morgane_t( array(
			'fr' => 'Trop d’essais d’inscription depuis votre connexion. Réessayez dans une heure.',
			'en' => 'Too many sign-up attempts from your connection. Please try again in an hour.',
		) ) );
		return $erreurs;
	}
	morgane_quota_compter( 'inscription', HOUR_IN_SECONDS );

	$mdp   = morgane_mdp_saisi( 'password' );
	$regle = morgane_mot_de_passe_refuse( $mdp );

	if ( $regle ) {
		$erreurs->add( 'morgane_mot_de_passe_refuse', $regle );
	} elseif ( morgane_mdp_saisi( 'password2' ) !== $mdp ) {
		$erreurs->add( 'morgane_mots_de_passe_differents', morgane_t( array(
			'fr' => 'Les deux mots de passe ne sont pas identiques.',
			'en' => 'The two passwords do not match.',
		) ) );
	}

	return $erreurs;
} );

/*
 * Nouveau mot de passe après un oubli. WooCommerce nomme le champ password_1,
 * wp-login.php pass1 ; tous deux demandent déjà de le saisir deux fois. Le
 * crochet tourne aussi à l'affichage du formulaire, champ vide : rien à
 * vérifier alors.
 */
add_action( 'validate_password_reset', function ( $erreurs ) {
	$mdp = isset( $_POST['password_1'] ) ? morgane_mdp_saisi( 'password_1' ) : morgane_mdp_saisi( 'pass1' ); // phpcs:ignore WordPress.Security
	if ( '' === $mdp ) {
		return;
	}

	$regle = morgane_mot_de_passe_refuse( $mdp );
	if ( $regle ) {
		$erreurs->add( 'morgane_mot_de_passe_refuse', $regle );
	}
} );

/*
 * Changement de mot de passe dans « Mon compte > Détails du compte ». Champ
 * vide : le client ne change pas de mot de passe.
 */
add_action( 'woocommerce_save_account_details_errors', function ( $erreurs ) {
	$mdp = morgane_mdp_saisi( 'password_1' );
	if ( '' === $mdp ) {
		return;
	}

	$regle = morgane_mot_de_passe_refuse( $mdp );
	if ( $regle ) {
		$erreurs->add( 'morgane_mot_de_passe_refuse', $regle );
	}
} );

/*
 * Compte créé en payant, page de commande classique.
 */
add_action( 'woocommerce_after_checkout_validation', function ( $donnees, $erreurs ) {
	if ( empty( $donnees['createaccount'] ) || is_user_logged_in() ) {
		return;
	}

	$regle = morgane_mot_de_passe_refuse( morgane_mdp_saisi( 'account_password' ) );
	if ( $regle ) {
		$erreurs->add( 'validation', $regle );
	}
}, 10, 2 );

/*
 * Compte créé en payant, bloc Commande. La commande passe par l'API Store de
 * WooCommerce : l'erreur se lève comme une exception de route, que la page
 * affiche au-dessus du bouton. Ce crochet passe avant la création du compte.
 */
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $commande, $requete ) {
	if ( ! $requete->get_param( 'create_account' ) || is_user_logged_in() ) {
		return;
	}

	$regle = morgane_mot_de_passe_refuse( (string) $requete->get_param( 'customer_password' ) );
	if ( $regle && class_exists( 'Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
		throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'morgane_mot_de_passe_refuse', $regle, 400 );
	}
}, 10, 2 );

/*
 * Le texte de confidentialité sous le formulaire est un réglage de WooCommerce,
 * en une seule langue : le français posé par l'installateur s'affichait aussi
 * sur le site anglais. L'anglais est fourni ici ; le français reste modifiable
 * dans Réglages > Comptes et confidentialité.
 */
add_filter( 'woocommerce_get_privacy_policy_text', function ( $texte ) {
	if ( 'en' !== morgane_lang() ) {
		return $texte;
	}

	return 'Your personal data will be used to process your order, support your experience on this website, and for other purposes described in our [privacy_policy].';
} );

<?php
/**
 * Formulaires : commissions, collaborations, murales.
 *
 * Trois formulaires figés, aux champs dictés par le cahier des charges — pas
 * d'extension de formulaires : une extension ajouterait un constructeur de
 * champs dont personne n'a besoin, son propre markup à surcharger, et un
 * doublon par langue à maintenir dans Polylang.
 *
 * Le traitement se fait avant le rendu (template_redirect), puis redirection
 * en cas de succès : rafraîchir la page ne renvoie pas le message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adresse qui reçoit les demandes.
 *
 * Celle de `inc/contact.php`, la même que le site affiche partout. Elle prime
 * sur l'adresse d'administration de WordPress : sur un hébergement, celle-ci
 * est souvent une adresse technique que personne ne relève, et les trois
 * formulaires sont la seule voie de contact du site.
 */
function morgane_contact_email() {
	$defaut = get_option( 'admin_email' );

	if ( function_exists( 'morgane_contact_info' ) ) {
		$c = morgane_contact_info();
		if ( ! empty( $c['email'] ) && is_email( $c['email'] ) ) {
			$defaut = $c['email'];
		}
	}

	return (string) apply_filters( 'morgane_contact_email', $defaut );
}

/**
 * Définition des trois formulaires.
 * `type` : 'text' | 'email' | 'textarea'.
 */
function morgane_form_definitions() {
	return array(
		'commission'    => array(
			'title'  => array( 'fr' => 'Parlez-moi de votre projet', 'en' => 'Tell me about your project' ),
			'fields' => array(
				'idea'   => array(
					'type'     => 'textarea',
					'required' => true,
					'label'    => array( 'fr' => 'Votre idée de projet', 'en' => 'Your project idea' ),
					'hint'     => array( 'fr' => 'Thèmes, sujets, composition, ambiance…', 'en' => 'Themes, subjects, composition, mood…' ),
				),
				'format' => array(
					'type'  => 'text',
					'label' => array( 'fr' => 'Taille ou format souhaité (cm)', 'en' => 'Desired size or format (cm)' ),
				),
				'medium' => array(
					'type'  => 'text',
					'label' => array( 'fr' => 'Médium souhaité', 'en' => 'Preferred medium' ),
					'hint'  => array( 'fr' => 'Gouache, aquarelle, encre, crayon à mine…', 'en' => 'Gouache, watercolour, ink, pencil…' ),
				),
				'email'  => array(
					'type'     => 'email',
					'required' => true,
					'label'    => array( 'fr' => 'Votre adresse courriel', 'en' => 'Your email address' ),
				),
			),
		),
		'collaboration' => array(
			'title'  => array( 'fr' => 'Proposer un projet', 'en' => 'Submit a project' ),
			'fields' => array(
				'project' => array(
					'type'     => 'textarea',
					'required' => true,
					'label'    => array( 'fr' => 'Votre projet', 'en' => 'Your project' ),
					'hint'     => array(
						'fr' => 'Présentation, type de collaboration, délais, budget, liens utiles.',
						'en' => 'Overview, type of collaboration, timeline, budget, useful links.',
					),
				),
				'email'   => array(
					'type'     => 'email',
					'required' => true,
					'label'    => array( 'fr' => 'Votre adresse courriel', 'en' => 'Your email address' ),
				),
			),
		),
		'mural'         => array(
			'title'  => array( 'fr' => 'Parlez-moi de votre mur', 'en' => 'Tell me about your wall' ),
			'fields' => array(
				'dimensions' => array(
					'type'     => 'text',
					'required' => true,
					'label'    => array( 'fr' => 'Dimensions approximatives de la surface', 'en' => 'Approximate surface dimensions' ),
				),
				'place'      => array(
					'type'     => 'text',
					'required' => true,
					'label'    => array( 'fr' => 'Lieu / adresse', 'en' => 'Location / address' ),
				),
				'dates'      => array(
					'type'  => 'text',
					'label' => array( 'fr' => 'Dates envisagées', 'en' => 'Intended dates' ),
				),
				'idea'       => array(
					'type'     => 'textarea',
					'required' => true,
					'label'    => array( 'fr' => 'Votre idée', 'en' => 'Your idea' ),
					'hint'     => array( 'fr' => 'Thèmes, composition, atmosphère souhaitée.', 'en' => 'Themes, composition, desired atmosphere.' ),
				),
				'email'      => array(
					'type'     => 'email',
					'required' => true,
					'label'    => array( 'fr' => 'Votre adresse courriel', 'en' => 'Your email address' ),
				),
			),
		),
	);
}

/**
 * Résultat du dernier traitement, partagé entre template_redirect et le rendu.
 *
 * @return array{type:string,errors:array,values:array}
 */
function &morgane_form_state() {
	static $state = array(
		'type'   => '',
		'errors' => array(),
		'values' => array(),
		'notice' => '',
	);
	return $state;
}

/**
 * Limite de débit, par empreinte d'adresse IP.
 *
 * Le piège à robots arrête les moissonneuses génériques, pas un envoi
 * automatisé écrit pour ce site : il suffit de laisser le champ vide. Sans
 * plafond, chaque soumission déclenche un `wp_mail` — de quoi noyer la boîte
 * de Morgane et abîmer la réputation d'envoi du domaine.
 *
 * L'adresse n'est pas stockée telle quelle mais hachée avec les clés de
 * sécurité du site : le transient ne contient rien de réidentifiable, et il
 * s'efface tout seul au bout d'une heure.
 *
 * @return bool true si l'envoi est autorisé.
 */
function morgane_form_rate_ok() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		return true;
	}

	$key   = 'morgane_fq_' . substr( wp_hash( $ip ), 0, 20 );
	$count = (int) get_transient( $key );

	/**
	 * Plafond horaire. Cinq laisse la place à une hésitation, à un renvoi après
	 * une erreur de saisie et à deux formulaires différents ; au-delà, ce n'est
	 * plus quelqu'un qui écrit.
	 */
	$max = (int) apply_filters( 'morgane_form_max_per_hour', 5 );
	if ( $count >= $max ) {
		return false;
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Traitement de l'envoi.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_POST['morgane_form'] ) ) {
		return;
	}

	$type  = sanitize_key( wp_unslash( $_POST['morgane_form'] ) );
	$forms = morgane_form_definitions();
	if ( ! isset( $forms[ $type ] ) ) {
		return;
	}

	// La page qui porte le formulaire — le formulaire poste sur lui-même.
	$back = get_permalink( get_queried_object_id() );
	if ( ! $back ) {
		$back = wp_get_referer() ?: home_url( '/' );
	}
	$done = add_query_arg( 'envoye', $type, $back ) . '#formulaire';

	// Piège à robots : rempli = on fait comme si tout allait bien, sans envoyer.
	if ( ! empty( $_POST['morgane_website'] ) ) {
		wp_safe_redirect( $done );
		exit;
	}

	/**
	 * Les valeurs sont relues et assainies avant la vérification du nonce, pour
	 * pouvoir les réafficher si celui-ci a expiré. Rien n'est envoyé ni
	 * enregistré à ce stade : la seule chose qui arrive à des données non
	 * vérifiées, c'est de repartir échappées vers le navigateur qui les a
	 * postées. Le traitement réel, lui, reste derrière le nonce.
	 */
	$state         = &morgane_form_state();
	$state['type'] = $type;
	$errors        = array();
	$values        = array();

	foreach ( $forms[ $type ]['fields'] as $name => $field ) {
		$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';

		if ( 'email' === $field['type'] ) {
			$value = sanitize_email( $raw );
			if ( $value && ! is_email( $value ) ) {
				$value = '';
			}
		} elseif ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}

		$values[ $name ] = $value;

		if ( ! empty( $field['required'] ) && '' === $value ) {
			$errors[ $name ] = morgane_t( $field['label'] );
		}
	}

	$state['values'] = $values;

	/**
	 * Nonce expiré : on réaffiche la page plutôt que de mourir en 403.
	 *
	 * Sur un formulaire public, le nonce est le même pour tous les visiteurs
	 * déconnectés et vit douze à vingt-quatre heures. Derrière un cache pleine
	 * page — le réglage par défaut de beaucoup d'hébergeurs — la page servie
	 * fige un nonce qui finit par expirer, et un visiteur parfaitement légitime
	 * se retrouvait devant une page blanche, son texte perdu. Le formulaire
	 * réaffiché porte un nonce frais : il suffit de renvoyer.
	 */
	if ( ! isset( $_POST['morgane_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['morgane_nonce'] ) ), 'morgane_form_' . $type ) ) {
		$state['notice'] = morgane_t( array(
			'fr' => 'Votre session avait expiré, votre message n\'est pas parti. Vos réponses sont toujours là : renvoyez le formulaire.',
			'en' => 'Your session had expired, so your message was not sent. Your answers are still here — just submit again.',
		) );
		return;
	}

	if ( $errors ) {
		$state['errors'] = $errors;
		return; // On réaffiche la page avec les erreurs et les valeurs saisies.
	}

	if ( ! morgane_form_rate_ok() ) {
		$state['notice'] = morgane_t( array(
			'fr' => 'Vous avez envoyé plusieurs messages coup sur coup. Patientez une heure, ou écrivez directement à ' . morgane_contact_email() . '.',
			'en' => 'You have sent several messages in a row. Please wait an hour, or write directly to ' . morgane_contact_email() . '.',
		) );
		return;
	}

	// --- Envoi ---------------------------------------------------------
	$lines = array();
	foreach ( $forms[ $type ]['fields'] as $name => $field ) {
		$lines[] = $field['label']['fr'] . ' : ' . ( '' !== $values[ $name ] ? $values[ $name ] : '—' );
	}
	$lines[] = '';
	$lines[] = 'Envoyé depuis ' . home_url( '/' ) . ' (langue : ' . morgane_lang() . ')';

	$subjects = array(
		'commission'    => 'Nouvelle demande de commission',
		'collaboration' => 'Nouvelle proposition de collaboration',
		'mural'         => 'Nouvelle demande de murale',
	);

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! empty( $values['email'] ) ) {
		// Répondre directement au demandeur. L'adresse est déjà validée.
		$headers[] = 'Reply-To: ' . $values['email'];
	}

	$sent = wp_mail( morgane_contact_email(), $subjects[ $type ], implode( "\n", $lines ), $headers );

	/**
	 * Un envoi qui échoue ne doit pas afficher « message parti ».
	 *
	 * Sur un serveur neuf, `mail()` n'est presque jamais configuré : sans ce
	 * contrôle, le visiteur repartait rassuré, Morgane ne recevait rien, et la
	 * demande était perdue sans que personne ne le sache. Les formulaires sont
	 * la seule voie de contact du site.
	 *
	 * En repli on donne l'adresse en clair : le visiteur peut toujours écrire.
	 */
	if ( ! $sent ) {
		$state['notice'] = morgane_t( array(
			'fr' => 'L\'envoi a échoué de notre côté — rien n\'est perdu, votre texte est toujours là. Écrivez-nous directement à ' . morgane_contact_email() . '.',
			'en' => 'Sending failed on our side — nothing is lost, your text is still here. Please write to us directly at ' . morgane_contact_email() . '.',
		) );
		return;
	}

	wp_safe_redirect( $done );
	exit;
} );

/**
 * Rendu d'un formulaire.
 */
function morgane_render_form( $attrs ) {
	$type  = isset( $attrs['type'] ) ? sanitize_key( $attrs['type'] ) : '';
	$forms = morgane_form_definitions();
	if ( ! isset( $forms[ $type ] ) ) {
		return '';
	}

	$form  = $forms[ $type ];
	$state = morgane_form_state();
	$mine  = ( $state['type'] === $type );

	$out = '<div class="form-wrap" id="formulaire">';

	// Message de confirmation après redirection.
	if ( isset( $_GET['envoye'] ) && sanitize_key( wp_unslash( $_GET['envoye'] ) ) === $type ) {
		$out .= '<p class="form__message form__message--ok" role="status">' . esc_html( morgane_t( array(
			'fr' => 'Merci, votre message est parti. Je vous réponds dès que possible.',
			'en' => 'Thank you, your message is on its way. I will get back to you as soon as possible.',
		) ) ) . '</p>';
	}

	// Session expirée, plafond atteint, échec d'envoi : un message qui explique
	// ce qui s'est passé et ce qu'il reste à faire, jamais un cul-de-sac.
	if ( $mine && ! empty( $state['notice'] ) ) {
		$out .= '<p class="form__message form__message--error" role="alert">' . esc_html( $state['notice'] ) . '</p>';
	}

	if ( $mine && $state['errors'] ) {
		$out .= '<p class="form__message form__message--error" role="alert">' . esc_html( morgane_t( array(
			'fr' => 'Il manque quelques informations : ',
			'en' => 'A few details are missing: ',
		) ) . implode( ', ', $state['errors'] ) ) . '</p>';
	}

	$out .= '<h2>' . esc_html( morgane_t( $form['title'] ) ) . '</h2>';
	$out .= '<form class="form" method="post" action="' . esc_url( get_permalink() . '#formulaire' ) . '">';
	$out .= '<input type="hidden" name="morgane_form" value="' . esc_attr( $type ) . '">';
	$out .= wp_nonce_field( 'morgane_form_' . $type, 'morgane_nonce', true, false );

	// Piège à robots — masqué visuellement, jamais annoncé aux lecteurs d'écran.
	$out .= '<div class="form__pot" aria-hidden="true"><label>Website<input type="text" name="morgane_website" tabindex="-1" autocomplete="off"></label></div>';

	foreach ( $form['fields'] as $name => $field ) {
		$id       = 'f-' . $type . '-' . $name;
		$value    = $mine && isset( $state['values'][ $name ] ) ? $state['values'][ $name ] : '';
		$required = ! empty( $field['required'] );
		$hint_id  = isset( $field['hint'] ) ? $id . '-hint' : '';

		$label = esc_html( morgane_t( $field['label'] ) );
		if ( ! $required ) {
			$label .= ' <span class="form__optional">' . esc_html( morgane_t( array( 'fr' => '(facultatif)', 'en' => '(optional)' ) ) ) . '</span>';
		}

		// Les zones de texte occupent les deux colonnes quand le formulaire en a deux.
		$out .= '<p class="form__field' . ( 'textarea' === $field['type'] ? ' form__field--wide' : '' ) . '">';
		$out .= '<label class="form__label" for="' . esc_attr( $id ) . '">' . $label . '</label>';

		if ( $hint_id ) {
			$out .= '<span class="form__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( morgane_t( $field['hint'] ) ) . '</span>';
		}

		$common = sprintf(
			'id="%s" name="%s"%s%s',
			esc_attr( $id ),
			esc_attr( $name ),
			$required ? ' required' : '',
			$hint_id ? ' aria-describedby="' . esc_attr( $hint_id ) . '"' : ''
		);

		if ( 'textarea' === $field['type'] ) {
			$out .= '<textarea ' . $common . ' rows="6">' . esc_textarea( $value ) . '</textarea>';
		} else {
			$input_type = 'email' === $field['type'] ? 'email' : 'text';
			$autocomplete = 'email' === $field['type'] ? ' autocomplete="email"' : '';
			$out .= '<input type="' . $input_type . '" ' . $common . $autocomplete . ' value="' . esc_attr( $value ) . '">';
		}

		$out .= '</p>';
	}

	$out .= '<button class="form__submit" type="submit">' . esc_html( morgane_t( array( 'fr' => 'Envoyer', 'en' => 'Send' ) ) ) . '</button>';
	$out .= '</form></div>';

	return $out;
}

add_action( 'init', function () {
	register_block_type(
		'morgane/form',
		array(
			'api_version'     => 3,
			'title'           => __( 'Formulaire', 'morgane' ),
			'category'        => 'widgets',
			'attributes'      => array(
				'type' => array(
					'type'    => 'string',
					'default' => 'commission',
				),
			),
			'render_callback' => 'morgane_render_form',
		)
	);
} );

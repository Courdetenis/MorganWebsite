<?php
/**
 * Installateur de contenu, depuis wp-admin.
 *
 * Sur un hébergement mutualisé il n'y a ni SSH ni WP-CLI : les scripts de
 * `install/` seraient inutilisables, et le thème seul ne donne qu'un site vide
 * dont le menu pointe six fois vers l'accueil. Cette page les lance depuis
 * l'administration, **une étape par requête** — un hébergeur coupe PHP au bout
 * de trente à cent vingt secondes, et les vingt-trois photos ne passent pas
 * d'un coup. Une requête coupée en cours de route laisserait la table des
 * médias à moitié écrite.
 *
 * Rien ne se déclenche tout seul : pas de crochet d'activation, toujours un
 * clic. Et tout est idempotent — un second passage ne duplique rien et
 * n'écrase pas une page déjà remplie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Les étapes, dans l'ordre imposé par les dépendances : les langues d'abord,
 * puis les produits traduisibles, puis les photos — que `pages.php` lit — et
 * enfin les pages et la boutique.
 */
function morgane_install_steps() {
	$photos = (array) require get_theme_file_path( 'install/photos.php' );
	$total  = count( $photos );

	$steps = array(
		array( 'id' => 'site',        'file' => '',                'label' => 'Nommer le site et poser les permaliens' ),
		array( 'id' => 'pll-setup',   'file' => 'pll-setup.php',   'label' => 'Créer les langues (français, anglais)' ),
		// pll-cpt.php fait l'inverse de ce que son ancien libellé annonçait :
		// il sort les produits de Polylang, pour qu'une œuvre n'ait qu'une
		// fiche et qu'un seul compteur de stock (voir inc/produits.php).
		array( 'id' => 'pll-cpt',     'file' => 'pll-cpt.php',     'label' => 'Sortir les produits de Polylang (un seul stock par œuvre)' ),
		array( 'id' => 'pll-assign',  'file' => 'pll-assign.php',  'label' => 'Assigner le français aux contenus existants' ),
		array( 'id' => 'pll-content', 'file' => 'pll-content.php', 'label' => 'Vérifier que Polylang est actif' ),
	);

	$i = 0;
	foreach ( array_keys( $photos ) as $n ) {
		$i++;
		$steps[] = array(
			'id'    => 'media-' . $n,
			'file'  => 'media.php',
			'photo' => (int) $n,
			'label' => sprintf( 'Photo %d sur %d', $i, $total ),
		);
	}

	$steps[] = array( 'id' => 'pages',    'file' => 'pages.php', 'label' => 'Créer les pages et leurs textes (FR et EN)' );
	$steps[] = array( 'id' => 'shop',     'file' => 'shop.php',  'label' => 'Monter la boutique (catégories, tailles)' );
	$steps[] = array( 'id' => 'seo',      'file' => 'seo.php',   'label' => 'Poser les titres et descriptions pour Google' );
	$steps[] = array( 'id' => 'langues',  'file' => '',          'label' => 'Télécharger les traductions françaises' );
	$steps[] = array( 'id' => 'menage',   'file' => '',          'label' => 'Ranger : doublons, confidentialité, icône' );

	return $steps;
}

/**
 * Les quatre pages que WooCommerce crée pour lui-même, et le slug français voulu.
 */
function morgane_wc_pages() {
	return array(
		'woocommerce_shop_page_id'      => array( 'shop',       'Boutique',   'boutique' ),
		'woocommerce_cart_page_id'      => array( 'cart',       'Panier',     'panier' ),
		'woocommerce_checkout_page_id'  => array( 'checkout',   'Commande',   'commande' ),
		'woocommerce_myaccount_page_id' => array( 'my-account', 'Mon compte', 'mon-compte' ),
	);
}

/**
 * Premier passage, avant tout le reste.
 *
 * L'ordre n'est pas cosmétique. Le nom du site doit être posé **avant** que
 * Polylang n'existe : l'extension enregistre `blogname` comme chaîne
 * traduisible au moment où elle démarre, et fige alors la valeur qu'elle
 * trouve. Posé après, le site anglais garde éternellement l'ancien nom — c'est
 * exactement ce qui est arrivé au premier essai, où l'anglais s'appelait encore
 * « Theme Test Site » alors que la base disait le contraire.
 *
 * Les pages de WooCommerce sont renommées ici pour la même raison : plus tôt
 * elles portent leur nom définitif et leur langue, moins elles risquent d'être
 * recréées en double quand le contexte de langue bascule.
 */
function morgane_install_site() {
	update_option( 'blogname', 'Morgane Cachia – Art' );
	update_option( 'blogdescription', 'Illustratrice, artiste peintre et muraliste à Montréal' );
	WP_CLI::log( 'Site nommé.' );

	// Permaliens : tout le thème et tous les scripts partent de cette structure.
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		WP_CLI::log( 'Permaliens passés en /%postname%/.' );
	}

	if ( function_exists( 'WC' ) ) {
		// WooCommerce ouvre en mode « bientôt disponible » : la boutique et les
		// fiches produit renvoient une page d'attente au lieu du catalogue.
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'woocommerce_store_pages_only', 'no' );
		update_option( 'woocommerce_default_country', 'CA:QC' );
		update_option( 'woocommerce_currency', 'CAD' );

		/*
		 * Comptes clients. WooCommerce ferme l'inscription par défaut : la page
		 * « Mon compte » n'affiche alors qu'un formulaire de connexion, sans
		 * moyen d'en créer un — un cul-de-sac pour qui vient d'acheter.
		 *
		 * La commande en tant qu'invité reste ouverte : obliger à créer un
		 * compte pour acheter un tirage fait perdre des ventes, et rien dans ce
		 * site n'exige un compte.
		 */
		update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );

		// Le texte de confidentialité de l'inscription est livré en anglais et
		// n'est pas une chaîne traduisible : c'est une option, à écrire.
		update_option(
			'woocommerce_registration_privacy_policy_text',
			'Vos données servent à traiter votre commande, à vous accompagner sur ce site et pour les usages décrits dans notre [privacy_policy].'
		);
		update_option(
			'woocommerce_checkout_privacy_policy_text',
			'Vos données servent à traiter votre commande, à vous accompagner sur ce site et pour les usages décrits dans notre [privacy_policy].'
		);
		WP_CLI::log( 'WooCommerce : boutique ouverte, devise CAD, Québec.' );

		foreach ( morgane_wc_pages() as $option => $cible ) {
			$id = (int) get_option( $option );
			if ( ! $id || ! get_post( $id ) ) {
				continue;
			}
			// On ne touche pas à un renommage déjà fait à la main.
			if ( get_post_field( 'post_name', $id ) === $cible[0] ) {
				wp_update_post( array( 'ID' => $id, 'post_title' => $cible[1], 'post_name' => $cible[2] ) );
				WP_CLI::log( 'Page WooCommerce : ' . $cible[0] . ' → ' . $cible[2] );
			}
		}
	}

	WP_CLI::success( 'Site préparé.' );
}

/**
 * Traductions françaises de WordPress, de WooCommerce et de Polylang.
 *
 * Sans elles, la page de paiement s'affiche en anglais — « Contact information »,
 * « Shipping address », « Place Order » — sur un site dont la langue est le
 * français. Ce n'est pas le thème : ces libellés viennent du paquet de langue de
 * WooCommerce, qu'un WordPress fraîchement installé n'a pas toujours téléchargé.
 *
 * C'est exactement ce que fait le bouton « Mettre à jour les traductions » du
 * tableau de bord. On le déclenche ici pour ne pas dépendre d'un clic de plus.
 */
function morgane_install_langues() {
	// `file.php` avant l'upgrader : c'est lui qui apporte
	// request_filesystem_credentials(), dont Language_Pack_Upgrader a besoin et
	// que WordPress ne charge pas de lui-même hors des écrans d'administration.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';

	if ( ! class_exists( 'Language_Pack_Upgrader' ) ) {
		WP_CLI::warning( 'Mise à jour des traductions indisponible sur cet hébergement.' );
		return;
	}

	/*
	 * Forcer une nouvelle interrogation de wordpress.org avant de télécharger.
	 *
	 * Le bouton « Mettre à jour les traductions » du tableau de bord répond
	 * souvent « tout est à jour » alors que le paquet manque : WordPress ne
	 * redemande la liste que toutes les douze heures, et une extension installée
	 * depuis la dernière vérification n'y figure pas. Il ne ment pas — il répond
	 * d'après un cache qui ignore WooCommerce.
	 *
	 * On vide ce cache et on redemande. C'est la seule façon d'obtenir le paquet
	 * le jour même de l'installation.
	 */
	delete_site_transient( 'update_plugins' );
	delete_site_transient( 'update_themes' );
	delete_site_transient( 'update_core' );
	wp_clean_plugins_cache( true );
	wp_clean_themes_cache( true );
	wp_update_plugins();
	wp_update_themes();

	/*
	 * Installer d'abord les langues au niveau du cœur.
	 *
	 * WordPress ne va chercher la traduction d'une extension que pour les
	 * langues qu'il a déjà installées pour lui-même. Tant que `fr_CA` n'est pas
	 * dans cette liste, il n'a rien à mettre à jour pour WooCommerce et répond
	 * « 0 paquet » — sans que rien ne soit en panne.
	 *
	 * On lui demande donc explicitement les locales dont ce site a besoin :
	 * la sienne, et celles que Polylang déclare.
	 */
	$locales = array( get_locale() );
	if ( function_exists( 'pll_languages_list' ) ) {
		$locales = array_merge( $locales, (array) pll_languages_list( array( 'fields' => 'locale' ) ) );
	}
	$locales = array_unique( array_filter( $locales ) );
	$deja    = get_available_languages();

	WP_CLI::log( 'Langue du site : ' . get_locale() . ' — installées : ' . ( implode( ', ', $deja ) ?: 'aucune' ) );
	WP_CLI::log( 'Langues nécessaires : ' . implode( ', ', $locales ) );

	foreach ( $locales as $locale ) {
		if ( 'en_US' === $locale || in_array( $locale, $deja, true ) ) {
			continue;
		}
		$pose = wp_download_language_pack( $locale );
		if ( $pose ) {
			WP_CLI::log( 'Langue ' . $locale . ' installée pour WordPress.' );
		} else {
			WP_CLI::warning( 'Langue ' . $locale . ' : téléchargement refusé par wordpress.org.' );
		}
	}

	// La liste a pu changer : on la relit avant de demander les extensions.
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();

	$upgrader = new Language_Pack_Upgrader( new Automatic_Upgrader_Skin() );
	$resultat = $upgrader->bulk_upgrade();

	if ( is_wp_error( $resultat ) ) {
		WP_CLI::warning( 'Traductions : ' . $resultat->get_error_message() );
		return;
	}

	$posees = is_array( $resultat ) ? count( array_filter( $resultat ) ) : 0;
	WP_CLI::success( $posees . ' paquet(s) de langue installé(s) ou mis à jour.' );

	/*
	 * Constat, pas action. Un « 0 paquet » ne dit pas si la traduction est déjà
	 * là ou si personne ne la propose : sans ces trois lignes, il n'y a rien à
	 * diagnostiquer à distance.
	 */
	$dossier = WP_LANG_DIR . '/plugins/';
	foreach ( $locales as $locale ) {
		$mo = $dossier . 'woocommerce-' . $locale . '.mo';
		WP_CLI::log( 'woocommerce-' . $locale . '.mo : ' . ( file_exists( $mo ) ? 'présent' : 'ABSENT' ) );
	}

	$dispo = wp_get_translation_updates();
	WP_CLI::log( 'Mises à jour de traduction proposées : ' . count( $dispo ) );
	foreach ( array_slice( $dispo, 0, 4 ) as $d ) {
		WP_CLI::log( '  · ' . $d->slug . ' (' . $d->language . ')' );
	}

	// La langue réellement servie sur la page « Mon compte » : si Polylang la
	// range en anglais, aucun paquet français n'y changera rien.
	if ( function_exists( 'pll_get_post_language' ) && function_exists( 'wc_get_page_id' ) ) {
		$compte = wc_get_page_id( 'myaccount' );
		if ( $compte > 0 ) {
			$lang = pll_get_post_language( $compte, 'locale' );
			WP_CLI::log( 'Page « Mon compte » (#' . $compte . ') rangée par Polylang en : ' . ( $lang ?: 'AUCUNE LANGUE' ) );
		}
	}
}

/**
 * Dernier passage : ranger derrière soi.
 */
function morgane_install_menage() {

	/*
	 * Doublons de pages WooCommerce.
	 *
	 * Au premier essai sur une installation vierge, quatre pages en double sont
	 * apparues en plein milieu de la création des contenus — mêmes titres,
	 * slugs suffixés d'un « -2 ». WooCommerce recrée ses pages quand il ne les
	 * retrouve pas, et Polylang les lui cachait le temps d'une bascule de
	 * langue. La cause est traitée en amont (langues explicites dans les
	 * requêtes, renommage plus tôt) ; ce filet rattrape ce qui passerait quand
	 * même, car une boutique avec deux pages « Panier » est un piège silencieux.
	 *
	 * Règle : celle que WooCommerce désigne fait foi, les autres partent à la
	 * corbeille — jamais de suppression définitive, au cas où Morgane y aurait
	 * mis quelque chose.
	 */
	if ( function_exists( 'WC' ) ) {
		foreach ( morgane_wc_pages() as $option => $cible ) {
			$garde = (int) get_option( $option );
			if ( ! $garde || ! get_post( $garde ) ) {
				continue;
			}

			$motif   = '^(' . preg_quote( $cible[0], '/' ) . '|' . preg_quote( $cible[2], '/' ) . ')(-[0-9]+)?$';
			$doubles = get_posts( array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'lang'             => '',
				'suppress_filters' => true,
			) );

			foreach ( $doubles as $id ) {
				if ( (int) $id === $garde ) {
					continue;
				}
				if ( preg_match( '/' . $motif . '/', (string) get_post_field( 'post_name', $id ) ) ) {
					wp_trash_post( $id );
					WP_CLI::warning( 'Doublon mis à la corbeille : #' . $id . ' (' . get_post_field( 'post_name', $id ) . ')' );
				}
			}

			// Le slug voulu a pu être pris par le doublon : on le reprend.
			if ( get_post_field( 'post_name', $garde ) !== $cible[2] ) {
				wp_update_post( array( 'ID' => $garde, 'post_title' => $cible[1], 'post_name' => $cible[2] ) );
				WP_CLI::log( 'Slug repris : #' . $garde . ' → ' . $cible[2] );
			}
		}
	}

	// Contenu de démonstration de WordPress, qui se ferait indexer tel quel.
	// La politique de confidentialité est épargnée : elle est en brouillon, et
	// une boutique québécoise devra la remplir pour la Loi 25.
	foreach ( array( 'hello-world', 'sample-page' ) as $slug ) {
		$post = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );
		if ( $post ) {
			wp_delete_post( $post->ID, true );
			WP_CLI::log( 'Supprimé : ' . $slug );
		}
	}

	// Page de confidentialité officielle : WooCommerce l'annonce alors d'elle-même
	// au moment du paiement, avec le lien qui va bien.
	$privacy = get_page_by_path( 'confidentialite' );
	if ( $privacy ) {
		update_option( 'wp_page_for_privacy_policy', $privacy->ID );
		WP_CLI::log( 'Page de confidentialité déclarée.' );
	}

	// Icône du site, prise dans le thème.
	if ( ! (int) get_option( 'site_icon' ) ) {
		$icone = get_theme_file_path( 'assets/img/favicon.png' );
		if ( file_exists( $icone ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$tmp = wp_tempnam( 'favicon.png' );
			if ( $tmp && copy( $icone, $tmp ) ) {
				$id = media_handle_sideload(
					array( 'name' => 'favicon.png', 'tmp_name' => $tmp ),
					0,
					'Logo Morgane Cachia – Art'
				);
				if ( is_wp_error( $id ) ) {
					@unlink( $tmp );
				} else {
					update_option( 'site_icon', $id );
					WP_CLI::log( 'Icône du site posée.' );
				}
			}
		}
	}

	/*
	 * Réglages d'URL de Polylang, reposés ici et pas seulement dans pll-setup.
	 *
	 * `redirect_lang` ne tient que s'il existe une page d'accueil statique — et
	 * à l'étape des langues, elle n'existe pas encore : Polylang remet
	 * l'option à faux sans rien dire. Résultat, /en/ repartait en redirection
	 * vers /en/home/, et c'est de là que venaient les adresses WooCommerce
	 * anglaises cassées en /en/home/boutique/. On la repose maintenant que
	 * l'accueil est là.
	 */
	if ( function_exists( 'PLL' ) && get_option( 'page_on_front' ) ) {
		$pll_options = get_option( 'polylang' );
		if ( is_array( $pll_options ) || $pll_options instanceof ArrayAccess ) {
			$pll_options['force_lang']    = 1;
			$pll_options['hide_default']  = 1;
			$pll_options['redirect_lang'] = true;
			update_option( 'polylang', $pll_options );
			delete_transient( 'pll_languages_list' );
			WP_CLI::log( 'Polylang : /en/ sert désormais l’accueil anglais.' );
		}
	}

	// Purge dure : les règles de langue viennent de changer.
	flush_rewrite_rules( true );
	WP_CLI::success( 'Rangement terminé.' );
}

/**
 * Ce qui doit être vrai avant de lancer quoi que ce soit.
 *
 * @return array<int,array{ok:bool,texte:string,aide:string}>
 */
function morgane_install_prealables() {
	$upload = wp_upload_dir();
	$dossier_photos = '';
	foreach ( (array) apply_filters( 'morgane_photos_dir', array(
		'/pictures',
		trailingslashit( $upload['basedir'] ) . 'morgane-photos',
	) ) as $candidat ) {
		if ( is_dir( $candidat ) ) {
			$dossier_photos = $candidat;
			break;
		}
	}

	return array(
		array(
			'ok'    => function_exists( 'pll_set_post_language' ),
			'texte' => 'Polylang est actif',
			'aide'  => 'Obligatoire : les pages sont créées en deux langues. Extensions > Ajouter > « Polylang ».',
		),
		array(
			'ok'    => function_exists( 'WC' ),
			'texte' => 'WooCommerce est actif',
			'aide'  => 'Nécessaire pour la boutique. Sans lui, l’étape « boutique » sera sautée.',
		),
		array(
			'ok'    => (bool) $dossier_photos,
			'texte' => $dossier_photos
				? 'Photos trouvées dans ' . esc_html( $dossier_photos )
				: 'Dossier de photos introuvable',
			'aide'  => 'Déposez les 23 fichiers par FTP dans ' . esc_html( trailingslashit( $upload['basedir'] ) . 'morgane-photos/' ) . ' — sinon les pages seront créées sans images.',
		),
		array(
			'ok'    => wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ),
			'texte' => 'PHP sait écrire du WebP',
			'aide'  => 'GD ou Imagick avec le support WebP. À demander à l’hébergeur si la case est rouge.',
		),
		array(
			'ok'    => is_writable( $upload['basedir'] ),
			'texte' => 'Le dossier des téléversements est accessible en écriture',
			'aide'  => 'Sans ça, aucune image ne peut être importée.',
		),
	);
}

/**
 * La page, sous Apparence.
 */
add_action( 'admin_menu', function () {
	add_theme_page(
		'Installer le contenu',
		'Installer le contenu',
		'manage_options',
		'morgane-install',
		'morgane_install_page'
	);
} );

function morgane_install_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}

	$steps      = morgane_install_steps();
	$prealables = morgane_install_prealables();
	$bloque     = ! $prealables[0]['ok'];               // Polylang
	$deja       = (bool) get_page_by_path( 'a-propos' ); // le site a déjà du contenu
	?>
	<div class="wrap">
		<h1>Installer le contenu</h1>

		<p style="max-width:42em">
			Le thème n’est que l’habillage : les textes, les pages, les photos et la
			structure de boutique vivent dans WordPress. Ce bouton les écrit, dans
			les deux langues. L’opération est <strong>rejouable</strong> — elle ne
			duplique rien et n’écrase pas une page déjà modifiée.
		</p>

		<?php if ( $deja ) : ?>
			<div class="notice notice-info inline" style="margin:1em 0">
				<p>Le contenu semble déjà installé. Relancer est sans danger : seules
				les pages vides et les photos manquantes seront complétées.</p>
			</div>
		<?php endif; ?>

		<h2>Avant de commencer</h2>
		<table class="widefat striped" style="max-width:52em">
			<tbody>
			<?php foreach ( $prealables as $p ) : ?>
				<tr>
					<td style="width:2em;font-size:1.2em"><?php echo $p['ok'] ? '✅' : '⚠️'; ?></td>
					<td>
						<strong><?php echo esc_html( $p['texte'] ); ?></strong>
						<?php if ( ! $p['ok'] ) : ?>
							<br><span class="description"><?php echo wp_kses_post( $p['aide'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:1.5em">
			<button type="button" class="button button-primary button-hero" id="morgane-go"
				<?php disabled( $bloque ); ?>>
				<?php echo $deja ? 'Relancer l’installation' : 'Installer le contenu'; ?>
			</button>
			<?php if ( $bloque ) : ?>
				<span class="description" style="margin-left:1em">Activez Polylang pour continuer.</span>
			<?php endif; ?>
		</p>

		<h2>Réécrire les textes</h2>
		<p style="max-width:42em">
			L’installation ne touche jamais à une page déjà remplie : c’est ce qui
			protège ce que vous avez écrit depuis l’éditeur. Après une mise à jour
			du thème qui change les textes, ce bouton-ci force la réécriture des
			pages dans les deux langues.
			<strong>Les modifications faites à la main dans l’éditeur seront
			perdues.</strong> Les produits, les commandes et les réglages de la
			boutique ne sont pas concernés.
		</p>
		<p>
			<button type="button" class="button button-secondary" id="morgane-pages">
				Réécrire les textes des pages
			</button>
		</p>

		<h2>Fiches produit anglaises</h2>
		<p style="max-width:42em">
			Avant la version 0.11, chaque œuvre existait en deux fiches — une par
			langue — avec deux stocks séparés. Ce bouton replie le texte anglais
			dans la fiche française, puis supprime les doublons.
			<strong>À jouer une seule fois.</strong> Les commandes déjà passées
			ne sont pas touchées.
		</p>
		<p>
			<button type="button" class="button button-secondary" id="morgane-migrer">
				Replier les fiches anglaises
			</button>
		</p>

		<h2>Traductions</h2>
		<p style="max-width:42em">
			Si la boutique ou la page « Mon compte » s’affichent en anglais, c’est
			que le paquet de langue de WooCommerce manque. Le bouton « Mettre à
			jour les traductions » du tableau de bord répond alors « tout est à
			jour » : il ne ment pas, il répond d’après une liste que WordPress ne
			redemande que toutes les douze heures, et où une extension installée
			depuis ne figure pas. Ce bouton-ci vide cette liste et redemande.
		</p>
		<p>
			<button type="button" class="button button-secondary" id="morgane-langues">
				Télécharger les traductions françaises
			</button>
		</p>

		<div id="morgane-progres" style="display:none;max-width:52em">
			<div style="background:#e0e0e0;border-radius:3px;height:22px;overflow:hidden">
				<div id="morgane-barre" style="background:#2271b1;height:100%;width:0;transition:width .2s"></div>
			</div>
			<p id="morgane-etape" style="font-weight:600"></p>
			<pre id="morgane-journal" style="background:#1d2327;color:#f0f0f1;padding:1em;max-height:24em;overflow:auto;font-size:12px;line-height:1.6"></pre>
		</div>
	</div>

	<script>
	(function () {
		var etapes = <?php echo wp_json_encode( array_map( function ( $s ) {
			return array( 'id' => $s['id'], 'label' => $s['label'] );
		}, $steps ) ); ?>;
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'morgane_install' ) ); ?>;
		var ajax  = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;

		var bouton  = document.getElementById('morgane-go');
		var zone    = document.getElementById('morgane-progres');
		var barre   = document.getElementById('morgane-barre');
		var titre   = document.getElementById('morgane-etape');
		var journal = document.getElementById('morgane-journal');

		function ecrire(niveau, texte) {
			var prefixe = niveau === 'warning' ? '⚠ ' : (niveau === 'success' ? '✓ ' : '  ');
			journal.textContent += prefixe + texte + '\n';
			journal.scrollTop = journal.scrollHeight;
		}

		async function lancer(liste, boutonCliqué, extra) {
			boutonCliqué.disabled = true;
			zone.style.display = 'block';
			journal.textContent = '';

			for (var i = 0; i < liste.length; i++) {
				var e = liste[i];
				titre.textContent = e.label + '  (' + (i + 1) + '/' + liste.length + ')';
				barre.style.width = Math.round((i / liste.length) * 100) + '%';

				var corps = new FormData();
				corps.append('action', 'morgane_install_step');
				corps.append('step', e.id);
				corps.append('_wpnonce', nonce);
				if (extra) {
					Object.keys(extra).forEach(function (k) { corps.append(k, extra[k]); });
				}

				var reponse, data;
				try {
					reponse = await fetch(ajax, { method: 'POST', body: corps, credentials: 'same-origin' });
					data = await reponse.json();
				} catch (err) {
					ecrire('warning', 'Étape « ' + e.label + " » : la requête n'a pas abouti (" + err.message + ').');
					titre.textContent = 'Interrompu.';
					boutonCliqué.disabled = false;
					return;
				}

				(((data.data || {}).lignes) || []).forEach(function (l) { ecrire(l[0], l[1]); });

				if (!data.success) {
					ecrire('warning', 'Arrêt : ' + ((data.data || {}).message || 'erreur inconnue'));
					titre.textContent = 'Interrompu à l’étape « ' + e.label + ' ».';
					boutonCliqué.disabled = false;
					return;
				}
			}

			barre.style.width = '100%';
			titre.textContent = 'Terminé.';
			ecrire('success', 'Terminé. Allez voir le site.');
			boutonCliqué.disabled = false;
		}

		bouton.addEventListener('click', function () { lancer(etapes, bouton); });

		var boutonPages = document.getElementById('morgane-pages');
		if (boutonPages) {
			boutonPages.addEventListener('click', function () {
				// Une confirmation : l'étape est la seule du lot qui détruise
				// quelque chose, et rien ne la rattrape ensuite.
				if (!window.confirm('Réécrire les textes des pages ?\n\nCe qui a été modifié à la main dans l’éditeur sera perdu.')) {
					return;
				}
				lancer(etapes.filter(function (e) { return e.id === 'pages'; }), boutonPages, { force: '1' });
			});
		}

		var boutonLangues = document.getElementById('morgane-langues');
		if (boutonLangues) {
			boutonLangues.addEventListener('click', function () {
				lancer(etapes.filter(function (e) { return e.id === 'langues'; }), boutonLangues);
			});
		}

		var boutonMigrer = document.getElementById('morgane-migrer');
		if (boutonMigrer) {
			boutonMigrer.addEventListener('click', function () {
				if (!window.confirm('Replier les fiches anglaises dans les françaises, puis les supprimer ?\n\nÀ ne faire qu\'une fois.')) {
					return;
				}
				lancer([{ id: 'migrer', label: 'Replier les fiches anglaises' }], boutonMigrer);
			});
		}
	})();
	</script>
	<?php
}

/**
 * Le lanceur : une étape par requête.
 *
 * Accroché à `admin_post_` et non à `wp_ajax_`, alors que le navigateur appelle
 * bien en arrière-plan. La raison est têtue : **Polylang ne charge pas son API
 * pendant une requête AJAX**. Vérifié sur une installation neuve —
 * `function_exists('PLL')` est vrai sous `WP_ADMIN` seul, faux dès que
 * `DOING_AJAX` est posé, et tous les scripts de langue s'arrêtent alors sur
 * « Polylang introuvable ». `admin-post.php` donne un contexte
 * d'administration complet sans cette constante.
 */
add_action( 'admin_post_morgane_install_step', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Droits insuffisants.' ), 403 );
	}
	check_admin_referer( 'morgane_install' );

	$demande = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
	$etape   = null;
	foreach ( morgane_install_steps() as $s ) {
		if ( $s['id'] === $demande ) {
			$etape = $s;
			break;
		}
	}

	// La migration ne figure pas dans `morgane_install_steps()` : elle ne doit
	// jamais partir avec le bouton d'installation, qui est rejouable et sans
	// effet destructeur.
	if ( ! $etape && 'migrer' === $demande ) {
		$etape = array( 'id' => 'migrer', 'file' => 'migrer-produits.php', 'label' => 'Replier les fiches anglaises' );
	}

	if ( ! $etape ) {
		wp_send_json_error( array( 'message' => 'Étape inconnue : ' . $demande ) );
	}

	/*
	 * `pages.php` refuse par défaut d'écraser une page qui a déjà du contenu :
	 * c'est ce qui protège le travail fait dans l'éditeur. Seul le bouton dédié
	 * lève cette garde — jamais le bouton d'installation, qui reste rejouable
	 * sans rien détruire.
	 */
	if ( 'pages' === $etape['id'] && ! empty( $_POST['force'] ) ) {
		$GLOBALS['morgane_force'] = '1';
	}

	require_once get_theme_file_path( 'install/wp-cli-shim.php' );
	WP_CLI::$lignes = array();

	try {
		if ( '' === $etape['file'] ) {
			if ( 'site' === $etape['id'] ) {
				morgane_install_site();
			} elseif ( 'langues' === $etape['id'] ) {
				morgane_install_langues();
			} else {
				morgane_install_menage();
			}
		} else {
			// La boutique n'a pas de sens sans WooCommerce : on saute plutôt que
			// de laisser le script s'arrêter sur une erreur.
			if ( 'shop.php' === $etape['file'] && ! function_exists( 'WC' ) ) {
				WP_CLI::warning( 'WooCommerce inactif : étape sautée.' );
			} else {
				if ( isset( $etape['photo'] ) ) {
					$GLOBALS['morgane_install_only'] = $etape['photo'];
				}
				require get_theme_file_path( 'install/' . $etape['file'] );
			}
		}
		wp_send_json_success( array( 'lignes' => WP_CLI::$lignes ) );
	} catch ( Throwable $e ) {
		wp_send_json_error( array(
			'message' => $e->getMessage(),
			'lignes'  => WP_CLI::$lignes,
		) );
	}
} );

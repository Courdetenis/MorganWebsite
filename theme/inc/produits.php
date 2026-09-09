<?php
/**
 * Produits bilingues sur une fiche unique.
 *
 * Polylang crée un article par langue : deux fiches pour une œuvre, donc deux
 * compteurs de stock. Vérifié le 2026-09-08 — poser le stock français à 1
 * laissait l'anglais à 25. Pour une pièce unique, c'est la même œuvre vendue
 * deux fois.
 *
 * Les produits sortent donc de Polylang. Il n'existe plus qu'un article, donc
 * un seul stock, sans rien à synchroniser. L'anglais vit dans trois métas de
 * cette même fiche, échangées au rendu.
 *
 * C'est le principe déjà appliqué au Panier, à la Commande et à Mon compte :
 * une page unique servie dans les deux langues.
 *
 * @package Morgane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MORGANE_META_NOM_EN    = '_morgane_nom_en';
const MORGANE_META_RESUME_EN = '_morgane_resume_en';
const MORGANE_META_DESC_EN   = '_morgane_description_en';

// Priorité du filtre sur get_post_metadata qui traduit les libellés de champs.
// Les trois appels (remove, add interne, add initial) doivent utiliser la même
// valeur, sinon le garde-fou anti-récursion casse et provoque une boucle infinie.
const MORGANE_PRIORITE_META_WAPF = 10;

/* -------------------------------------------------------------------------
   Sécurité structurelle : la sortie de Polylang décidée par le thème, pas
   par un réglage.
   ------------------------------------------------------------------------- */

/**
 * Retire `product`, `product_cat` et `product_tag` de ce que Polylang traduit
 * — depuis le thème, pas seulement depuis l'option enregistrée en base.
 *
 * `install/pll-cpt.php` retire déjà ces trois entrées de l'option `polylang`
 * au moment de la migration (une œuvre = un article = un stock, voir plus
 * haut). Mais une option se recoche en deux clics, dans Langues > Réglages >
 * Types de contenus : rien, sinon ce filtre, n'empêcherait alors de recréer
 * l'incident du double stock — une œuvre traduisible redonnerait une fiche
 * française et une fiche anglaise, chacune avec son compteur.
 *
 * En le posant ici, l'invariant ne dépend plus de l'état d'une case à cocher :
 * même si `product` était recoché dans Polylang, l'extension ne le traiterait
 * plus comme traduit, parce que le thème le lui interdit à chaque lecture de
 * la liste. Vérifié : passer le produit #13 en langue `en` (test manuel de la
 * revue) n'a été rendu possible qu'en désactivant ces filtres.
 */
add_filter( 'pll_get_post_types', function ( $post_types ) {
	unset( $post_types['product'] );
	return $post_types;
} );

add_filter( 'pll_get_taxonomies', function ( $taxonomies ) {
	unset( $taxonomies['product_cat'], $taxonomies['product_tag'] );
	return $taxonomies;
} );

/**
 * Le texte anglais d'une fiche, ou '' s'il n'a pas été saisi.
 *
 * Le vide est significatif : il fait retomber l'affichage sur le français,
 * plutôt que de montrer un blanc à la place d'un titre.
 */
function morgane_texte_en( $id, $cle ) {
	$valeur = get_post_meta( (int) $id, $cle, true );

	return is_string( $valeur ) ? trim( $valeur ) : '';
}

/**
 * Les trois champs anglais, dans l'éditeur de produit.
 */
function morgane_champs_en() {
	return array(
		MORGANE_META_NOM_EN    => array( 'Nom (EN)', 'input' ),
		MORGANE_META_RESUME_EN => array( 'Résumé court (EN)', 'textarea' ),
		MORGANE_META_DESC_EN   => array( 'Description (EN)', 'textarea' ),
	);
}

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'morgane-produit-en',
		'Version anglaise',
		'morgane_metaboite_en',
		'product',
		'normal',
		'high'
	);
} );

/**
 * La métaboîte. Volontairement trois champs et rien de plus : ce qui n'est pas
 * rempli retombe sur le français, ce qui rend l'anglais facultatif œuvre par
 * œuvre.
 */
function morgane_metaboite_en( $post ) {
	wp_nonce_field( 'morgane_produit_en', 'morgane_produit_en_nonce' );

	echo '<p class="description">Laissé vide, le texte français est affiché aussi sur le site anglais.</p>';

	foreach ( morgane_champs_en() as $cle => $champ ) {
		$valeur = get_post_meta( $post->ID, $cle, true );
		printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br>', esc_attr( $cle ), esc_html( $champ[0] ) );

		if ( 'textarea' === $champ[1] ) {
			printf(
				'<textarea id="%1$s" name="%1$s" rows="4" style="width:100%%">%2$s</textarea></p>',
				esc_attr( $cle ),
				esc_textarea( (string) $valeur )
			);
		} else {
			printf(
				'<input type="text" id="%1$s" name="%1$s" value="%2$s" style="width:100%%"></p>',
				esc_attr( $cle ),
				esc_attr( (string) $valeur )
			);
		}
	}
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['morgane_produit_en_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['morgane_produit_en_nonce'] ), 'morgane_produit_en' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( morgane_champs_en() as $cle => $champ ) {
		if ( ! isset( $_POST[ $cle ] ) ) {
			continue;
		}

		// Le nom est du texte pur ; les deux autres acceptent la même mise en
		// forme que leurs équivalents français.
		$valeur = ( 'input' === $champ[1] )
			? sanitize_text_field( wp_unslash( $_POST[ $cle ] ) )
			: wp_kses_post( wp_unslash( $_POST[ $cle ] ) );

		if ( '' === trim( $valeur ) ) {
			delete_post_meta( $post_id, $cle );
		} else {
			update_post_meta( $post_id, $cle, $valeur );
		}
	}
} );

/**
 * Exclut les trois métas anglaises d'une duplication de produit.
 *
 * Le mode d'emploi de Morgane est de dupliquer le produit modèle pour chaque
 * nouvelle œuvre. `WC_Admin_Duplicate_Product` recopie par défaut toutes les
 * métas `_…` du produit copié : sans ce filtre, la nouvelle fiche hériterait
 * du nom, du résumé et de la description anglais de l'œuvre dupliquée.
 *
 * L'erreur ne se verrait pas dans l'éditeur : un champ anglais oublié retombe
 * silencieusement sur le français (voir `morgane_texte_en()`), mais un champ
 * anglais hérité par erreur, lui, s'affiche activement — le mauvais titre,
 * au premier chargement de la page anglaise de la nouvelle fiche.
 */
add_filter( 'woocommerce_duplicate_product_exclude_meta', function ( $exclure ) {
	$exclure[] = MORGANE_META_NOM_EN;
	$exclure[] = MORGANE_META_RESUME_EN;
	$exclure[] = MORGANE_META_DESC_EN;
	return $exclure;
} );

/**
 * Faut-il servir l'anglais ?
 *
 * Jamais dans l'administration ni en REST : Morgane doit lire et modifier ce
 * qu'elle a saisi, et l'éditeur de blocs passe par REST pour lister les
 * produits. Même garde que le filtre `the_title` de `inc/boutique.php`.
 */
function morgane_produit_en_actif() {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}

	return 'en' === morgane_lang();
}

/**
 * Le nom de l'œuvre.
 *
 * `woocommerce_product_get_name` couvre presque tout WooCommerce — grilles,
 * panier, courriels de commande. `the_title` rattrape ce que WordPress rend
 * lui-même à partir du nom de l'article : le <h1> de la fiche, le fil
 * d'Ariane.
 *
 * La balise <title> n'en fait PAS partie, contrairement à ce qu'affirmait une
 * version antérieure de ce commentaire. SEOPress la compose lui-même à partir
 * de `get_post_field( 'post_title', … )` — la valeur brute en base, jamais
 * celle que rend `the_title` ou `get_the_title()` (vérifié dans
 * `seopress_get_dynamic_variables()`, du plugin). Elle a son propre filtre,
 * plus bas (`seopress_titles_title`), symétrique de `seopress_titles_canonical`
 * déjà posé dans `inc/i18n.php` pour l'accueil.
 */
add_filter( 'woocommerce_product_get_name', function ( $nom, $produit ) {
	if ( ! morgane_produit_en_actif() ) {
		return $nom;
	}

	$en = morgane_texte_en( $produit->get_id(), MORGANE_META_NOM_EN );

	return '' !== $en ? $en : $nom;
}, 10, 2 );

add_filter( 'the_title', function ( $titre, $id = 0 ) {
	if ( ! $id || 'product' !== get_post_type( $id ) || ! morgane_produit_en_actif() ) {
		return $titre;
	}

	$en = morgane_texte_en( $id, MORGANE_META_NOM_EN );

	return '' !== $en ? $en : $titre;
}, 10, 2 );

/**
 * La balise <title>, dans l'onglet du navigateur, les résultats de recherche
 * et les aperçus de partage.
 *
 * Ni `woocommerce_product_get_name` ni `the_title` ne l'atteignent (voir le
 * commentaire au-dessus du premier des deux) : SEOPress la compose à partir
 * du nom brut en base. `seopress_titles_title` est le filtre que l'extension
 * applique sur le texte déjà assemblé (gabarit résolu, séparateur et nom du
 * site compris), juste avant de le renvoyer — on y remplace le nom français
 * qu'il contient par l'anglais.
 */
add_filter( 'seopress_titles_title', function ( $titre ) {
	if ( ! morgane_produit_en_actif() || ! is_singular( 'product' ) ) {
		return $titre;
	}

	$id = get_queried_object_id();
	$fr = get_post_field( 'post_title', $id );
	$en = morgane_texte_en( $id, MORGANE_META_NOM_EN );

	if ( '' === $en || '' === $fr ) {
		return $titre;
	}

	return str_replace( $fr, $en, $titre );
} );

/**
 * Le permalien d'une œuvre, dans la langue active.
 *
 * `product` n'étant plus traduit par Polylang (voir plus haut), l'extension
 * n'ajoute plus le préfixe de langue aux permaliens de produit :
 * `get_permalink()` rendait toujours l'adresse française, y compris sur le
 * site anglais. La grille de la boutique, le fil d'Ariane et la canonique de
 * SEOPress (qui repose sur `get_permalink()` pour une fiche isolée, voir
 * `seopress_titles_canonical_hook()` du plugin) en héritaient tous : les liens
 * des œuvres ne portaient jamais « /en/ », et menaient à une fiche servie en
 * français.
 *
 * Même remède que `morgane_categorie_url()` pour les catégories
 * (`inc/i18n.php`) : on préfixe par la racine de la langue active.
 *
 * Ce filtre s'exécute partout, y compris dans l'administration — d'où le même
 * garde-fou que le reste du bilinguisme produit : jamais dans l'administration
 * (Morgane doit y voir et pouvoir copier le vrai permalien), ni en REST.
 */
add_filter( 'post_type_link', function ( $lien, $produit ) {
	if ( 'product' !== $produit->post_type || ! morgane_produit_en_actif() ) {
		return $lien;
	}

	$chemin = wp_make_link_relative( $lien ); // « /product/aurore-boreale-tirage/ »

	return untrailingslashit( morgane_lang_root( morgane_lang() ) ) . $chemin;
}, 10, 2 );

/**
 * Le permalien d'une œuvre, dans la langue **demandée** — pas forcément la
 * langue active.
 *
 * Sert au sélecteur de langue (`morgane_url_courante_dans()`, `inc/i18n.php`) :
 * depuis une fiche produit, il doit pouvoir demander l'adresse de la même
 * fiche dans l'*autre* langue. Le filtre `post_type_link` ci-dessus ne sait
 * construire que l'adresse de la langue active — `get_permalink()` n'a pas de
 * paramètre de langue à lui passer.
 *
 * @param int         $id   Identifiant du produit.
 * @param string|null $lang Langue visée. Par défaut, la langue active.
 * @return string URL, ou '' si le produit n'existe pas.
 */
function morgane_produit_url( $id, $lang = null ) {
	$url = get_permalink( $id );
	if ( ! $url ) {
		return '';
	}

	if ( ! $lang ) {
		$lang = morgane_lang();
	}

	if ( function_exists( 'pll_home_url' ) ) {
		$chemin = wp_make_link_relative( $url );

		/*
		 * `get_permalink()` vient d'appliquer le préfixe de la langue *active*
		 * (le filtre `post_type_link` ci-dessus) — pas nécessairement celle
		 * qu'on vise ici. On le retire s'il y est, pour repartir du chemin nu
		 * avant de poser le bon préfixe. Même méthode que `morgane_wc_page_url()`
		 * et `morgane_categorie_url()` (`inc/i18n.php`).
		 */
		$racine_active = wp_make_link_relative( morgane_lang_root( morgane_lang() ) );
		if ( '/' !== $racine_active && 0 === strpos( $chemin, $racine_active ) ) {
			$chemin = '/' . substr( $chemin, strlen( $racine_active ) );
		}

		$url = untrailingslashit( morgane_lang_root( $lang ) ) . $chemin;
	}

	return $url;
}

/**
 * Le résumé court, partout où il est lu.
 *
 * Deux filtres et non un, parce que deux chemins mènent à ce texte et qu'un
 * seul passe par WooCommerce. Le gabarit de fiche du thème est en blocs : c'est
 * `core/post-excerpt` qui affiche le résumé, et il lit `get_the_excerpt()`,
 * jamais `$produit->get_short_description()`. Sans le second filtre, le
 * visiteur anglais voyait le résumé français sous le prix — vérifié.
 */
add_filter( 'get_the_excerpt', function ( $texte, $post = null ) {
	$id = ( $post instanceof WP_Post ) ? $post->ID : 0;

	if ( ! $id || 'product' !== get_post_type( $id ) || ! morgane_produit_en_actif() ) {
		return $texte;
	}

	$en = morgane_texte_en( $id, MORGANE_META_RESUME_EN );

	return '' !== $en ? $en : $texte;
}, 10, 2 );

add_filter( 'woocommerce_product_get_short_description', function ( $texte, $produit ) {
	if ( ! morgane_produit_en_actif() ) {
		return $texte;
	}

	$en = morgane_texte_en( $produit->get_id(), MORGANE_META_RESUME_EN );

	return '' !== $en ? $en : $texte;
}, 10, 2 );

/**
 * La description longue, dans l'onglet de la fiche.
 *
 * `the_content` s'exécute sur toutes les pages : on vérifie le type d'article
 * et qu'on est bien dans la boucle principale de cette fiche, sinon un extrait
 * de produit affiché ailleurs emprunterait la description d'une autre œuvre.
 */
add_filter( 'the_content', function ( $contenu ) {
	if ( ! morgane_produit_en_actif() || ! is_singular( 'product' ) || ! in_the_loop() ) {
		return $contenu;
	}

	$en = morgane_texte_en( get_the_ID(), MORGANE_META_DESC_EN );

	return '' !== $en ? wpautop( $en ) : $contenu;
} );

/**
 * Les libellés des champs d'option, indexés par leur texte français.
 *
 * Une table dans le thème plutôt qu'un écran d'administration : les options
 * sont des formats de papier, elles changent une fois par an. Si le catalogue
 * d'options venait à grossir, c'est ici qu'il faudrait passer à des métas
 * saisies sur le groupe de champs.
 *
 * Les formats (A5, A4…) ne figurent pas : ils s'écrivent pareil dans les deux
 * langues.
 */
function morgane_libelles_options() {
	return array(
		'Taille'      => array( 'fr' => 'Taille',      'en' => 'Size' ),
		'Format'      => array( 'fr' => 'Format',      'en' => 'Format' ),
		'Finition'    => array( 'fr' => 'Finition',    'en' => 'Finish' ),
		'Encadrement' => array( 'fr' => 'Encadrement', 'en' => 'Framing' ),
	);
}

/**
 * Traduit un libellé d'option, ou le rend tel quel s'il est inconnu.
 */
function morgane_libelle_option( $texte ) {
	$table = morgane_libelles_options();

	return isset( $table[ $texte ] ) ? morgane_t( $table[ $texte ] ) : $texte;
}

/**
 * Traduit le libellé du champ en interceptant la lecture de la méta.
 *
 * L'extension de champs produit ne propose aucun filtre sur le libellé d'un
 * champ, on intercepte donc la lecture de la méta où elle range tout le groupe.
 * Seul le libellé du champ est traduit (ex. « Taille » → « Size ») ; la
 * description et les libellés de choix individuels ne le sont pas.
 *
 * Le garde-fou `morgane_produit_en_actif()` tient l'administration à l'écart :
 * l'écran d'édition du groupe doit montrer ce qui est enregistré, sans quoi
 * enregistrer réécrirait l'anglais par-dessus le français.
 */
function morgane_traduire_groupe_champs( $valeur, $post_id, $cle, $unique ) {
	if ( '_wapf_fieldgroup' !== $cle || ! $unique || ! morgane_produit_en_actif() ) {
		return $valeur;
	}

	// On relit sans le filtre, sinon l'appel se rappellerait lui-même.
	remove_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', MORGANE_PRIORITE_META_WAPF );
	$groupe = get_post_meta( $post_id, $cle, true );
	add_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', MORGANE_PRIORITE_META_WAPF, 4 );

	if ( ! is_array( $groupe ) || empty( $groupe['fields'] ) || ! is_array( $groupe['fields'] ) ) {
		return $valeur;
	}

	foreach ( $groupe['fields'] as $i => $champ ) {
		if ( isset( $champ['label'] ) ) {
			$groupe['fields'][ $i ]['label'] = morgane_libelle_option( $champ['label'] );
		}
		// Les libellés de choix ne sont pas traduits aujourd'hui. La structure réelle
		// est $champ['options']['choices'][]{label,…} (un niveau de profondeur
		// supplémentaire) et la table morgane_libelles_options() ne contient que des
		// libellés de champs. Si un choix venait à différer entre les deux langues
		// (ex. « Mat » → « Matte »), c'est ici qu'il faudrait intervenir.
	}

	// `get_post_metadata` attend la valeur emballée dans un tableau.
	return array( $groupe );
}

/*
 * Fonction nommée et non fermeture : le filtre doit se retirer lui-même le
 * temps de relire la méta, ce qu'une fermeture anonyme ne sait pas faire.
 */
add_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', MORGANE_PRIORITE_META_WAPF, 4 );

/* -------------------------------------------------------------------------
   La description des catégories, dans les deux langues.
   ------------------------------------------------------------------------- */

const MORGANE_META_CAT_DESC_EN = '_morgane_cat_description_en';

/**
 * Les catégories ne sont plus traduites par Polylang : il n'existe qu'un terme
 * « Prints », donc qu'une description, écrite en français. Sur le site anglais,
 * le titre du rayon se traduisait mais son texte d'introduction restait
 * français, juste en dessous.
 *
 * Même principe que pour les produits : l'anglais vit dans une métadonnée du
 * terme, saisie sur le même écran, et remplace le français à l'affichage. Laissé
 * vide, le français reste — l'anglais est facultatif rayon par rayon.
 */
function morgane_champ_categorie_en( $terme = null ) {
	$valeur = ( $terme instanceof WP_Term )
		? get_term_meta( $terme->term_id, MORGANE_META_CAT_DESC_EN, true )
		: '';
	?>
	<tr class="form-field">
		<th scope="row"><label for="<?php echo esc_attr( MORGANE_META_CAT_DESC_EN ); ?>">Description (EN)</label></th>
		<td>
			<textarea id="<?php echo esc_attr( MORGANE_META_CAT_DESC_EN ); ?>"
				name="<?php echo esc_attr( MORGANE_META_CAT_DESC_EN ); ?>"
				rows="5" cols="40"><?php echo esc_textarea( (string) $valeur ); ?></textarea>
			<p class="description">Laissée vide, la description française est affichée aussi sur le site anglais.</p>
		</td>
	</tr>
	<?php
}

add_action( 'product_cat_edit_form_fields', 'morgane_champ_categorie_en' );

/*
 * Le formulaire d'ajout n'est pas un tableau : le même balisage y produirait une
 * ligne de tableau orpheline. On ne l'y met donc pas — une catégorie se décrit
 * en anglais après sa création, sur l'écran de modification.
 */

add_action( 'edited_product_cat', function ( $terme_id ) {
	if ( ! isset( $_POST[ MORGANE_META_CAT_DESC_EN ] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	// Le formulaire de terme porte déjà son propre nonce, vérifié par WordPress
	// avant d'arriver ici.
	$valeur = wp_kses_post( wp_unslash( $_POST[ MORGANE_META_CAT_DESC_EN ] ) );

	if ( '' === trim( $valeur ) ) {
		delete_term_meta( $terme_id, MORGANE_META_CAT_DESC_EN );
	} else {
		update_term_meta( $terme_id, MORGANE_META_CAT_DESC_EN, $valeur );
	}
} );

/**
 * La description servie au visiteur.
 *
 * `term_description` couvre le rendu de WooCommerce comme celui des blocs :
 * les deux finissent par l'appeler. Le garde-fou d'administration vient de
 * `morgane_produit_en_actif()` — Morgane doit lire ce qu'elle a saisi.
 */
add_filter( 'term_description', function ( $texte, $terme_id = 0, $taxonomie = '' ) {
	if ( 'product_cat' !== $taxonomie || ! $terme_id || ! morgane_produit_en_actif() ) {
		return $texte;
	}

	$en = get_term_meta( (int) $terme_id, MORGANE_META_CAT_DESC_EN, true );
	$en = is_string( $en ) ? trim( $en ) : '';

	return '' !== $en ? wpautop( $en ) : $texte;
}, 10, 3 );

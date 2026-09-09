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
 * lui-même : le titre de la fiche, le fil d'Ariane, la balise <title>.
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
 * Le résumé court, affiché sous le prix sur la fiche.
 */
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
 * L'extension de champs produit ne propose aucun filtre sur le libellé d'un
 * champ — seulement sur ses options. On intercepte donc la lecture de la méta
 * où elle range tout le groupe : un seul point de passage pour le libellé, la
 * description et les options.
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
	remove_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', 10 );
	$groupe = get_post_meta( $post_id, $cle, true );
	add_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', 10, 4 );

	if ( ! is_array( $groupe ) || empty( $groupe['fields'] ) || ! is_array( $groupe['fields'] ) ) {
		return $valeur;
	}

	foreach ( $groupe['fields'] as $i => $champ ) {
		if ( isset( $champ['label'] ) ) {
			$groupe['fields'][ $i ]['label'] = morgane_libelle_option( $champ['label'] );
		}
		if ( ! empty( $champ['options'] ) && is_array( $champ['options'] ) ) {
			foreach ( $champ['options'] as $j => $option ) {
				if ( isset( $option['label'] ) ) {
					$groupe['fields'][ $i ]['options'][ $j ]['label'] = morgane_libelle_option( $option['label'] );
				}
			}
		}
	}

	// `get_post_metadata` attend la valeur emballée dans un tableau.
	return array( $groupe );
}

/*
 * Fonction nommée et non fermeture : le filtre doit se retirer lui-même le
 * temps de relire la méta, ce qu'une fermeture anonyme ne sait pas faire.
 */
add_filter( 'get_post_metadata', 'morgane_traduire_groupe_champs', 10, 4 );

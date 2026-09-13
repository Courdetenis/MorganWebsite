## Task 2 : Les champs anglais sur la fiche produit

**Files:**
- Create: `theme/inc/produits.php`
- Modify: `theme/functions.php` (ajouter le `require_once`, après `inc/i18n.php`)
- Test: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: `morgane_t()`, `morgane_lang()` de `inc/i18n.php`.
- Produces:
  - constantes `MORGANE_META_NOM_EN = '_morgane_nom_en'`, `MORGANE_META_RESUME_EN = '_morgane_resume_en'`, `MORGANE_META_DESC_EN = '_morgane_description_en'` ;
  - `morgane_texte_en( int $id, string $cle ) : string` — lit une des trois métas, `''` si absente. Les tâches 3 et 6 s'en servent.

- [ ] **Step 1 : Écrire l'assertion, qui doit échouer**

Ajouter dans `scripts/verif-produits.php`, avant `mg_verif_bilan();` :

```php
// --- 2. Les trois métas anglaises se lisent et s'écrivent ------------------
$echantillon = $produits ? (int) $produits[0] : 0;

mg_verif(
	'morgane_texte_en() existe',
	function_exists( 'morgane_texte_en' )
);

if ( function_exists( 'morgane_texte_en' ) && $echantillon ) {
	update_post_meta( $echantillon, MORGANE_META_NOM_EN, 'Northern Lights — print' );
	mg_verif(
		'Le nom anglais se relit',
		'Northern Lights — print' === morgane_texte_en( $echantillon, MORGANE_META_NOM_EN )
	);
	mg_verif(
		'Une méta absente rend une chaîne vide',
		'' === morgane_texte_en( $echantillon, MORGANE_META_RESUME_EN )
	);
	delete_post_meta( $echantillon, MORGANE_META_NOM_EN );
}
```

- [ ] **Step 2 : Le jouer et constater l'échec**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : `ÉCHEC morgane_texte_en() existe`.

- [ ] **Step 3 : Écrire `theme/inc/produits.php`**

```php
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
```

- [ ] **Step 4 : Charger le fichier**

Dans `theme/functions.php`, après la ligne `require_once get_theme_file_path( 'inc/i18n.php' );` :

```php
require_once get_theme_file_path( 'inc/produits.php' );
```

- [ ] **Step 5 : Rejouer le banc**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : les trois assertions de la tâche 2 passent. Celle de la tâche 1 échoue toujours.

- [ ] **Step 6 : Commit**

```bash
git add theme/inc/produits.php theme/functions.php scripts/verif-produits.php
git commit -m "feat(produits): champs anglais sur la fiche produit"
```

---


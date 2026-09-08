# Fiche produit unique bilingue — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Une œuvre = une fiche produit = un stock, avec le français et l'anglais saisis sur le même écran et basculés à l'affichage.

**Architecture:** Les produits et leurs taxonomies sortent de Polylang : il n'existe plus qu'un article par œuvre, donc un seul compteur de stock, par construction et non par synchronisation. Les textes anglais vivent dans des métadonnées de cette même fiche (`_morgane_nom_en`, `_morgane_resume_en`, `_morgane_description_en`), échangées au rendu par des filtres quand la langue active est l'anglais. C'est le principe déjà appliqué aux pages Panier, Commande et Mon compte : une page unique servie dans les deux langues.

**Tech Stack:** WordPress 6.5+, WooCommerce 11, Polylang 3.8 (gratuit), PHP 8.3, Advanced Product Fields for WooCommerce 1.7.

**Spec:** ce document (la décision et ses alternatives sont dans l'échange du 2026-09-08 : route 1 extension payante, route 2 fiche unique — retenue, route 3 synchronisation maison — écartée).

## Global Constraints

- **Langue par défaut du site : `fr`** (Loi 96). L'anglais est la variante ; le français est ce qui est saisi dans l'éditeur.
- **Un seul jeu de catégories** (décision du 2026-09-08). `product_cat` et `product_tag` sortent de Polylang ; les noms sont traduits à l'affichage. Conséquence acceptée : l'archive anglaise porte le slug français.
- **Aucune commande n'existe sur les fiches anglaises** (confirmé le 2026-09-08) : la migration peut les supprimer sans précaution d'historique.
- **Jamais de traduction dans l'administration ni en REST.** Morgane doit lire et modifier ce qu'elle a saisi. Tous les filtres de rendu portent la garde `is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )`, comme le filtre `the_title` existant de `inc/boutique.php`.
- **Libellés d'interface** : tableaux `array( 'fr' => …, 'en' => … )` lus par `morgane_t()`. Pas de fichiers `.po`.
- **Le thème doit rester fonctionnel sans Polylang** : toute fonction `pll_*` est appelée derrière `function_exists()`.
- **Commandes de vérification** : le site local tourne dans Docker. `wp` s'invoque par
  `docker compose run --rm --entrypoint wp wpcli <commande>`.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `theme/inc/produits.php` *(créé)* | Tout le bilingue produit : métaboîte, bascule au rendu, libellés d'options. Un seul sujet, un seul fichier. |
| `theme/inc/i18n.php` *(modifié)* | `morgane_categorie_terme()` se simplifie : plus de traduction de terme à chercher. |
| `theme/inc/boutique.php` *(modifié)* | Noms de catégories traduits au rendu (titre d'archive, fil d'Ariane). |
| `theme/functions.php` *(modifié)* | Charge `inc/produits.php`. |
| `theme/install/pll-cpt.php` *(modifié)* | Cesse de déclarer `product`, `product_cat`, `product_tag` traduisibles — et les retire s'ils y sont. |
| `theme/install/pll-content.php` *(modifié)* | Cesse de créer les doublons anglais. |
| `theme/install/migrer-produits.php` *(créé)* | Migration jouable une fois : replie les fiches anglaises dans les françaises, puis les supprime. |
| `theme/inc/installer.php` *(modifié)* | Un bouton pour lancer la migration depuis `wp-admin`. |
| `scripts/verif-produits.php` *(créé)* | Banc d'essai : assertions jouées par `wp eval-file`. Le dépôt n'a pas de PHPUnit ; ce fichier en tient lieu. |

---

## Task 1 : Banc d'essai et état des lieux

**Files:**
- Create: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: rien.
- Produces: `mg_verif( string $nom, bool $ok, string $detail = '' )` — enregistre un résultat ; `mg_verif_bilan()` — imprime le bilan et rend le nombre d'échecs. Toutes les tâches suivantes ajoutent leurs assertions dans ce fichier.

- [ ] **Step 1 : Écrire le banc et sa première assertion, qui doit échouer**

```php
<?php
/**
 * Banc d'essai de la fiche produit unique bilingue.
 * Le dépôt n'a pas de PHPUnit : ce fichier joue les assertions dans un
 * WordPress chargé, ce qui est ce qu'on veut vérifier de toute façon.
 *
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
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

// --- 1. Un seul article par œuvre -----------------------------------------
$produits = get_posts( array(
	'post_type'   => 'product',
	'numberposts' => -1,
	'post_status' => 'any',
	'fields'      => 'ids',
	'lang'        => '',
) );

$en = array_filter( $produits, function ( $id ) {
	return function_exists( 'pll_get_post_language' ) && 'en' === pll_get_post_language( $id );
} );

mg_verif(
	'Aucun produit rattaché à l\'anglais',
	0 === count( $en ),
	count( $en ) . ' trouvé(s) : ' . implode( ',', $en )
);

mg_verif_bilan();
```

- [ ] **Step 2 : Le jouer et constater l'échec**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : `ÉCHEC Aucun produit rattaché à l'anglais — 5 trouvé(s) : 95,16,17,18,19`.
C'est l'état de départ : cinq fiches anglaises en double.

- [ ] **Step 3 : Relever l'inventaire avant migration**

```bash
docker compose run --rm --entrypoint wp wpcli eval '
foreach ( get_posts( array( "post_type" => "product", "numberposts" => -1, "fields" => "ids", "lang" => "" ) ) as $id ) {
  $p = wc_get_product( $id );
  printf( "%d\t%s\tstock=%s\t%s\n", $id, pll_get_post_language( $id ), var_export( $p->get_stock_quantity(), true ), $p->get_name() );
}'
```

Coller le résultat dans le message de commit : c'est l'état auquel comparer après migration.

- [ ] **Step 4 : Commit**

```bash
git add scripts/verif-produits.php
git commit -m "test(produits): banc d'essai de la fiche unique bilingue"
```

---

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

## Task 3 : La bascule au rendu

**Files:**
- Modify: `theme/inc/produits.php`
- Test: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: `morgane_texte_en()`, les trois constantes de la tâche 2.
- Produces: `morgane_produit_en_actif() : bool` — vrai quand la langue active est l'anglais et qu'on est en rendu public. Réutilisée par la tâche 5.

- [ ] **Step 1 : Écrire les assertions, qui doivent échouer**

Ajouter dans `scripts/verif-produits.php`, avant `mg_verif_bilan();` :

```php
// --- 3. Le nom bascule selon la langue ------------------------------------
mg_verif( 'morgane_produit_en_actif() existe', function_exists( 'morgane_produit_en_actif' ) );

if ( $echantillon && function_exists( 'morgane_produit_en_actif' ) ) {
	$fr_nom = get_post_field( 'post_title', $echantillon );
	update_post_meta( $echantillon, MORGANE_META_NOM_EN, 'Test EN name' );

	// En ligne de commande la langue vaut 'fr' : le nom français doit sortir.
	$produit = wc_get_product( $echantillon );
	mg_verif(
		'En français, le nom reste le nom saisi',
		$fr_nom === $produit->get_name(),
		$produit->get_name()
	);

	// On force l'anglais par le filtre prévu pour ça.
	$forcer_en = function () { return 'en'; };
	add_filter( 'morgane_langue_forcee', $forcer_en );
	wp_cache_flush();
	$produit = wc_get_product( $echantillon );
	mg_verif(
		'En anglais, le nom anglais prend la place',
		'Test EN name' === $produit->get_name(),
		$produit->get_name()
	);
	remove_filter( 'morgane_langue_forcee', $forcer_en );

	delete_post_meta( $echantillon, MORGANE_META_NOM_EN );
}
```

- [ ] **Step 2 : Le jouer et constater l'échec**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : `ÉCHEC morgane_produit_en_actif() existe`.

- [ ] **Step 3 : Ouvrir `morgane_lang()` à un forçage, pour pouvoir la tester**

Dans `theme/inc/i18n.php`, dans `morgane_lang()`, en toute première instruction du corps :

```php
	/*
	 * Point d'entrée du banc d'essai : en ligne de commande il n'y a pas de
	 * langue de page, et rien ne permettrait sinon de vérifier le rendu
	 * anglais. Aucun appelant du thème n'accroche ce filtre.
	 */
	$forcee = apply_filters( 'morgane_langue_forcee', '' );
	if ( $forcee ) {
		return (string) $forcee;
	}
```

- [ ] **Step 4 : Écrire la bascule**

Ajouter à la fin de `theme/inc/produits.php` :

```php
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
```

- [ ] **Step 5 : Rejouer le banc**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : les assertions des tâches 2 et 3 passent.

- [ ] **Step 6 : Commit**

```bash
git add theme/inc/produits.php theme/inc/i18n.php scripts/verif-produits.php
git commit -m "feat(produits): servir le texte anglais au rendu"
```

---

## Task 4 : Sortir les produits et les catégories de Polylang

**Files:**
- Modify: `theme/install/pll-cpt.php`
- Modify: `theme/inc/i18n.php` (`morgane_categorie_terme`)
- Modify: `theme/inc/boutique.php` (noms de catégories au rendu)
- Test: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: `morgane_rayons_boutique()` de `inc/boutique.php`, qui porte déjà les libellés FR/EN des deux rayons.
- Produces: `morgane_nom_categorie( WP_Term $terme ) : string` — le nom du rayon dans la langue active, ou le nom brut du terme s'il n'est pas un rayon connu.

- [ ] **Step 1 : Écrire les assertions, qui doivent échouer**

Ajouter dans `scripts/verif-produits.php`, avant `mg_verif_bilan();` :

```php
// --- 4. Produits et taxonomies hors de Polylang ----------------------------
$opts = get_option( 'polylang' );
$pt   = isset( $opts['post_types'] ) ? (array) $opts['post_types'] : array();
$tx   = isset( $opts['taxonomies'] ) ? (array) $opts['taxonomies'] : array();

mg_verif( 'product n\'est plus traduisible', ! in_array( 'product', $pt, true ) );
mg_verif( 'product_cat n\'est plus traduisible', ! in_array( 'product_cat', $tx, true ) );
mg_verif( 'product_tag n\'est plus traduisible', ! in_array( 'product_tag', $tx, true ) );
```

- [ ] **Step 2 : Le jouer et constater l'échec**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : les trois échouent.

- [ ] **Step 3 : Réécrire `theme/install/pll-cpt.php`**

Remplacer tout le corps après le bloc de commentaires par :

```php
$opts = get_option( 'polylang' );
if ( ! is_array( $opts ) ) {
	WP_CLI::error( 'Options Polylang inattendues (' . gettype( $opts ) . ').' );
}

/*
 * Les produits sortent de Polylang : une œuvre = un article = un stock.
 * Y laisser `product` recréerait deux fiches, donc deux compteurs — et un
 * original vendu deux fois. Les catégories suivent : un produit rattaché au
 * terme français n'apparaîtrait pas dans l'archive anglaise.
 *
 * Idempotent : on retire ce qui s'y trouve, on ne suppose pas l'état de départ.
 */
$retirer_pt = array( 'product' );
$retirer_tx = array( 'product_cat', 'product_tag' );

$opts['post_types'] = array_values( array_diff(
	isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : array(),
	$retirer_pt
) );
$opts['taxonomies'] = array_values( array_diff(
	isset( $opts['taxonomies'] ) && is_array( $opts['taxonomies'] ) ? $opts['taxonomies'] : array(),
	$retirer_tx
) );

update_option( 'polylang', $opts );

WP_CLI::log( 'post_types : ' . ( implode( ', ', $opts['post_types'] ) ?: '(aucun)' ) );
WP_CLI::log( 'taxonomies : ' . ( implode( ', ', $opts['taxonomies'] ) ?: '(aucune)' ) );
WP_CLI::success( 'Produits et taxonomies produit retirés de Polylang.' );
```

Mettre à jour le bloc de commentaires en tête pour qu'il dise ce que le fichier fait maintenant.

- [ ] **Step 4 : Simplifier `morgane_categorie_terme()`**

Dans `theme/inc/i18n.php`, retirer le bloc `if ( function_exists( 'pll_get_term' ) ) { … }` : il n'y a plus de traduction de terme à chercher. Garder `'lang' => ''` dans l'appel à `get_terms()` — inoffensif, et il protège si Polylang venait à refiltrer la taxonomie. Remplacer le commentaire par :

```php
	/*
	 * `lang => ''` reste par prudence : les catégories ne sont plus traduites
	 * (voir install/pll-cpt.php), mais réactiver la case dans Polylang
	 * remettrait le filtre de langue et ferait disparaître les deux rayons.
	 */
```

- [ ] **Step 5 : Traduire le nom des catégories au rendu**

Ajouter à la fin de `theme/inc/boutique.php` :

```php
/**
 * Le nom d'un rayon dans la langue active.
 *
 * Les catégories ne sont plus traduites par Polylang : il n'existe qu'un terme
 * « Originaux », dont le nom doit se lire « Originals » sur le site anglais.
 * Les libellés viennent de `morgane_rayons_boutique()`, déjà seule source de
 * vérité pour le menu et la vitrine.
 */
function morgane_nom_categorie( $terme ) {
	if ( ! ( $terme instanceof WP_Term ) ) {
		return '';
	}

	foreach ( morgane_rayons_boutique() as $rayon ) {
		if ( $rayon['slug'] === $terme->slug ) {
			return morgane_t( $rayon['label'] );
		}
	}

	return $terme->name;
}

/*
 * `single_term_title` couvre le titre de l'archive, `woocommerce_get_breadcrumb`
 * le fil d'Ariane. On ne touche pas à `get_term` lui-même : il s'exécute dans
 * l'administration comme ailleurs, et Morgane doit y lire le nom qu'elle a saisi.
 */
add_filter( 'single_cat_title', function ( $titre ) {
	if ( is_admin() || ! is_tax( 'product_cat' ) ) {
		return $titre;
	}

	$terme = get_queried_object();

	return ( $terme instanceof WP_Term ) ? morgane_nom_categorie( $terme ) : $titre;
} );
```

- [ ] **Step 6 : Rejouer le banc et l'installateur**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /var/www/html/wp-content/themes/morgane/install/pll-cpt.php
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : les trois assertions de la tâche 4 passent.

- [ ] **Step 7 : Commit**

```bash
git add theme/install/pll-cpt.php theme/inc/i18n.php theme/inc/boutique.php scripts/verif-produits.php
git commit -m "feat(produits): sortir produits et catégories de Polylang"
```

---

## Task 5 : Les libellés d'options de taille

**Files:**
- Modify: `theme/inc/produits.php`
- Test: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: `morgane_produit_en_actif()` de la tâche 3.
- Produces: `morgane_libelles_options() : array<string,array<string,string>>` — table des libellés, indexée par le texte français.

**Contexte technique :** l'extension range ses groupes de champs dans la méta `_wapf_fieldgroup` (constaté le 2026-09-08) et **n'offre aucun filtre sur le libellé d'un champ** — `wapf/field_options` ne couvre que les options. On intercepte donc la lecture de la méta elle-même, ce qui attrape d'un coup le libellé du champ, sa description et ses options.

- [ ] **Step 1 : Écrire l'assertion, qui doit échouer**

Ajouter dans `scripts/verif-produits.php`, avant `mg_verif_bilan();` :

```php
// --- 5. Libellés d'options traduits ---------------------------------------
mg_verif( 'morgane_libelles_options() existe', function_exists( 'morgane_libelles_options' ) );

if ( function_exists( 'morgane_libelles_options' ) ) {
	$table = morgane_libelles_options();
	mg_verif(
		'« Taille » a son équivalent anglais',
		isset( $table['Taille']['en'] ) && 'Size' === $table['Taille']['en']
	);
}
```

- [ ] **Step 2 : Le jouer et constater l'échec**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : `ÉCHEC morgane_libelles_options() existe`.

- [ ] **Step 3 : Écrire la traduction des libellés**

Ajouter à la fin de `theme/inc/produits.php` :

```php
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
		'Taille'  => array( 'fr' => 'Taille',  'en' => 'Size' ),
		'Format'  => array( 'fr' => 'Format',  'en' => 'Format' ),
		'Finition' => array( 'fr' => 'Finition', 'en' => 'Finish' ),
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
```

- [ ] **Step 4 : Rejouer le banc**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : les deux assertions de la tâche 5 passent.

- [ ] **Step 5 : Vérifier dans le navigateur qu'un groupe de champs s'affiche encore**

Créer un groupe de champs d'essai depuis `Produits > Product Field Groups` avec un champ « Taille » et deux options, l'assigner au produit modèle, puis charger la fiche en français et sous `/en/`. Le libellé doit dire « Taille » puis « Size », et le récapitulatif de prix doit continuer de se mettre à jour au changement d'option.

- [ ] **Step 6 : Commit**

```bash
git add theme/inc/produits.php scripts/verif-produits.php
git commit -m "feat(produits): traduire les libellés d'options"
```

---

## Task 6 : La migration

**Files:**
- Create: `theme/install/migrer-produits.php`
- Modify: `theme/install/pll-content.php`
- Modify: `theme/inc/installer.php`
- Test: `scripts/verif-produits.php`

**Interfaces:**
- Consumes: les trois constantes de la tâche 2.
- Produces: rien — script terminal, jouable une fois puis sans effet.

- [ ] **Step 1 : Empêcher l'installateur de recréer des doublons**

Dans `theme/install/pll-content.php`, remplacer la boucle qui crée les traductions de produits par une sortie nette :

```php
/*
 * Les produits ne sont plus traduits : une œuvre = un article = un stock.
 * Voir theme/inc/produits.php. Ce script ne s'occupe donc plus que des
 * contenus qui restent traduisibles.
 */
```

Supprimer le bloc `foreach ( $products as $pid ) { … }` ainsi que la requête `$products` qui l'alimente, et les appels `pll_set_post_language` / `pll_save_post_translations` qui portaient sur des produits. Ne rien toucher d'autre dans ce fichier.

- [ ] **Step 2 : Écrire la migration**

Créer `theme/install/migrer-produits.php` :

```php
<?php
/**
 * Replie les fiches produit anglaises dans leur jumelle française, puis les
 * supprime.
 *
 * À jouer une fois, après avoir chargé le thème qui sort les produits de
 * Polylang. Rejouable sans dommage : une fois les fiches anglaises parties,
 * il n'y a plus rien à replier.
 *
 * Aucune commande n'existe sur ces fiches (vérifié le 2026-09-08) : la
 * suppression est sans conséquence sur l'historique. Le script s'arrête
 * néanmoins si une commande en référence une, plutôt que d'effacer en silence.
 */

if ( ! function_exists( 'pll_get_post_language' ) ) {
	WP_CLI::error( 'Polylang introuvable : impossible de reconnaître les fiches anglaises.' );
}

$tous = get_posts( array(
	'post_type'   => 'product',
	'numberposts' => -1,
	'post_status' => 'any',
	'fields'      => 'ids',
	'lang'        => '',
) );

$replies    = 0;
$supprimes  = 0;
$conserves  = 0;

foreach ( $tous as $id ) {
	if ( 'en' !== pll_get_post_language( $id ) ) {
		continue;
	}

	$fr = function_exists( 'pll_get_post' ) ? (int) pll_get_post( $id, 'fr' ) : 0;

	if ( ! $fr || ! get_post( $fr ) ) {
		WP_CLI::warning( sprintf( 'Fiche EN #%d sans jumelle française : conservée, à traiter à la main.', $id ) );
		$conserves++;
		continue;
	}

	$en_post = get_post( $id );

	// On n'écrase jamais un texte anglais déjà saisi à la main.
	$paires = array(
		MORGANE_META_NOM_EN    => $en_post->post_title,
		MORGANE_META_RESUME_EN => $en_post->post_excerpt,
		MORGANE_META_DESC_EN   => $en_post->post_content,
	);

	foreach ( $paires as $cle => $texte ) {
		$texte = trim( (string) $texte );
		if ( '' !== $texte && '' === trim( (string) get_post_meta( $fr, $cle, true ) ) ) {
			update_post_meta( $fr, $cle, $texte );
		}
	}

	$replies++;

	wp_delete_post( $id, true );
	$supprimes++;
	WP_CLI::log( sprintf( 'Fiche EN #%d repliée dans #%d, puis supprimée.', $id, $fr ) );
}

WP_CLI::success( sprintf(
	'%d fiche(s) repliée(s), %d supprimée(s), %d conservée(s) pour examen.',
	$replies,
	$supprimes,
	$conserves
) );
```

- [ ] **Step 3 : Ajouter le bouton dans l'installateur**

Dans `theme/inc/installer.php`, à la liste `morgane_install_steps()`, **ne pas** ajouter cette étape : elle ne doit jamais partir avec le bouton d'installation. Ajouter à la place, dans la page d'administration, juste avant le bloc `<h2>Traductions</h2>` :

```php
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
```

Puis dans le gestionnaire `admin_post_morgane_install_step`, juste après la résolution de `$etape`, autoriser une étape hors liste :

```php
	// La migration ne figure pas dans `morgane_install_steps()` : elle ne doit
	// jamais partir avec le bouton d'installation, qui est rejouable et sans
	// effet destructeur.
	if ( ! $etape && 'migrer' === $demande ) {
		$etape = array( 'id' => 'migrer', 'file' => 'migrer-produits.php', 'label' => 'Replier les fiches anglaises' );
	}
```

Et dans le JavaScript, à côté de `boutonPages` :

```js
		var boutonMigrer = document.getElementById('morgane-migrer');
		if (boutonMigrer) {
			boutonMigrer.addEventListener('click', function () {
				if (!window.confirm('Replier les fiches anglaises dans les françaises, puis les supprimer ?\n\nÀ ne faire qu\'une fois.')) {
					return;
				}
				lancer([{ id: 'migrer', label: 'Replier les fiches anglaises' }], boutonMigrer);
			});
		}
```

- [ ] **Step 4 : Jouer la migration en local**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /var/www/html/wp-content/themes/morgane/install/migrer-produits.php
```

Attendu : cinq fiches repliées puis supprimées.

- [ ] **Step 5 : Rejouer le banc en entier**

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php
```

Attendu : **toutes** les assertions passent, y compris celle de la tâche 1 — plus aucun produit rattaché à l'anglais.

- [ ] **Step 6 : Vérifier le stock partagé, ce qui est tout l'objet du chantier**

```bash
docker compose run --rm --entrypoint wp wpcli eval '
$id = (int) get_posts( array( "post_type" => "product", "numberposts" => 1, "fields" => "ids", "lang" => "" ) )[0];
wc_update_product_stock( $id, 1, "set" );
printf( "stock unique = %s pour « %s »\n", wc_get_product( $id )->get_stock_quantity(), get_the_title( $id ) );
printf( "articles product en base : %d\n", count( get_posts( array( "post_type" => "product", "numberposts" => -1, "fields" => "ids", "lang" => "" ) ) ) );'
```

Attendu : un seul compteur, et un nombre d'articles divisé par deux par rapport à l'inventaire de la tâche 1.

- [ ] **Step 7 : Vérifier les deux langues dans le navigateur**

Charger `/boutique/?tout=1` et `/en/boutique/?tout=1` : la même œuvre doit apparaître des deux côtés, avec son nom français puis anglais. Ouvrir sa fiche dans les deux langues : titre, résumé et description doivent suivre, et l'adresse être la même à la racine de langue près.

- [ ] **Step 8 : Commit**

```bash
git add theme/install/migrer-produits.php theme/install/pll-content.php theme/inc/installer.php scripts/verif-produits.php
git commit -m "feat(produits): migration des fiches anglaises vers la fiche unique"
```

---

## Après le plan

- Monter `theme/style.css` en **0.11.0** et construire `dist/morgane-theme-0.11.0.zip`.
- **Ordre de mise en ligne, qui compte** : téléverser le thème *avant* de lancer la migration. Le thème sorti les produits de Polylang ; la migration suppose ce contexte.
- Mettre à jour `docs/architecture.md` et `README.md` : la section bilingue affirme encore qu'une œuvre existe en deux fiches.
- Prévenir Morgane : la saisie change. Un seul écran par œuvre, avec un encadré « Version anglaise » sous la description.

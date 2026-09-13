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


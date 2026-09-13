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


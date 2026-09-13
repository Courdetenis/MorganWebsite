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


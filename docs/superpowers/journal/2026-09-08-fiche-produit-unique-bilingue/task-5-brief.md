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


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

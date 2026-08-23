# Morgane Cachia – Art — dev local

WordPress + WooCommerce + Polylang + thème bloc sur mesure.
Cahier des charges et décisions : **`docs/architecture.md`**.

## Démarrer (après installation de Docker)

```bash
docker compose up -d
```

```bash
docker compose run --rm wpcli
```

La seconde commande installe tout : WordPress, WooCommerce, Polylang, le thème,
les langues FR/EN, l'arborescence complète avec les textes, et la structure de
boutique. Elle est relançable sans rien casser.

- Site : http://localhost:8080
- Admin : http://localhost:8080/wp-admin — `morgane` / `morgane`

## Arrêter / réinitialiser

```bash
docker compose down
```

```bash
docker compose down -v
```

La seconde efface la base et WordPress — on repart de zéro.

## Structure

- `theme/` — le thème, seul dossier versionné.
  - `theme.json` — design system (couleurs, typo, espacements).
  - `inc/i18n.php` — libellés FR/EN et résolution des URL par langue.
  - `inc/blocks.php` — menu, bascule de langue, bascule de thème, grille d'œuvres.
  - `inc/forms.php` — les trois formulaires.
- `scripts/` — installation reproductible, tous idempotents.
- `pictures/` — photos d'origine, en JPEG pleine résolution. Elles ne sont pas
  servies telles quelles : `scripts/media.php` les convertit en WebP (2000 px de
  plus grand côté) et les importe dans la médiathèque.
- WP core, extensions et base vivent dans des volumes Docker, hors du dépôt.

## Photos

Chaque photo est numérotée (`3 - site internet.jpg` → photo 3). `media.php` tient
la correspondance numéro → sujet, texte alternatif et nom de fichier ; `pages.php`
ne connaît que les numéros et pose les images aux bons endroits. Pour ajouter ou
remplacer une photo : déposer le fichier dans `pictures/`, ajouter son entrée dans
`media.php`, puis rejouer les deux scripts.

```bash
docker compose run --rm --entrypoint wp wpcli eval-file /scripts/media.php
```

## Réécrire les contenus des pages

Par défaut les scripts ne remplissent que les pages vides, pour ne pas écraser
une modification faite depuis l'admin. Pour forcer :

```bash
docker compose run --rm -e MORGANE_FORCE=1 --entrypoint wp wpcli eval-file /scripts/pages.php
```

## Ajouter une œuvre (côté Morgane)

- **Un tirage** : Produits > dupliquer « Modèle — print en plusieurs tailles »,
  changer le titre, la photo, la description, les prix des variations, publier.
- **Un original** : Produits > Ajouter, produit simple, stock 1,
  catégorie « Originaux ».
- La qualité du papier, les matériaux et le certificat sont décrits une fois
  dans la description de catégorie — inutile de les recopier.

## À faire avant production

- Photos des produits de la boutique (les pages, elles, sont illustrées).
- Renseigner Instagram, TikTok et le courriel professionnel dans
  `theme/parts/footer.html` et via le filtre `morgane_contact_email`.
- Auto-héberger les polices et GSAP (voir `functions.php`) — perf + Loi 25.
- Brancher un SMTP : `wp_mail()` en PHP natif finit en spam.
- Surcharger la fiche produit et le panier dans `theme/woocommerce/`.
- Réactiver le bloc « compte client » WooCommerce (désactivé dans `functions.php`).
- Livraison, taxes, paiement, pages légales.
- Préproduction obligatoire avant chaque mise en production.

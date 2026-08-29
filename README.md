# Morgane Cachia – Art — dev local

WordPress + WooCommerce + Polylang + thème bloc sur mesure.
Cahier des charges et décisions : **`docs/architecture.md`**.

## Repartir de zéro (nouvelle machine, après formatage)

Tout le site se reconstruit depuis ce dépôt. Il n'y a rien d'autre à sauvegarder :
WordPress, les extensions et la base de données vivent dans des volumes Docker et
sont recréés par le script d'installation.

```bash
git clone https://github.com/Courdetenis/MorganWebsite.git
```

Installer Docker Desktop, puis, depuis le dossier cloné, les deux commandes
ci-dessous. Compter quelques minutes : le script télécharge WordPress, WooCommerce
et Polylang, puis convertit les 23 photos en WebP et les importe.

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

## Logo

Les trois versions d'origine (noire, bordeaux, crème) sont dans `pictures/logo/`.
Le thème n'en embarque qu'une : `theme/assets/img/logo.webp`, posée en masque CSS
et repeinte selon le thème — bordeaux en clair, crème en sombre. Une seule image
pour les deux, et le texte « Morgane Cachia – Art » reste dans le balisage pour
les lecteurs d'écran et les moteurs de recherche.

`theme/assets/img/favicon.png` est le trait repeint dans l'accent `#944654`, recadré au carré :
l'onglet du navigateur ne suit pas le thème du site. `setup.sh` l'importe et la
règle comme icône du site.

Pour changer de logo, remplacer les fichiers dans `pictures/logo/` puis :

```bash
python3 - <<'EOF'
from PIL import Image
src = Image.open('pictures/logo/logo-noir.png')
src = src.crop(src.getchannel('A').getbbox())
src.resize((900, round(src.height * 900 / src.width)), Image.LANCZOS).save('theme/assets/img/logo.webp', quality=88, method=6)
tint = Image.new('RGBA', src.size, (148, 70, 84, 0))   # #944654, l'accent du nuancier
tint.putalpha(src.getchannel('A'))
side = max(tint.size)
sq = Image.new('RGBA', (side, side), (0, 0, 0, 0))
sq.paste(tint, ((side - tint.width) // 2, (side - tint.height) // 2))
sq.resize((512, 512), Image.LANCZOS).save('theme/assets/img/favicon.png', optimize=True)
EOF
```

Le rapport largeur/hauteur du fichier recadré est écrit en dur dans
`app.css` (`aspect-ratio`) : le corriger si le nouveau logo n'a pas la même forme.

## Réécrire les contenus des pages

Par défaut les scripts ne remplissent que les pages vides, pour ne pas écraser
une modification faite depuis l'admin. Pour forcer :

```bash
docker compose run --rm -e MORGANE_FORCE=1 --entrypoint wp wpcli eval-file /scripts/pages.php
```

Ou une seule page, par sa clé (`about`, `home`, `murals`, `portfolio`,
`commissions`…), pour ne pas toucher au reste :

```bash
docker compose run --rm -e MORGANE_FORCE=about --entrypoint wp wpcli eval-file /scripts/pages.php
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

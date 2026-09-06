# Morgane — thème Shopify

Portage du thème WordPress (`../theme`) vers Shopify, pour comparer le rendu
des deux plateformes à design égal.

Le fichier à déposer : **`dist/morgane-shopify-0.6.0.zip`**.

## Installation

1. Boutique de développement Shopify → **Boutique en ligne › Thèmes**
2. **Ajouter un thème › Importer un fichier zip** → choisir le zip
3. **Aperçu** (inutile de le publier)

Le thème s'affiche tel quel sur une boutique vide : la grille « Œuvres
disponibles » montre huit tuiles de démonstration tant qu'aucun produit
n'existe, et disparaissent d'elles-mêmes au premier produit ajouté.

Pour une comparaison honnête, trois choses à faire ensuite :

- **Menu** — *Navigation* → mettre les cinq entrées du site (Accueil, Boutique,
  Murales, Portfolio, À propos). Sans ça, le menu par défaut de Shopify
  s'affiche (Accueil, Catalogue).
- **Entrée secondaire** — *Personnaliser › En-tête* → renseigner le lien
  « Commissions et collaborations ». C'est l'entrée détachée à droite du menu.
- **Produits** — deux ou trois œuvres avec photo, prix et **poids**, pour voir
  la grille, la fiche et le calcul des frais de port.

## Ce qui a été porté

| Fichier WordPress | Devient |
|---|---|
| `theme.json` | `assets/base.css` (mêmes couleurs, tailles, espacements) |
| `assets/css/app.css` | `assets/app.css` — **copié sans une modification** |
| `assets/js/app.js` | `assets/app.js` — **copié sans une modification** |
| `parts/header.html` + `inc/blocks.php` | `sections/header.liquid` |
| `parts/footer.html` + `inc/contact.php` | `sections/footer.liquid` |
| `install/pages.php` (cas `home`) | `sections/hero,marquee,nav-cards,featured-artworks,atelier` |
| `woocommerce/content-product.php` | `snippets/artwork-card.liquid` |
| `inc/i18n.php` | `locales/fr.default.json`, `locales/en.json` |

Les classes du balisage sont restées celles de WordPress (`wp-block-group`,
`alignwide`, `woocommerce ul.products`…). Ce n'est pas de la négligence : c'est
ce qui permet de réutiliser `app.css` et `app.js` tels quels, donc de comparer
deux rendus et non deux réécritures.

## Ce qui n'a pas d'équivalent

- **`inc/installer.php` + `install/*`** (3 388 lignes) — Shopify n'exécute pas
  de code serveur. Le contenu se saisit à la main ou via l'Admin API, hors du
  thème.
- **Polylang** — les traductions de contenu sont des données dans la boutique
  (Shopify Markets), pas des fichiers. Seules les chaînes d'interface sont dans
  `locales/`. Le sélecteur de langue ne s'affiche que si la boutique publie
  plus d'une langue.
- **Formulaires maison** (`inc/forms.php`) — le formulaire de contact natif de
  Shopify est utilisé ; tout traitement particulier demanderait une app.

## Aperçu local, sans boutique

`preview.html` reproduit l'accueil en HTML statique, avec les mêmes CSS et JS
que le thème. Il sert à vérifier le rendu sans passer par Shopify.

```
python3 -m http.server 4173 --directory shopify-theme
```

puis http://localhost:4173/preview.html — il n'est pas inclus dans le zip.

## Limite connue

Shopify enveloppe chaque section dans un `<div class="shopify-section">`. Cette
enveloppe est neutralisée en `display: contents` (`assets/base.css`), sans quoi
l'en-tête collant n'a plus de course et les sections pleine largeur s'arrêtent
à 64 px des bords. Effet de bord : dans l'éditeur de thème, le survol d'une
section peut ne pas dessiner son cadre de sélection. Sans conséquence sur le
site publié.

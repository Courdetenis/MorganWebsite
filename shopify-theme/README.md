# Morgane — thème Shopify

Portage du thème WordPress (`../theme`) vers Shopify, design inchangé :
`assets/app.css` et `assets/app.js` sont des **copies exactes** de ceux du thème
WordPress.

Le fichier à importer : **`dist/morgane-shopify-<version>.zip`**, fabriqué par

```
powershell -ExecutionPolicy Bypass -File scripts/faire-zip-shopify.ps1
```

## Installer sur une boutique neuve

Ce que le thème fait seul, dès l'import : en-tête, menu, pied de page, accueil
complet, textes de toutes les pages en français et en anglais, photos,
formulaires, boutique, fiche d'œuvre, panier, 404.

Ce qu'un thème Shopify **ne peut pas** faire (Shopify ne le permet à aucun
thème) : créer des pages, des collections ou activer une langue. Quatre
étapes, une seule fois :

### 1. Importer et publier

*Boutique en ligne › Thèmes › Ajouter un thème › Importer un fichier zip*, puis
**Publier**.

### 2. Créer les pages (14)

*Boutique en ligne › Pages › Ajouter une page*. Pour chacune : le titre, puis
dans **Référencement › Modifier** mettre l'**identifiant (handle)** exact
ci-dessous. Le contenu reste **vide** : le thème l'affiche lui-même, dans les
deux langues. Gabarit : celui par défaut (`page`).

| Titre | Handle |
|---|---|
| Murales | `murales` |
| Portfolio | `portfolio` |
| Illustration jeunesse | `illustration-jeunesse` |
| Illustration | `illustration` |
| Dessin | `dessin` |
| Murales (portfolio) | `portfolio-murales` |
| À propos | `a-propos` |
| Commissions et collaborations | `commissions-et-collaborations` |
| Commissions | `commissions` |
| Collaborations | `collaborations` |
| Contact | `contact` |
| Conditions générales de vente | `conditions-generales-de-vente` |
| Retours et remboursements | `retours-et-remboursements` |
| Confidentialité | `confidentialite` |

L'onglet du navigateur prend le titre écrit dans le thème
(`locales/*.json › page_titles`), pas celui saisi ici.

### 3. Créer les collections

*Produits › Collections › Créer une collection*, handle exact :

- `prints` — titre « Prints »
- `originaux` — titre « Originaux ». Sa **description** s'affiche sous chaque
  original (matériaux, certificat) : l'écrire une fois ici.
- `accueil` (facultatif) — les œuvres à montrer sur l'accueil. Vide ou absente,
  l'accueil montre les dernières œuvres ajoutées.

Le menu « Boutique » ne déroule que les rayons qui existent, et la page
Boutique (`/collections`) les présente en cartes.

### 4. Langues et réglages

- *Paramètres › Langues* : **français par défaut**, ajouter **anglais** et le
  **publier**. Le sélecteur FR/EN apparaît alors dans l'en-tête.
- *Paramètres › Coordonnées de la boutique* : l'adresse courriel qui recevra
  les demandes de commissions, collaborations et murales.
- *Paramètres › Général › Devise* : format « {{amount_with_comma_separator}} $ CA »
  pour retrouver « 45,00 $ CA ».
- Produits en anglais : application gratuite **Translate & Adapt** de Shopify.
- Réseaux et courriel du pied de page : *Personnaliser › Pied de page*.

## Modifier un texte de page

Les textes vivent toujours dans `theme/install/pages.php`, un seul endroit pour
les deux plateformes. Après modification :

```
php -d extension=gd -d extension=exif scripts/shopify-pages.php
```

régénère `snippets/page-*.liquid` et `assets/art-*.webp`, puis refaire le zip.
Ne pas modifier les snippets `page-*` à la main : ils seraient écrasés.

## Ce qui a changé par rapport à WordPress

| WordPress | Shopify |
|---|---|
| `theme.json` | `assets/base.css` (variables et styles globaux) |
| moteur de mise en page des blocs | `assets/base.css` § 2, classes posées par `shopify-pages.php` |
| feuilles des blocs du cœur | `assets/wp-blocks.css` (copie, commentaires retirés) |
| `parts/header.html` + `inc/blocks.php` | `sections/header.liquid` |
| `parts/footer.html` + `inc/contact.php` | `sections/footer.liquid` |
| `install/pages.php` | `snippets/page-*.liquid` (générés) |
| bloc `morgane/artworks` | `snippets/artworks-grid.liquid` |
| `woocommerce/content-product.php` | `snippets/artwork-card.liquid` |
| `inc/forms.php` | `snippets/request-form.liquid`, envoi par le formulaire de contact de Shopify |
| vitrine de `inc/boutique.php` | `sections/main-list-collections.liquid` |
| Polylang | langues de Shopify, URL `/en/…` |

Le panier et le paiement sont ceux de Shopify : le paiement (checkout) ne se
personnalise pas par le thème.

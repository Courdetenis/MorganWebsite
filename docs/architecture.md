# Morgane Cachia – Art — cadrage et architecture

Document de référence du projet. Remplace la direction artistique et
l'arborescence du `brief-site-morgane.md` d'origine.

---

## 1. Analyse

### Compréhension du projet

Site vitrine + boutique pour une illustratrice, artiste peintre et muraliste
de Montréal. Trois intentions, dans cet ordre : montrer les œuvres, vendre
des tirages et des originaux, recevoir des demandes sur mesure (commissions,
collaborations, murales). Bilingue FR/EN, français par défaut.

### Ce qui est certain

- Stack **WordPress + WooCommerce**, local Docker aujourd'hui, serveur plus tard.
- Bilingue FR/EN avec bascule, français par défaut.
- Menu dépliant en haut à gauche, nom au centre, panier à droite.
- Clair = rose pâle / pastel. Sombre = vert sauge. Secondaires : violet
  pastel, vert pastel. Texte : vert foncé.
- Arborescence : Accueil, Boutique (Prints / Originaux), Murales, Portfolio
  (4 sous-portfolios), À propos, Commissions & collaborations (hub + 2 pages).
- Tous les textes de pages sont fournis et repris **mot pour mot**.
- Quatre formulaires de champs imposés (commissions, collaborations, murales).
- **Mise en scène assumée** : dégradé « aurore » animé derrière le hero, formes
  flottantes en parallaxe, bandeau de mots défilant, titre qui entre mot à mot,
  apparitions au défilement, cartes qui se soulèvent au survol. Le cahier des
  charges demandait au départ une interface sans effets ; arbitrage tranché en
  faveur de la version animée, avec la palette sable / bordeaux conservée
  telle quelle. Le mouvement est piloté par GSAP + ScrollTrigger et s'annule
  entièrement sous `prefers-reduced-motion`.

### Ce qui est ambigu

- « Barre de recherche seulement si elle est réellement utile » → tranché : **non**.
  Un catalogue de quelques dizaines d'œuvres se parcourt plus vite qu'il ne se
  cherche, et une recherche vide est un cul-de-sac. À rajouter le jour où le
  catalogue dépasse ~60 références.
- « Commissions et collaborations » absent du menu fourni mais devant rester
  accessible → tranché : entrée secondaire en bas du panneau de menu (le
  panneau est un volet, la place ne coûte rien), plus des appels à l'action
  contextuels sur l'accueil, les portfolios et la page Murales.
- « Plusieurs tailles » pour les prints → variations WooCommerce sur un
  attribut global `Taille`, pas des produits séparés.

### Ce qui manque

- Les **photos des produits** de la boutique. Celles des pages sont en place
  (voir `scripts/media.php`).
- Les **vraies adresses** : Instagram, TikTok, courriel professionnel.
  Placeholders dans `theme/parts/footer.html` et `inc/forms.php`.
- Frais et zones de **livraison**, taxes (TPS/TVQ), et le moyen de paiement.

### Hypothèses

- Un print = un produit variable (tailles), un original = un produit simple
  avec stock 1.
- Les portfolios sont éditoriaux (pages), pas des produits.
- L'entente de commande se règle hors ligne (courriel), le site sert à
  déclencher la conversation, pas à contractualiser.

---

## 2. Recommandation technique

### Architecture retenue

**Thème bloc (FSE) sur mesure, sans thème parent, sans page builder.**

- `theme.json` porte le design system : palette, typographie, espacements.
  Les deux thèmes (clair / sombre) réécrivent les mêmes variables CSS, donc
  tout le site bascule d'un coup.
- Les gabarits sont des fichiers HTML de blocs, versionnés dans Git.
- Les contenus vivent dans WordPress (pages traduites Polylang), **jamais dans
  les gabarits** : sinon la version anglaise afficherait les textes français.
- Quatre blocs dynamiques maison, rendus en PHP, sans étape de build :
  `morgane/menu`, `morgane/lang-switcher`, `morgane/theme-toggle`,
  `morgane/artworks`, `morgane/form`.
- Extensions : **WooCommerce** et **Polylang**. C'est tout.

### Pourquoi

Un page builder rendrait le contenu illisible hors de son éditeur et
verrouillerait le projet. Un thème parent ajouterait une couche de CSS à
défaire. Un empilement d'extensions (formulaires, portfolio, mégamenu)
ajouterait des mises à jour, des failles et du markup à surcharger, pour des
besoins que quelques dizaines de lignes de PHP couvrent exactement.

Pas d'étape de build (npm, webpack) : le thème est un dossier qu'on copie.
Le déploiement futur en est d'autant plus simple.

### Variante secondaire

Thème bloc identique + **une** extension de formulaires (Contact Form 7)
à la place de `inc/forms.php`.

**Pourquoi on ne l'a pas prise** : trois formulaires aux champs figés ne
justifient pas un constructeur de formulaires, son markup à re-styler, et le
doublon de chaque formulaire par langue à maintenir dans Polylang. À
reconsidérer si Morgane veut modifier les champs elle-même, ou si le volume
de spam impose un service anti-spam.

---

## 3. Architecture du site

### Arborescence

```
/                                     /en/
/shop/                                /en/shop/
  ├─ Prints        (catégorie produit)
  └─ Originaux     (catégorie produit)
/murales/                             /en/murals/
/portfolio/                           /en/works/
  ├─ /illustration-jeunesse/            ├─ /childrens-illustration/
  ├─ /illustration/                     ├─ /illustration/
  ├─ /dessin/                           ├─ /drawing/
  └─ /murales/                          └─ /murals/
/a-propos/                            /en/about/
/commissions-et-collaborations/       /en/commissions-and-collaborations/
  ├─ /commissions/                      ├─ /commissions/
  └─ /collaborations/                   └─ /collaborations/
```

Deux pages de langues différentes ne peuvent pas partager un slug au même
niveau : WordPress résout une page par son chemin avant que Polylang n'ait son
mot à dire, et c'est la première trouvée qui gagne. D'où deux choix :

- **Commissions et Collaborations sont rangées sous leur page d'entrée** —
  parents différents, donc slugs libres, et l'arborescence y gagne : le hub
  devient un vrai palier.
- **Le portfolio anglais s'appelle `works`**, usage courant sur les sites
  d'artistes. Le titre affiché reste « Portfolio ».

### Navigation

L'en-tête tient sur deux rangées : le **nom du site** centré (cliquable vers
l'accueil) avec **bascule FR/EN, bascule clair/sombre et panier** à droite ;
en dessous, la **rangée de liens** : Accueil, Boutique, Murales, Portfolio,
À propos, puis — séparé d'un filet et visiblement secondaire — « Commissions
et collaborations ».

Sur une seule rangée, les six liens (≈690 px) prendraient au nom la place dont
il a besoin pour rester centré ; d'où les deux rangées dès la tablette.

**En dessous de 720 px seulement**, les liens repassent derrière un bouton
hamburger. Au-dessus, ils restent affichés : personne ne devrait avoir à
ouvrir un menu quand l'écran a la place de le montrer.

Un seul balisage sert les deux affichages — c'est le CSS qui décide, pas une
duplication de liens dans le DOM. Le bouton porte `aria-expanded` /
`aria-controls`, Échap et le clic à l'extérieur referment. Sans JavaScript, le
panneau reste déplié (`html:not(.js)`) plutôt que de devenir inatteignable.

### Répartition des contenus dans WordPress

| Quoi | Où |
|---|---|
| Textes des pages | Pages WordPress, traduites (Polylang) |
| Œuvres en vente | Produits WooCommerce |
| Prints / Originaux | Catégories produits (`product_cat`) |
| Tailles de tirage | Attribut global `Taille` + variations |
| Images de portfolio | Bloc Galerie, style « Défilement horizontal » |
| Formulaires | Bloc `morgane/form`, déjà posé dans les pages |
| En-tête / pied de page | Parties de modèle (Apparence > Éditeur) |

Pas de type de contenu maison, pas de champ personnalisé : rien dans ce site
n'en demandait. Un portfolio est une page avec une galerie ; une œuvre est un
produit. Ajouter un CPT « portfolio » aurait obligé Morgane à apprendre un
deuxième endroit où écrire.

### Gestion WooCommerce

- **Un print** = produit *variable*, attribut `Taille` en variations
  (5×7, 8×10, 11×14, 12×18 po). Un produit modèle en brouillon
  (« Modèle — print en plusieurs tailles ») sert de gabarit : Morgane le
  duplique, change titre, photo, description et prix.
- **Un original** = produit *simple*, stock 1. Il s'affiche « Vendu » tout seul
  quand le stock tombe à zéro.
- La qualité du papier, les matériaux et le certificat d'authenticité sont
  écrits **une fois** dans la description de catégorie — pas recopiés dans
  chaque fiche.

### Gestion du toggle FR / EN

- Polylang, `fr_CA` par défaut, `en_US`, URLs en répertoires (`/`, `/en/`).
- Trois réglages Polylang non évidents, posés par `scripts/pll-setup.php` :
  `force_lang = 1` (langue dans l'URL), `hide_default = 1` (le français reste
  à la racine) et **`redirect_lang = true`** — sans ce dernier, l'accueil
  anglais s'annonce partout comme `/en/home/` au lieu de `/en/`, soit deux URL
  pour la même page. Les langues mettent leur URL d'accueil en cache : après
  tout changement, jeter le transient `pll_languages_list`.
- En WP-CLI, Polylang ne charge que son modèle de données, pas ses filtres de
  requête : l'argument `lang` d'une `WP_Query` y est ignoré, et
  `wp_insert_post()` suffixe les slugs en `-2`. Les scripts contournent les
  deux explicitement — voir les commentaires dans `scripts/pages.php`.
- Chaque page a sa jumelle anglaise, liées comme traductions. La bascule mène
  à la traduction de la page courante, ou à l'accueil de la langue si elle
  n'existe pas encore.
- Les libellés d'interface du thème (menu, formulaires, messages) sont des
  tableaux `array( 'fr' => …, 'en' => … )` dans `inc/i18n.php` — pas de
  fichiers `.po` à compiler dans Docker, et les deux langues se relisent
  côte à côte.
- **Aucune URL n'est écrite en dur** dans le thème : `morgane_url()` résout
  chaque lien dans la langue active.
- Polylang gratuit ne filtre pas les produits WooCommerce (l'extension
  officielle est payante) : un filtre maison s'en charge dans `functions.php`
  et dans le bloc `morgane/artworks`.

### Gestion des formulaires

Trois formulaires, un seul bloc (`morgane/form`, attribut `type`) :

| Formulaire | Champs |
|---|---|
| Commissions | idée du projet, taille/format, médium, courriel |
| Collaborations | description du projet, courriel |
| Murales | dimensions, lieu, dates, idée, courriel |

Traitement : jeton de sécurité (nonce), piège à robots invisible,
assainissement de chaque champ, envoi par `wp_mail()` avec l'adresse du
demandeur en `Reply-To`, puis redirection — rafraîchir la page ne renvoie pas
le message. Les erreurs réaffichent le formulaire avec les valeurs saisies.

### Maintenance future

Ce que Morgane fait seule, sans toucher au code :

- ajouter une œuvre → dupliquer le produit modèle, changer titre / photo / prix ;
- remplir un portfolio → éditer la page, insérer un bloc Galerie, choisir le
  style « Défilement horizontal » ;
- corriger un texte → éditer la page, puis sa traduction ;
- changer les réseaux sociaux ou le courriel → Apparence > Éditeur >
  Modèles > Pied de page.

---

## 4. Développement et déploiement

### Organisation locale avec Docker

- `docker-compose.yml` : MariaDB + WordPress + un conteneur WP-CLI ponctuel.
- **Seul `theme/` est versionné.** Le cœur de WordPress et la base vivent dans
  des volumes Docker, hors du dépôt.
- `scripts/` contient l'installation reproductible : langues, contenus, pages,
  boutique. Tous les scripts sont idempotents.

### Points d'attention pour le futur déploiement

- **Auto-héberger les polices** (Fraunces, DM Sans) avant la mise en ligne :
  performance et Loi 25. Aujourd'hui elles viennent du CDN Google.
- **Courriel sortant** : `wp_mail()` en PHP natif finit en spam. Prévoir un
  service SMTP (le premier formulaire perdu est un client perdu).
- **HTTPS obligatoire** avant d'activer le paiement.
- **Sauvegardes** base + `wp-content/uploads` : les photos d'œuvres ne sont pas
  dans Git.
- **Environnement de préproduction** avant chaque mise en production.
- Cache de page à mettre en place **après** avoir vérifié qu'il ne casse ni le
  panier ni la bascule de langue.

### Ce qu'il ne faut pas figer trop tôt

- L'hébergeur et le plan serveur.
- Le fournisseur de paiement (Stripe, PayPal, virement) — décision commerciale.
- Les frais et zones de livraison : ils dépendent du poids réel des envois.
- Le nombre exact de formats de tirage : c'est une liste de termes, elle
  s'ajuste en deux clics.

---

## 5. Questions critiques

Seules celles qui bloquent :

1. **Adresses réelles** — Instagram, TikTok, courriel professionnel de Morgane.
   Sans ça le pied de page et les formulaires pointent vers des placeholders.
2. **Photos** — au minimum : image d'accueil, portrait, 2 murales, et une
   photo par œuvre en vente. Tout le reste est en place et les attend.
3. **Livraison** — expédie-t-elle hors Québec ? hors Canada ? Cela détermine
   les zones et les frais WooCommerce.

Tout le reste peut avancer sans réponse.

---

## 6. Plan de production

**Lot 1 — fait**

1. Design system : palette sable / bordeaux, typographie, espacements.
2. En-tête (menu dépliant, nom centré, langue, thème, panier) et pied de page.
3. Arborescence complète FR + EN, avec les textes définitifs.
4. Les trois formulaires, fonctionnels et sécurisés.
5. Portfolios à défilement horizontal.
6. Structure boutique : catégories, attribut Taille, produit modèle.
7. Photos des pages : conversion WebP et import par `scripts/media.php`, pose
   par `scripts/pages.php` (accueil, À propos, murales, quatre portfolios,
   cartes de navigation). Texte alternatif rédigé par photo et par langue.

**Lot 2 — reste**

8. Photos des produits de la boutique.
9. Renseigner réseaux sociaux et courriel professionnel.

**Lot 3 — avant mise en ligne**

10. Fiche produit et panier : surcharger `theme/woocommerce/` au même niveau
    de soin que la grille.
11. Livraison, taxes, paiement, pages légales (confidentialité, conditions,
    retours).
12. Auto-héberger les polices, brancher un SMTP, activer HTTPS, mettre en
    place les sauvegardes.
13. Préproduction, relecture bilingue, puis production.

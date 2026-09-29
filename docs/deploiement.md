# Mise en ligne

Ce que le thème ne peut pas faire tout seul. Le reste — polices, GSAP, en-têtes
`X-Frame-Options`, `X-Content-Type-Options` et `Referrer-Policy` — voyage avec
le dossier `theme/` et ne demande aucune configuration.

## Ce qu'on envoie sur le serveur

**Uniquement `theme/`**, dans `wp-content/themes/morgane/`.

Ni `.env` (il contient `morgane/morgane`), ni `docker-compose.yml`, ni
`scripts/` : ce sont les outils du poste de développement. Et le compte
administrateur du serveur ne réutilise pas les identifiants locaux.

Le thème embarque désormais ses dépendances — six fichiers `.woff2` et les deux
fichiers GSAP, dans `assets/fonts/` et `assets/js/vendor/`. Rien n'est appelé
chez un tiers : c'est délibéré, voir SEC-1 de l'audit.

## Sur le serveur, dans l'ordre

1. **Permaliens** en `Réglages > Permaliens > Nom de l'article` (`/%postname%/`).
   Le thème et les scripts partent tous de cette structure.

2. **Créer les pages avec ces identifiants exacts** — le menu les résout par leur
   chemin français, et une page absente renvoie discrètement vers l'accueil :

   | Page | Identifiant (slug) |
   |---|---|
   | Murales | `murales` |
   | Portfolio | `portfolio` |
   | À propos | `a-propos` |
   | Commissions et collaborations | `commissions-et-collaborations` |

   Les deux sous-pages `commissions` et `collaborations` se rangent **sous** la
   page d'entrée : c'est ce qui leur permet de garder un identifiant court sans
   entrer en collision avec leurs équivalents anglais.

3. **Page d'accueil statique** dans `Réglages > Lecture`. Sans cela,
   `front-page.html` ne s'applique pas et l'accueil retombe sur la liste des
   articles.

4. **Renommer les pages de WooCommerce**, qu'il crée en anglais : Cart, Checkout,
   My account, Shop deviennent Panier (`panier`), Commande (`commande`),
   Mon compte (`mon-compte`), Boutique (`boutique`). Puis revalider les
   permaliens. `scripts/setup.sh` le fait en local ; sur le serveur c'est à la
   main, depuis `Pages > Modification rapide`.

5. **Supprimer le contenu de démonstration** : l'article « Hello world! » et la
   page « Page d'exemple ». Ils se font indexer tels quels.

6. **Configurer l'envoi de courriel.** Le point le plus important de la liste :
   les trois formulaires sont la seule voie de contact du site. Le `mail()` d'un
   serveur mutualisé part en indésirable quand il part. Installer un envoi SMTP
   authentifié — WP Mail SMTP avec Brevo ou Postmark — puis envoyer un vrai
   message depuis la page Commissions pour vérifier qu'il arrive.

   Le thème signale désormais un échec d'envoi au visiteur et lui donne
   l'adresse en clair, mais c'est un filet, pas une solution.

7. **Adresse de réception.** Par défaut les demandes partent vers l'adresse
   d'administration (`Réglages > Général`). Pour la découpler, filtrer
   `morgane_contact_email`.

8. **Décider pour Polylang.** Le thème tourne sans lui : le sélecteur de langue
   disparaît et tout retombe en français, sans panne. Mais la moitié bilingue du
   site n'existe pas tant qu'il n'est pas installé.

## Installer sur un WordPress neuf

**Le thème n'est que l'habillage.** Copier `theme/` donne un site correctement
dessiné mais **vide**, dont le menu pointe six fois vers l'accueil : les textes,
les pages et la boutique vivent dans WordPress. Le thème embarque donc un
installateur qui les écrit, utilisable sans SSH.

Procédure complète, vérifiée de bout en bout sur une installation vierge.

### 1. WordPress

Assistant habituel, puis `Réglages > Général > Langue du site` → **Français du
Canada**.

### 2. Les deux extensions

`Extensions > Ajouter` : **WooCommerce**, puis **Polylang** (version gratuite).

**Passez les deux assistants de configuration.** L'installateur crée les langues
et pose les réglages d'URL lui-même, avec les bonnes valeurs — les refaire à la
main ne peut que diverger.

### 3. Le thème

Fabriquer le zip depuis le dépôt :

```bash
powershell -ExecutionPolicy Bypass -File scripts/faire-zip.ps1
```

Il sort dans `dist/`, numéroté d'après la version lue dans `theme/style.css`.
Environ 700 Ko : polices et GSAP compris, aucune dépendance externe.

**Ne pas le faire à la main avec `tar`.** `tar -a -cf morgane.zip theme` écrit
une archive *tar* sous un nom en « .zip » — bsdtar, livré avec Windows, ignore
l'extension. Rien ne se voit : le fichier pèse le poids attendu et s'ouvre dans
l'explorateur, mais WordPress le refuse avec « Archive incompatible ». Le
script passe par le compresseur de .NET et vérifie la signature du fichier
avant de le rendre.

Puis `Apparence > Thèmes > Ajouter > Téléverser un thème` → le zip, puis
**Activer**.

### 4. Les photos

Sur le poste, produire le dossier à envoyer :

```bash
python3 scripts/preparer-photos.py
```

64 Mo de JPEG deviennent 10 Mo de WebP. La conversion se fait ici et pas sur le
serveur : PHP y est coupé au bout de trente à cent vingt secondes et plafonné à
128 Mo, vingt-trois photos de 4000 px n'y passeraient pas.

Puis déposer le contenu de `dist/photos/` **par FTP** dans
`wp-content/uploads/morgane-photos/`. FTP n'est pas SSH : tous les hébergeurs le
donnent.

### 5. Le bouton

`Apparence > Installer le contenu`. Cinq préalables sont vérifiés à l'écran
(Polylang, WooCommerce, photos trouvées, WebP, droits d'écriture). Quand ils sont
verts, cliquer.

Trente et une étapes s'enchaînent, **une par requête** : langues, produits
traduisibles, les vingt-trois photos une à une, les quatorze pages dans les deux
langues, la boutique, les réglages. Progression et journal à l'écran.

C'est **rejouable** : un second passage ne duplique rien et n'écrase aucune page
déjà modifiée.

### 6. Vérifier

- `/portfolio/` et `/contact/` s'affichent en français, sans redirection
- `/en/` sert l'accueil anglais, avec le slogan traduit dans l'onglet
- Une adresse inexistante affiche la page 404 du thème
- Le panier n'appelle aucun domaine tiers (onglet Réseau)

### Si l'hébergement a SSH

Les mêmes scripts se lancent directement, sans passer par le bouton :

```bash
wp eval-file wp-content/themes/morgane/install/pll-setup.php
```

…et ainsi de suite dans l'ordre : `pll-setup`, `pll-cpt`, `pll-assign`,
`pll-content`, `media`, `pages`, `shop`. Ils ne parlent à WP-CLI que pour écrire
dans le journal ; hors ligne de commande, une cale prend le relais.

### Les extensions, en résumé

| Extension | Nécessaire ? |
|---|---|
| **Polylang** (gratuit) | **Obligatoire** pour l'installateur : les scripts s'arrêtent sans elle. |
| **WooCommerce** | Pour la boutique. Sans elle le thème tourne : le panier disparaît de l'en-tête, l'étape boutique est sautée. |

Rien d'autre. Pas d'extension de formulaires, pas de constructeur de pages.

## En-têtes HTTP

Le thème pose déjà `X-Content-Type-Options`, `X-Frame-Options` et
`Referrer-Policy`. Deux choses restent au serveur.

**HSTS**, qui n'a de sens qu'une fois le certificat en place — sinon on
verrouille un site inaccessible :

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

**La politique de sécurité de contenu.** Elle est devenue réaliste maintenant
que plus rien n'est chargé depuis un tiers, mais elle demande d'inventorier
d'abord ce que WooCommerce et la passerelle de paiement injectent. À poser
d'abord en `Content-Security-Policy-Report-Only`, à lire les rapports, puis à
durcir. Prévoir un `hash` pour le petit script anti-scintillement posé en ligne
dans `theme/inc/blocks.php`.

## Vérifier après coup

- Une adresse inexistante affiche la page 404 du thème, avec ses deux boutons.
- `/en/` sert bien l'accueil anglais, et la balise `<title>` y annonce le
  slogan en anglais.
- Un envoi de formulaire arrive vraiment dans la boîte.
- Le panier et la page de commande n'appellent aucun domaine tiers
  (onglet Réseau des outils de développement).

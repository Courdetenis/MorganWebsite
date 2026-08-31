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

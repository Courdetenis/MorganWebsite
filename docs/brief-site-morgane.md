# Brief projet — Site e-commerce pour Morgane (artiste peintre, Québec)

> ⚠️ **Document historique — partiellement remplacé.**
> Le cahier des charges de référence est maintenant `docs/architecture.md`
> (arborescence complète, textes définitifs, direction artistique rose pâle /
> vert sauge). Ce fichier reste utile pour le contexte d'origine, mais sa
> direction artistique et son arborescence ne font plus foi.

> Document de cadrage à destination de Claude Code.
> **Lis intégralement ce fichier avant d'écrire du code.** Les sections « Questions ouvertes »
> et « Règles non négociables » conditionnent l'architecture.

---

## 0. Contexte

Morgane est artiste (peinture, dessin). Elle a besoin d'une boutique en ligne :

- **facile à opérer au quotidien** pour elle (ajouter une œuvre, changer un prix, marquer « vendu ») ;
- avec un **design remarquable** — c'est le critère n°1, le site doit lui ressembler et servir ses œuvres ;
- **peu coûteuse à maintenir** (pas d'abonnements empilés).

Elle réside au **Québec**, ce qui impose des contraintes légales et fiscales spécifiques (voir §7).

### Arbitrage déjà tranché

| Option | Verdict |
|---|---|
| Développement e-commerce from scratch | ❌ Écarté — sécurité, paiement, conformité PCI |
| Shopify | ❌ Écarté — coût qui explose dès qu'on ajoute des apps |
| **WordPress auto-hébergé + WooCommerce** | ✅ **Retenu** |

Rationale : aucune commission de plateforme, liberté totale de design, coûts récurrents faibles.

---

## 1. Questions ouvertes — à résoudre AVANT de coder

Ces réponses changent l'architecture. Ne pas supposer, demander.

1. **Que vend-elle exactement ?**
   - Originaux uniques → stock = 1, pièces fragiles et chères, expédition sur mesure
   - Tirages / impressions → stock multiple, expédition en tube, variations de format
   - Commandes personnalisées → formulaire de devis, pas de panier

   Ce n'est pas la même boutique. La réponse détermine le modèle de données produit.

2. **Chiffre d'affaires prévu ?**
   Sous **30 000 $ CA sur 4 trimestres consécutifs**, elle est « petit fournisseur » : aucune
   obligation de s'inscrire à la TPS/TVQ ni de les percevoir. → Configuration fiscale beaucoup
   plus simple au lancement.

3. **Vend-elle aussi en personne** (marchés, vernissages, galeries) ?
   Si oui → privilégier Square plutôt que Stripe (synchro d'inventaire en ligne / terminal,
   crucial pour éviter de vendre deux fois le même original).

4. **Le site doit-il être bilingue FR/EN ?**
   Le français est obligatoire (§7). L'anglais est optionnel mais engage un travail de i18n.

5. **Qui maintient le site dans 18 mois ?**
   Vrai coût caché de WordPress. À trancher explicitement avec elle.

---

## 2. Stack technique

| Couche | Choix | Justification |
|---|---|---|
| Socle | WordPress **auto-hébergé** (.org) | .com Business = plus cher, moins libre |
| Boutique | WooCommerce (gratuit) | Zéro commission de plateforme |
| Thème | **Thème bloc (FSE) sur mesure, hybride** | Design total + édition visuelle pour Morgane |
| Animations | **GSAP + ScrollTrigger** | 100 % gratuit depuis avril 2025, usage commercial inclus, tous les plugins Club compris |
| Paiement | Stripe (+ PayPal optionnel) | Intégration la plus propre au Canada |
| Hébergement | WHC (Montréal) ou Cloudways | Datacentre canadien, support en français |

### Point technique critique : thème hybride

WooCommerce ne supporte que **partiellement** le full site editing. On peut construire une
boutique parfaitement fonctionnelle en blocs, mais tout n'est pas personnalisable depuis
l'éditeur de site.

**Architecture retenue :**

- **Thème bloc (FSE)** pour tout le site vitrine : accueil, à propos, galerie, expositions, blog
  → Morgane édite visuellement, sans risque
- **Surcharges PHP classiques** dans `theme/woocommerce/` pour fiche produit, panier, commande
  → contrôle pixel-perfect là où le design compte le plus

`theme.json` sert de **design system** : couleurs, typographie, échelle d'espacements,
breakpoints. Tout ce qui y est déclaré devient éditable par elle sans possibilité de casser
la cohérence visuelle.

### Interdictions

- ❌ **Elementor, Divi, WPBakery** — markup dégradé, performances plombées. Rédhibitoire pour
  un site où les images doivent charger vite et fidèlement.
- ❌ Tout plugin « ultimate addons », sliders lourds, bundles de fonctionnalités.

*(Alternative si le développement custom n'est pas envisageable : **Bricks Builder** — builder
orienté développeur, markup propre, licence à vie. Mais c'est un lock-in.)*

---

## 3. Design

### Ordre d'importance réel du résultat perçu

1. **Les photos des œuvres — 80 % du résultat.**
   Un thème sublime avec de mauvaises photos = site moche. Non négociable :
   - Reflex ou hybride, lumière diffuse indirecte, fond neutre
   - Charte colorimétrique (ColorChecker) pour la fidélité des couleurs
   - **Profil sRGB intégré** dans les fichiers exportés
   - Si Morgane ne peut pas le faire : budgéter un photographe. Meilleur investissement du projet.

2. **Typographie et espace blanc.**
   Pour un site d'artiste, l'interface doit disparaître. Beaucoup d'air, une typographie forte
   (pistes : Fraunces, Instrument Serif, Editorial New), **une seule** couleur d'accent.

3. **Animations — subtiles.**
   Reveal au scroll, transitions de page (View Transitions API), zoom sur détail d'œuvre.
   Toujours respecter `prefers-reduced-motion`.

### Workflow

```
Moodboard → direction artistique → 4-5 écrans clés en Figma → validation Morgane
   → tokens traduits dans theme.json → templates
```

### Environnement de développement

- **LocalWP** pour le développement local
- **Git** versionne le thème (et uniquement le thème)
- **Staging** chez l'hébergeur, obligatoire avant chaque mise en production

---

## 4. Paiement

| Solution | Quand la choisir |
|---|---|
| **Stripe** | Défaut. Cartes, Apple Pay, Google Pay, sans redirection. Extension officielle gratuite. ~2,9 % + 0,30 $ CA |
| **Square** | Si vente en personne — synchro inventaire en ligne / terminal |
| **PayPal** | En complément, pour les clients réticents à saisir leur carte |
| **Interac e-Transfer / virement** | Paiements hors ligne natifs dans Woo. Utile pour les grosses pièces |

### Taxes

Si inscription requise (voir §1.2) : **TPS 5 % + TVQ 9,975 %**, **non composées** — les deux
s'appliquent sur le prix hors taxes. Deux lignes dans les réglages Woo natifs. **Aucun plugin
fiscal nécessaire.**

---

## 5. Livraison

Piège classique du projet. Pour des œuvres d'art :

- **Tarifs forfaitaires par zone et par format** (petit / moyen / grand / hors norme) — simple,
  natif, zéro plugin
- **Cueillette locale** activée (natif Woo)
- **Devis sur demande** pour les grands formats — un formulaire de contact suffit au lancement
- ❌ Pas de plugin de calcul temps réel (Postes Canada) tant que le volume ne le justifie pas
- Prévoir l'**assurance** sur les originaux + emballage sérieux (caisse-carton, coins de
  protection, tube rigide pour les tirages)

---

## 6. Plugins — le strict minimum (8)

1. **WooCommerce**
2. **WooCommerce Stripe Gateway**
3. **LiteSpeed Cache** (gratuit, si l'hébergeur le supporte) ou le cache de l'hôte
4. **UpdraftPlus** (sauvegardes) ou sauvegardes côté hébergeur
5. **Wordfence** (gratuit) ou pare-feu de l'hébergeur
6. **SEOPress** ou **Rank Math** (versions gratuites)
7. **Complianz** (consentement cookies — exigence Loi 25)
8. **Polylang** — seulement si le site est bilingue

**Tout le reste doit être implémenté dans le thème.** Chaque plugin ajouté est une dette de
maintenance et un risque de sécurité.

---

## 7. Conformité québécoise — obligatoire

### Loi 96 (Charte de la langue française)

L'article 52 couvre les publications commerciales « quel que soit le support », ce qui fait
entrer les sites web et boutiques en ligne dans son champ d'application. L'obligation de
traduire le contenu commercial s'applique aux entreprises ayant un établissement au Québec
et visant le marché québécois.

**Concrètement :**
- Le site **doit** exister en français
- S'il existe une version anglaise, le contenu **et les fonctionnalités** doivent être
  identiques dans les deux versions
- Le français doit être la langue par défaut

→ Si bilingue : **Polylang**, français en langue par défaut.

### Loi 25 (protection des renseignements personnels)

- Politique de confidentialité
- Responsable de la protection des renseignements personnels désigné (Morgane)
- Bandeau de consentement cookies si analytics
- Procédure documentée en cas d'incident de confidentialité

### Loi sur la protection du consommateur (contrat à distance)

Doivent être affichés **avant** la validation de l'achat :
- Prix total
- Frais applicables (livraison, taxes)
- Délai de livraison
- Politique d'annulation et de retour

---

## 8. Phasage

| Phase | Contenu | Durée |
|---|---|---|
| 0 | Cadrage avec Morgane (catalogue, budget, ambitions) — §1 | 1 sem. |
| 1 | Direction artistique + maquettes Figma **validées** | 2 sem. |
| 2 | Hébergement, domaine, staging, WP + Woo installés | 2 j |
| 3 | Développement du thème (theme.json, templates, surcharges Woo) | 3-4 sem. |
| 4 | Photos + contenu + fiches produits (**par Morgane**, pas par le dev) | en parallèle |
| 5 | Paiement, taxes, livraison, pages légales | 3 j |
| 6 | Performance, SEO, accessibilité, tests d'achat réels bout en bout | 1 sem. |
| 7 | **Formation de Morgane** (2 h + documentation écrite ou vidéo) | 1 j |
| 8 | Lancement + plan de maintenance écrit | — |

---

## 9. Budget

**Récurrent :**
- Hébergement : 150-350 $ CA / an
- Domaine : ~20 $ CA / an
- Thème : 0 $
- Plugins : 0 $

**Variable :** 2,9 % + 0,30 $ CA par transaction (Stripe)

**Ponctuel à prévoir :** photographie professionnelle des œuvres.

C'est sur les coûts récurrents que WooCommerce écrase Shopify.

---

## 10. Règles non négociables

1. **La phase 7 (formation) n'est pas optionnelle.** Si Morgane n'est pas autonome pour ajouter
   une œuvre, changer un prix et marquer une pièce vendue, le site sera mort dans six mois.
   La documentation doit être écrite, en français, avec captures d'écran.

2. **WordPress auto-hébergé demande des mises à jour, des sauvegardes vérifiées et une
   surveillance.** Quelqu'un doit assumer ce rôle explicitement, par écrit, avant le lancement.
   Si personne ne le veut, le surcoût de Shopify n'est plus une dépense mais une assurance —
   et tout l'arbitrage du §0 est à refaire.

3. **Aucun plugin ajouté sans justification écrite** de pourquoi il ne peut pas être remplacé
   par du code dans le thème.

4. **Le français d'abord**, partout, y compris dans l'interface d'administration et dans les
   courriels transactionnels.

---

## Annexe — Alternative à considérer

Si Morgane vend surtout des **originaux uniques en petit nombre** (< 30 pièces actives) :

Un **site statique (Astro) + Stripe Payment Links** offrirait une liberté de design encore plus
grande, une surface de sécurité quasi nulle à maintenir et un coût proche de zéro.

**Contrepartie :** ajouter une œuvre demande une manipulation technique. À ne retenir que si
un développeur reste dans la boucle durablement.

Cette option est incompatible avec l'exigence d'autonomie du §10.1 — elle n'est listée ici que
si cette exigence est explicitement abandonnée.

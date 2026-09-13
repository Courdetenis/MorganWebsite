# Tâche 3 — La bascule au rendu

Rapport reconstitué par le contrôleur : l'implémenteur (agent a1c64b2eb47f3b92f)
a livré le code et renvoyé son résumé, mais n'a pas écrit ce fichier. Contenu
établi à partir de son retour et du diff, tous deux vérifiés par la relecture.

**Statut :** DONE — **Commit :** `50595ba`

## Ce qui a été fait

- `theme/inc/i18n.php` : point d'entrée `morgane_langue_forcee` en première
  instruction de `morgane_lang()`, au seul bénéfice du banc d'essai — il n'y a
  pas de langue de page en ligne de commande.
- `theme/inc/produits.php` : `morgane_produit_en_actif()` (garde
  `is_admin() || REST_REQUEST`, puis `'en' === morgane_lang()`), et quatre
  filtres — `woocommerce_product_get_name`, `the_title`,
  `woocommerce_product_get_short_description`, `the_content`.
- `scripts/verif-produits.php` : trois assertions de plus.

## Vérifications

Banc : 7 assertions, 1 échec — « Aucun produit rattaché à l'anglais », attendu
jusqu'à la tâche 6.

La relecture a vérifié en sus, hors banc : la cohabitation dans les deux sens
avec le filtre `the_title` de `inc/boutique.php` (chacun sort tôt sur ce qui ne
le concerne pas) ; l'étanchéité de `the_content` par une vraie boucle `WP_Query`
sur deux produits, dont un sans méta anglaise ; et qu'aucun fichier du thème
n'accroche `morgane_langue_forcee`, qui ne peut donc pas se déclencher en
production.

## Réserves connues, non bloquantes

- Le texte anglais servi par `the_content` ne passe pas par `wptexturize` : notre
  filtre s'exécute en priorité 10, après les callbacks natifs déjà accrochés là.
  Guillemets et tirets typographiques ne sont donc pas appliqués côté anglais.
- `is_admin()` est vrai pendant `admin-ajax.php` : le rafraîchissement des
  fragments du panier servirait du français. Sans effet ici, le thème n'y
  affichant qu'un compteur numérique, jamais un nom d'œuvre.

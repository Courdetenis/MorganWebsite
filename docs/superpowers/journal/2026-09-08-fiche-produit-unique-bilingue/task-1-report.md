# Tâche 1 : Banc d'essai et état des lieux — Rapport

## Statut
**DONE**

Tous les steps ont été exécutés avec succès.

## Identifiants de commit
- `e774ce1` — test(produits): banc d'essai de la fiche unique bilingue

## Résumé du banc d'essai
**1 assertion, 1 échec (attendu).**

L'unique assertion "Aucun produit rattaché à l'anglais" échoue comme prévu, trouvant 5 produits: `95,17,18,19,16`.

## État de départ documenté

### Produits trouvés dans la base (inventaire complet)
```
95	en	stock=NULL	Modèle — print en plusieurs tailles (EN)
19	en	stock=25	Northern Lights — print
18	en	stock=25	Dried Flowers — drawing
17	en	stock=25	Winter Forest — print
16	en	stock=25	Low Tide — print
15	fr	stock=25	Marée basse — tirage
14	fr	stock=25	Fleurs séchées — dessin
13	fr	stock=25	Forêt en hiver — tirage
12	fr	stock=25	Aurore boréale — tirage
```

### Interprétation
- 5 produits sont rattachés à l'anglais (`lang='en'`), servant de doublons aux 4 produits français de base
- Les produits français (12, 13, 14, 15) forment les fiches de départ
- Le produit 95 a une particularité : son stock est `NULL` (pas de quantité définie)
- Tous les autres produits anglais ont un stock de 25

### Mise en place du banc
Le fichier `scripts/verif-produits.php` est créé et fonctionne correctement. Il fournit:
- `mg_verif($nom, $ok, $detail)` : enregistre chaque résultat d'assertion
- `mg_verif_bilan()` : affiche le bilan et retourne le nombre d'échecs
- Exécution via `docker compose run --rm --entrypoint wp wpcli eval-file /scripts/verif-produits.php`

## Réserves
Aucune. Le banc fonctionne comme attendu et l'échec observé confirme l'état de départ : les 5 fiches anglaises attendent d'être migrées vers un modèle unilingue avec sélecteur de langue.

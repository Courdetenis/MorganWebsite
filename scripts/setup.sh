#!/bin/sh
# Installation locale en une commande : WordPress + WooCommerce + Polylang
# + thème + contenus bilingues + structure de boutique.
# Idempotent : relançable sans rien casser. Dev local uniquement.
set -e

WP_URL="http://localhost:8080"
WP_TITLE="Morgane Cachia – Art"
WP_ADMIN_USER="morgane"
WP_ADMIN_PASSWORD="morgane"
WP_ADMIN_EMAIL="admin@example.com"

echo "Waiting for database..."
until wp db check --quiet 2>/dev/null; do sleep 3; done

if ! wp core is-installed 2>/dev/null; then
  echo "Installing WordPress core..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

# Le nom affiché dans l'en-tête vient du titre du site.
wp option update blogname "$WP_TITLE"
wp option update blogdescription "Illustratrice, artiste peintre et muraliste à Montréal"

# French by default (Loi 96). Falls back gracefully if language pack missing.
wp language core install fr_CA --activate 2>/dev/null || wp site switch-language fr_CA 2>/dev/null || true

echo "Installing WooCommerce..."
wp plugin is-installed woocommerce || wp plugin install woocommerce --activate
wp plugin is-active woocommerce || wp plugin activate woocommerce

# Les chaînes de WooCommerce (« Add to cart », « View cart »…) viennent de son
# propre paquet de langue : sans lui, la boutique reste en anglais même si le
# site est en fr_CA.
wp language plugin install woocommerce fr_CA >/dev/null 2>&1 || true

echo "Installing Polylang..."
wp plugin is-installed polylang || wp plugin install polylang --activate
wp plugin is-active polylang || wp plugin activate polylang

# WooCommerce base config (skip onboarding wizard).
wp option update woocommerce_store_address "" >/dev/null 2>&1 || true
wp option update woocommerce_default_country "CA:QC" >/dev/null 2>&1 || true
wp option update woocommerce_currency "CAD" >/dev/null 2>&1 || true
wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json >/dev/null 2>&1 || true
# WooCommerce 10 arrive avec le mode « bientôt disponible » activé : la boutique
# et les fiches produit renvoient une page d'attente au lieu du catalogue.
wp option update woocommerce_coming_soon "no" >/dev/null 2>&1 || true
wp option update woocommerce_store_pages_only "no" >/dev/null 2>&1 || true

# WooCommerce crée ses quatre pages en anglais (« Shop », « Cart », « Checkout »,
# « My account ») : sur un site dont la langue par défaut est le français, ces
# titres s'affichent en haut du catalogue, dans le fil d'Ariane, dans l'onglet du
# navigateur et dans l'adresse. On les repasse en français, une fois.
#
# Chaque ligne : option WooCommerce | slug d'origine | titre FR | slug FR.
# La condition sur le slug d'origine rend l'opération rejouable sans risque et
# respecte un renommage fait à la main depuis l'administration.
rename_wc_page() {
  _id=$(wp option get "$1" 2>/dev/null || true)
  [ -n "$_id" ] || return 0
  [ "$(wp post get "$_id" --field=post_name 2>/dev/null)" = "$2" ] || return 0
  wp post update "$_id" --post_title="$3" --post_name="$4" >/dev/null
  echo "  $2 -> $4"
}

echo "Renaming WooCommerce pages..."
rename_wc_page woocommerce_shop_page_id       shop       "Boutique"   "boutique"
rename_wc_page woocommerce_cart_page_id       cart       "Panier"     "panier"
rename_wc_page woocommerce_checkout_page_id   checkout   "Commande"   "commande"
rename_wc_page woocommerce_myaccount_page_id  my-account "Mon compte" "mon-compte"

# Contenu de démonstration de WordPress : l'article « Hello world! » et la page
# « Page d'exemple ». Publiés tels quels, ils finissent dans le plan du site et
# se font indexer.
#
# La page « Politique de confidentialité » n'est pas touchée : elle est en
# brouillon, donc invisible, et une boutique québécoise devra la remplir pour la
# Loi 25. Autant garder le brouillon que WordPress a préparé.
echo "Removing WordPress sample content..."
for _slug in hello-world sample-page; do
  _id=$(wp post list --name="$_slug" --post_type=post,page --field=ID --format=ids 2>/dev/null || true)
  if [ -n "$_id" ]; then
    wp post delete $_id --force >/dev/null 2>&1 && echo "  supprimé : $_slug"
  fi
done

echo "Activating theme..."
wp theme activate morgane

# Favicon : le logo, importé une fois depuis le thème vers la médiathèque.
# WordPress pose ensuite lui-même les balises dans <head>.
if [ "$(wp option get site_icon 2>/dev/null)" = "" ] || [ "$(wp option get site_icon 2>/dev/null)" = "0" ]; then
  echo "Importing site icon..."
  ICON=$(wp media import /var/www/html/wp-content/themes/morgane/assets/img/favicon.png \
    --title="Logo Morgane Cachia - Art" --porcelain 2>/dev/null || true)
  if [ -n "$ICON" ]; then
    wp option update site_icon "$ICON"
  fi
fi

# Pretty permalinks (SEO).
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# Sample products (tirages / impressions — multiples exemplaires).
if [ "$(wp post list --post_type=product --format=count)" -eq 0 ]; then
  echo "Creating sample products..."
  for t in "Aurore boréale — tirage" "Forêt en hiver — tirage" "Fleurs séchées — dessin" "Marée basse — tirage"; do
    wp wc product create \
      --name="$t" \
      --type=simple \
      --regular_price="120" \
      --manage_stock=true \
      --stock_quantity=25 \
      --status=publish \
      --user="$WP_ADMIN_USER" >/dev/null 2>&1 || true
  done
fi

# Bilingue + contenus. Ordre imposé : langues, puis produits traduisibles,
# puis assignation, puis pages et boutique.
echo "Configuring Polylang and content..."
wp eval-file /scripts/pll-setup.php
wp eval-file /scripts/pll-cpt.php
wp eval-file /scripts/pll-assign.php
wp eval-file /scripts/pll-content.php
# Avant les pages : pages.php lit la table des photos écrite par media.php.
wp eval-file /scripts/media.php
wp eval-file /scripts/pages.php
wp eval-file /scripts/shop.php

wp rewrite flush --hard

echo ""
echo "Done. Site: $WP_URL  |  Admin: $WP_URL/wp-admin  ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"

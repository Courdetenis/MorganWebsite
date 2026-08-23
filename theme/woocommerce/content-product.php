<?php
/**
 * Carte d'œuvre dans les grilles (accueil, boutique, catégories).
 * Surcharge du template WooCommerce : markup maîtrisé, pas de flottants,
 * hauteurs prévisibles.
 *
 * @package Morgane
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$has_image = has_post_thumbnail( $product->get_id() );
?>
<li <?php wc_product_class( 'artwork', $product ); ?>>
	<a class="artwork__link" href="<?php echo esc_url( get_permalink() ); ?>">
		<span class="artwork__frame<?php echo $has_image ? '' : ' artwork__frame--empty'; ?>">
			<?php
			// Sans photo, la tuile colorée du CSS tient lieu de visuel :
			// on n'affiche pas le placeholder gris de WooCommerce.
			if ( $has_image ) {
				echo $product->get_image( 'woocommerce_thumbnail' );
			}
			?>
			<?php if ( $product->is_on_sale() ) : ?>
				<span class="artwork__badge"><?php esc_html_e( 'Promo', 'morgane' ); ?></span>
			<?php elseif ( ! $product->is_in_stock() ) : ?>
				<span class="artwork__badge artwork__badge--out"><?php esc_html_e( 'Vendu', 'morgane' ); ?></span>
			<?php endif; ?>
		</span>

		<span class="artwork__body">
			<span class="artwork__title"><?php echo esc_html( $product->get_name() ); ?></span>
			<?php $price = $product->get_price_html(); ?>
			<?php if ( $price ) : ?>
				<span class="artwork__price"><?php echo wp_kses_post( $price ); ?></span>
			<?php endif; ?>
		</span>
	</a>

	<?php
	/**
	 * Bouton « ajouter au panier » (et AJAX) fourni par WooCommerce.
	 *
	 * @hooked woocommerce_template_loop_add_to_cart - 10
	 */
	do_action( 'woocommerce_after_shop_loop_item' );
	?>
</li>

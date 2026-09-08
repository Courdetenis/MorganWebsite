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
				/*
				 * Pas `woocommerce_thumbnail` : c'est une coupe carrée de 300 px,
				 * seule taille à ce rapport, donc seule candidate de son srcset.
				 * Le navigateur n'avait rien d'autre à prendre et l'étirait — sur
				 * mobile le cadre fait 322 px de côté, soit 643 px réels sur un
				 * écran à deux points par pixel, plus du double de la source.
				 * D'où le grain, sur la boutique seulement.
				 *
				 * `woocommerce_single` garde le rapport de l'original : le srcset
				 * y court de 300 à 2000 px. Le cadre étant en 4/5 avec
				 * `object-fit: cover`, la forme de la source n'entre pas en jeu.
				 */
				echo $product->get_image(
					'woocommerce_single',
					array( 'sizes' => '(max-width: 480px) 90vw, (max-width: 820px) 45vw, 300px' )
				);
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

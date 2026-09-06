<?php
/**
 * Blocs dynamiques du thème : menu dépliant, sélecteur de langue,
 * bascule clair/sombre, grille d'œuvres.
 * Rendus en PHP, sans étape de build. Utilisés dans parts/header.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu principal.
 *
 * Bloc maison plutôt que le bloc « Navigation » de WordPress : les menus de
 * navigation en bloc se traduisent mal avec Polylang gratuit, alors que ces
 * cinq entrées ne bougeront pas. Les URL sont résolues dans la langue active
 * par morgane_url(), donc jamais codées en dur.
 *
 * Un seul balisage pour les deux affichages, le CSS décide :
 *   - au-dessus de 1280 px, les liens sont posés à plat dans l'en-tête et le
 *     bouton disparaît — on ne fait pas cliquer deux fois quelqu'un qui a la
 *     place d'afficher le menu ;
 *   - en dessous, le bouton ouvre le panneau.
 * Sans JavaScript, le panneau reste déplié (voir `html:not(.js)` dans le CSS).
 */
function morgane_render_menu() {
	$items = array(
		array( 'url' => morgane_home_url(),    'label' => array( 'fr' => 'Accueil',   'en' => 'Home' ) ),
		array( 'url' => morgane_shop_url(),    'label' => array( 'fr' => 'Boutique',  'en' => 'Shop' ) ),
		array( 'url' => morgane_url( 'murales' ),   'label' => array( 'fr' => 'Murales',   'en' => 'Murals' ) ),
		array( 'url' => morgane_url( 'portfolio' ), 'label' => array( 'fr' => 'Portfolio', 'en' => 'Portfolio' ) ),
		array( 'url' => morgane_url( 'a-propos' ),  'label' => array( 'fr' => 'À propos', 'en' => 'About' ) ),
	);

	// Hors du menu principal, mais toujours à portée (exigence du cahier des charges).
	$aside = array(
		'url'   => morgane_url( 'commissions-et-collaborations' ),
		'label' => array( 'fr' => 'Commissions et collaborations', 'en' => 'Commissions and collaborations' ),
	);

	$current = is_singular() ? get_permalink( get_queried_object_id() ) : '';

	$render = function ( $item ) use ( $current ) {
		$is_current = $current && untrailingslashit( $item['url'] ) === untrailingslashit( $current );
		return sprintf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( $item['url'] ),
			$is_current ? ' aria-current="page"' : '',
			esc_html( morgane_t( $item['label'] ) )
		);
	};

	$list = '';
	foreach ( $items as $item ) {
		$list .= $render( $item );
	}

	return sprintf(
		'<nav class="menu" aria-label="%1$s">
			<button class="menu__trigger" type="button" aria-expanded="false" aria-controls="menu-panel" aria-label="%1$s">
				<span class="menu__bars" aria-hidden="true"><span></span><span></span><span></span></span>
				<span class="menu__trigger-text">%2$s</span>
			</button>
			<div class="menu__panel" id="menu-panel">
				<ul class="menu__list">%3$s</ul>
				<div class="menu__aside"><ul class="menu__list">%4$s</ul></div>
			</div>
		</nav>',
		esc_attr( morgane_t( array( 'fr' => 'Menu principal', 'en' => 'Main menu' ) ) ),
		esc_html( morgane_t( array( 'fr' => 'Menu', 'en' => 'Menu' ) ) ),
		$list,
		$render( $aside )
	);
}

/**
 * Accueil de la langue active.
 */
function morgane_home_url() {
	if ( function_exists( 'pll_home_url' ) ) {
		return pll_home_url( morgane_lang() );
	}
	return home_url( '/' );
}

/**
 * Sélecteur de langue (FR / EN), en menu déroulant.
 *
 * C'était une pastille à deux segments, large de 90 px. Elle occupait à elle
 * seule la place de deux boutons ronds dans un en-tête déjà dense — et il n'y
 * avait plus de quoi loger le compte client. Réduite à un bouton rond de 44 px
 * qui affiche la langue courante et déroule les autres, elle rentre dans le
 * rang : quatre commandes de même taille.
 *
 * Le panneau s'ouvre au survol là où le survol existe, et au clic partout —
 * donc aussi sur téléphone et tablette, où survoler ne veut rien dire.
 *
 * S'appuie sur Polylang. N'affiche rien si l'extension est absente ou si une
 * seule langue est configurée.
 */
function morgane_render_lang_switcher() {
	if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_home_url' ) ) {
		return '';
	}

	$slugs = pll_languages_list();
	if ( ! is_array( $slugs ) || count( $slugs ) < 2 ) {
		return '';
	}

	$current = morgane_lang();

	$items = '';
	foreach ( $slugs as $slug ) {
		$url = '';

		// Une page, un article, une œuvre : sa traduction, quand elle existe.
		if ( is_singular() && function_exists( 'pll_get_post' ) ) {
			$translated = pll_get_post( get_queried_object_id(), $slug );
			if ( $translated ) {
				$url = get_permalink( $translated );
			}
		}

		/*
		 * Les pages de WooCommerce — boutique, panier, commande, compte — n'ont
		 * aucune traduction à trouver : Polylang gratuit ne les traduit pas.
		 * La boutique, en plus, n'est même pas une page « singulière » mais une
		 * archive de produits. Sans ce cas, changer de langue depuis l'une
		 * d'elles renvoyait à l'accueil, et l'on perdait au passage sa boutique,
		 * son panier ou son compte.
		 *
		 * Depuis une catégorie de produits on retombe sur la racine de la
		 * boutique : la catégorie traduite demanderait de traduire les termes,
		 * ce que Polylang gratuit ne fait pas pour WooCommerce. Mieux vaut la
		 * boutique dans la bonne langue que l'accueil.
		 */
		if ( ! $url ) {
			$wc = morgane_wc_page_courante();
			if ( $wc ) {
				$url = morgane_wc_page_url( $wc, $slug );
			}
		}

		// Ailleurs — recherche, 404, archives — l'accueil de la langue.
		if ( ! $url ) {
			$url = pll_home_url( $slug );
		}

		$is_current = ( $slug === $current );

		$items .= sprintf(
			'<a class="lang-switch__item%1$s" href="%2$s" lang="%3$s" hreflang="%3$s"%4$s>%5$s</a>',
			$is_current ? ' is-active' : '',
			esc_url( $url ),
			esc_attr( $slug ),
			$is_current ? ' aria-current="true"' : '',
			esc_html( strtoupper( $slug ) )
		);
	}

	$label = morgane_t( array( 'fr' => 'Choix de la langue', 'en' => 'Language' ) );

	return sprintf(
		'<div class="lang-switch" data-lang-switch>
			<button class="lang-switch__trigger" type="button" aria-expanded="false" aria-controls="lang-panel" aria-label="%1$s" title="%1$s">%2$s</button>
			<div class="lang-switch__panel" id="lang-panel">%3$s</div>
		</div>',
		esc_attr( $label ),
		esc_html( strtoupper( $current ) ),
		$items
	);
}

/**
 * Compte client.
 *
 * Un lien vers la page « Mon compte » de WooCommerce — connexion, inscription,
 * commandes, adresses. Le libellé change selon qu'on est identifié ou non :
 * une cliente connectée ne cherche pas « se connecter ».
 *
 * Rendu seulement si WooCommerce est là : sans lui, la page n'existe pas et le
 * bouton mènerait à l'accueil.
 */
function morgane_render_account() {
	if ( ! function_exists( 'wc_get_page_id' ) || wc_get_page_id( 'myaccount' ) <= 0 ) {
		return '';
	}

	$connecte = is_user_logged_in();
	$label    = $connecte
		? morgane_t( array( 'fr' => 'Mon compte', 'en' => 'My account' ) )
		: morgane_t( array( 'fr' => 'Se connecter', 'en' => 'Sign in' ) );

	$icone = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.6"/><path d="M4.8 20.2a7.2 7.2 0 0 1 14.4 0"/></svg>';

	return sprintf(
		'<a class="account-link%s" href="%s" aria-label="%s" title="%s">%s</a>',
		$connecte ? ' is-connected' : '',
		esc_url( morgane_wc_page_url( 'myaccount' ) ),
		esc_attr( $label ),
		esc_attr( $label ),
		$icone
	);
}

/**
 * Bascule clair / sombre.
 * L'état réel est posé par le script anti-FOUC (voir morgane_theme_boot_script).
 */
function morgane_render_theme_toggle() {
	$sun  = '<svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.6v2.2M12 19.2v2.2M4.2 12H2M22 12h-2.2M6.3 6.3 4.8 4.8M19.2 19.2l-1.5-1.5M17.7 6.3l1.5-1.5M4.8 19.2l1.5-1.5"/></svg>';
	$moon = '<svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13.4A8.2 8.2 0 0 1 10.6 4a8.4 8.4 0 1 0 9.4 9.4Z"/></svg>';

	$label = morgane_t( array( 'fr' => 'Thème clair / sombre', 'en' => 'Light / dark theme' ) );

	return sprintf(
		'<button class="theme-toggle" type="button" data-theme-toggle aria-label="%s" title="%s">%s%s</button>',
		esc_attr( $label ),
		esc_attr( $label ),
		$sun,
		$moon
	);
}

/**
 * Panier, en haut à droite.
 *
 * Un simple lien plutôt que le bloc « Mini-panier » de WooCommerce : celui-ci
 * ne rend rien hors de ses propres modèles, et un volet coulissant n'apporte
 * rien à une boutique de quelques dizaines d'œuvres. Le nombre d'articles est
 * calculé à chaque affichage — à revoir le jour où un cache de page arrive.
 */
function morgane_render_cart() {
	if ( ! function_exists( 'WC' ) ) {
		return '';
	}

	$count = ( WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	$label = morgane_t( array( 'fr' => 'Panier', 'en' => 'Cart' ) );

	$icon = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.5 8h13l-1.1 11.1a1.6 1.6 0 0 1-1.6 1.4H8.2a1.6 1.6 0 0 1-1.6-1.4Z"/><path d="M9 8V6.4a3 3 0 0 1 6 0V8"/></svg>';

	$badge = $count > 0
		? '<span class="cart-link__badge">' . esc_html( number_format_i18n( $count ) ) . '</span>'
		: '';

	return sprintf(
		'<a class="cart-link" href="%s" aria-label="%s">%s%s</a>',
		esc_url( morgane_wc_page_url( 'cart' ) ),
		esc_attr( $count > 0
			? sprintf( morgane_t( array( 'fr' => 'Panier, %s article(s)', 'en' => 'Cart, %s item(s)' ) ), number_format_i18n( $count ) )
			: $label ),
		$icon,
		$badge
	);
}

/**
 * Grille d'œuvres.
 * Remplace le shortcode [products] : celui-ci passe par wpautop, qui insère
 * des <br>/<p> dans le markup des cartes et casse la mise en page.
 * Filtre aussi par langue courante (Polylang) et, si demandé, par catégorie.
 */
function morgane_render_artworks( $attrs ) {
	if ( ! function_exists( 'wc_get_template_part' ) ) {
		return '';
	}

	$limit    = isset( $attrs['limit'] ) ? absint( $attrs['limit'] ) : 8;
	$category = isset( $attrs['category'] ) ? sanitize_title( $attrs['category'] ) : '';

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	);

	if ( $category ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $category,
			),
		);
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();
		if ( $lang ) {
			$args['lang'] = $lang;
		}
	}

	$query = new WP_Query( $args );
	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	// Le bouton « ajouter au panier » en AJAX a besoin de son script,
	// que WooCommerce ne charge pas hors de ses propres pages.
	wp_enqueue_script( 'wc-add-to-cart' );

	ob_start();
	echo '<div class="woocommerce artworks-grid">';
	echo '<ul class="products">';
	while ( $query->have_posts() ) {
		$query->the_post();
		global $product;
		$product = wc_get_product( get_the_ID() );
		wc_get_template_part( 'content', 'product' );
	}
	echo '</ul>';
	echo '</div>';
	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Contenu de la page 404.
 *
 * Rendu en PHP et non écrit dans le gabarit : un gabarit est un fichier unique,
 * il ne peut pas porter les deux langues. Le reste de la mise en page — le
 * bandeau, les formes floues, le centrage — vient du gabarit `404.html`, qui
 * réemploie les briques du hero. Rien de neuf en CSS.
 *
 * Une 404 doit toujours proposer une sortie : sans cela le visiteur n'a que le
 * bouton « précédent », et une adresse partagée de travers coûte une visite.
 */
function morgane_render_not_found() {
	$out = '<p class="hero__eyebrow">' . esc_html( morgane_t( array(
		'fr' => 'Erreur 404',
		'en' => 'Error 404',
	) ) ) . '</p>';

	$out .= '<h1 class="wp-block-heading">' . esc_html( morgane_t( array(
		'fr' => 'Cette page s\'est égarée',
		'en' => 'This page has wandered off',
	) ) ) . '</h1>';

	$out .= '<p class="hero__lede has-large-font-size">' . esc_html( morgane_t( array(
		'fr' => 'L\'adresse ne mène nulle part — elle a peut-être changé, ou comporte une coquille. Voici deux chemins qui, eux, mènent quelque part.',
		'en' => 'This address leads nowhere — it may have changed, or contain a typo. Here are two that do lead somewhere.',
	) ) ) . '</p>';

	$liens = array(
		array( morgane_home_url(), morgane_t( array( 'fr' => 'Retour à l\'accueil', 'en' => 'Back to home' ) ) ),
		array( morgane_shop_url(), morgane_t( array( 'fr' => 'Voir la boutique',   'en' => 'Browse the shop' ) ) ),
	);

	// Les classes de mise en page sont écrites à la main : ce balisage est produit
	// en PHP, il ne passe pas par le moteur de blocs qui les ajouterait.
	$out .= '<div class="wp-block-buttons is-content-justification-center'
		. ' is-layout-flex wp-container-core-buttons-is-layout-1 wp-block-buttons-is-layout-flex">';
	foreach ( $liens as $i => $lien ) {
		$style = 0 === $i ? '' : ' is-style-outline';
		$out  .= '<div class="wp-block-button' . $style . '">'
			. '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $lien[0] ) . '">'
			. esc_html( $lien[1] ) . '</a></div>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * Les quatre commandes de droite : langue, thème, compte, panier.
 *
 * Sur grand écran elles sont posées à plat. Sur téléphone et tablette, quatre
 * boutons plus le bouton menu ne laissent plus au logo la place de tenir le
 * centre de la barre — et faire descendre le logo sur sa propre rangée abîmait
 * l'en-tête. Elles passent donc derrière un bouton qui les déroule.
 *
 * Ce bouton porte trois points et non trois barres : le menu principal est déjà
 * un hamburger, à gauche. Deux boutons identiques aux deux bouts d'une barre
 * n'annonceraient pas deux choses différentes.
 *
 * Le nombre d'articles du panier remonte sur le déclencheur quand le panneau est
 * fermé : sans ça, une cliente qui a rempli son panier n'en verrait plus la
 * trace tant qu'elle n'ouvre pas le menu.
 */
function morgane_render_tools() {
	$label = morgane_t( array( 'fr' => 'Langue, thème, compte et panier', 'en' => 'Language, theme, account and cart' ) );

	$points = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
		. '<circle cx="5" cy="12" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="19" cy="12" r="1.9"/></svg>';

	$badge = '';
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$n = WC()->cart->get_cart_contents_count();
		if ( $n > 0 ) {
			$badge = '<span class="tools__badge" aria-hidden="true">' . esc_html( number_format_i18n( $n ) ) . '</span>';
		}
	}

	return sprintf(
		'<div class="site-header__tools" data-tools>
			<button class="tools__trigger" type="button" aria-expanded="false" aria-controls="tools-panel" aria-label="%1$s" title="%1$s">%2$s%3$s</button>
			<div class="tools__panel" id="tools-panel">%4$s%5$s%6$s%7$s</div>
		</div>',
		esc_attr( $label ),
		$points,
		$badge,
		morgane_render_lang_switcher(),
		morgane_render_theme_toggle(),
		morgane_render_account(),
		morgane_render_cart()
	);
}

add_action(
	'init',
	function () {
		register_block_type(
			'morgane/artworks',
			array(
				'api_version'     => 3,
				'title'           => __( 'Grille d\'œuvres', 'morgane' ),
				'category'        => 'widgets',
				'attributes'      => array(
					'limit'    => array(
						'type'    => 'number',
						'default' => 8,
					),
					'category' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => 'morgane_render_artworks',
			)
		);

		register_block_type(
			'morgane/menu',
			array(
				'api_version'     => 3,
				'title'           => __( 'Menu dépliant', 'morgane' ),
				'category'        => 'design',
				'render_callback' => 'morgane_render_menu',
			)
		);

		register_block_type(
			'morgane/lang-switcher',
			array(
				'api_version'     => 3,
				'title'           => __( 'Sélecteur de langue', 'morgane' ),
				'category'        => 'design',
				'render_callback' => 'morgane_render_lang_switcher',
			)
		);

		register_block_type(
			'morgane/tools',
			array(
				'api_version'     => 3,
				'title'           => __( 'Commandes de l\'en-tête', 'morgane' ),
				'category'        => 'design',
				'render_callback' => 'morgane_render_tools',
			)
		);

		register_block_type(
			'morgane/account',
			array(
				'api_version'     => 3,
				'title'           => __( 'Compte client', 'morgane' ),
				'category'        => 'woocommerce',
				'render_callback' => 'morgane_render_account',
			)
		);

		register_block_type(
			'morgane/cart',
			array(
				'api_version'     => 3,
				'title'           => __( 'Panier', 'morgane' ),
				'category'        => 'woocommerce',
				'render_callback' => 'morgane_render_cart',
			)
		);

		register_block_type(
			'morgane/not-found',
			array(
				'api_version'     => 3,
				'title'           => __( 'Contenu de la page 404', 'morgane' ),
				'category'        => 'design',
				'render_callback' => 'morgane_render_not_found',
			)
		);

		register_block_type(
			'morgane/theme-toggle',
			array(
				'api_version'     => 3,
				'title'           => __( 'Bascule clair / sombre', 'morgane' ),
				'category'        => 'design',
				'render_callback' => 'morgane_render_theme_toggle',
			)
		);
	}
);

/**
 * Script anti-FOUC : applique le thème enregistré avant le premier rendu,
 * sinon la page clignote en clair puis bascule en sombre.
 */
function morgane_theme_boot_script() {
	?>
<script>
(function(){
	try {
		var s = localStorage.getItem('morgane-theme');
		var d = window.matchMedia('(prefers-color-scheme: dark)').matches;
		document.documentElement.setAttribute('data-theme', s || (d ? 'dark' : 'light'));
	} catch (e) {
		document.documentElement.setAttribute('data-theme', 'light');
	}
})();
</script>
	<?php
}
add_action( 'wp_head', 'morgane_theme_boot_script', 1 );

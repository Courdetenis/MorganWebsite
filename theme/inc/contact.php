<?php
/**
 * Coordonnées et pied de page.
 *
 * Les coordonnées vivent ici, à un seul endroit. Elles servaient jusque-là dans
 * quatre : le gabarit du pied de page, et chacune des pages légales qui doivent
 * les redonner. Une adresse changée en oubliant l'une des quatre, c'est un
 * client qui écrit dans le vide — et sur une page de politique de retour, une
 * adresse morte n'est pas qu'un détail.
 *
 * `scripts/pages.php` s'exécute dans WordPress avec le thème chargé : il lit
 * donc les mêmes valeurs pour composer les pages. Une seule ligne à changer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordonnées de Morgane.
 *
 * Modifiables sans toucher au code par le filtre `morgane_contact_info`.
 *
 * Attention en cas de changement : le pied de page lit ces valeurs à chaque
 * affichage, mais les pages Contact, Conditions de vente, Retours et
 * Confidentialité les ont recopiées dans leur contenu au moment de leur
 * création. Changer l'adresse ici ne suffit donc pas — il faut régénérer ces
 * quatre pages :
 *
 *   MORGANE_FORCE=contact,terms,returns,privacy wp eval-file .../install/pages.php
 */
function morgane_contact_info() {
	return (array) apply_filters( 'morgane_contact_info', array(
		'email'     => 'morganecachia.art@gmail.com',
		'instagram' => 'https://www.instagram.com/morganecachia/',
		'tiktok'    => 'https://www.tiktok.com/@morganecachia',
		'city'      => array( 'fr' => 'Montréal, Québec', 'en' => 'Montreal, Quebec' ),
		'name'      => 'Morgane Cachia',
	) );
}

/**
 * Pied de page : réseaux, courriel, pages légales, mention.
 *
 * Rendu en PHP et non écrit dans le gabarit, pour deux raisons : les URL des
 * pages légales doivent être résolues dans la langue active — un gabarit est un
 * fichier unique et ne peut pas porter les deux — et les coordonnées viennent
 * de morgane_contact_info().
 */
function morgane_render_footer() {
	$c = morgane_contact_info();

	$ig = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>';
	$tt = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M14 3v11.6a3.4 3.4 0 1 1-3.4-3.4c.3 0 .6 0 .9.1"/><path d="M14 3c.3 2.6 2 4.4 4.6 4.7"/></svg>';

	$out = '<div class="site-footer__inner alignwide">';

	$out .= sprintf(
		'<nav class="site-footer__social" aria-label="%s">'
			. '<a href="%s" rel="me noopener" target="_blank" aria-label="Instagram">%s</a>'
			. '<a href="%s" rel="me noopener" target="_blank" aria-label="TikTok">%s</a>'
			. '</nav>',
		esc_attr( morgane_t( array( 'fr' => 'Réseaux sociaux', 'en' => 'Social media' ) ) ),
		esc_url( $c['instagram'] ),
		$ig,
		esc_url( $c['tiktok'] ),
		$tt
	);

	$out .= '<a class="site-footer__mail" href="' . esc_url( 'mailto:' . $c['email'] ) . '">'
		. esc_html( $c['email'] ) . '</a>';

	// Les pages légales : discrètes, mais toujours atteignables. Leur place est
	// ici et non dans le menu, qui tient volontairement à cinq entrées.
	// Libellés courts : les quatre doivent tenir sur une ligne dans la colonne
	// de droite, qui ne fait que 470 px sur le plus large des écrans. Le titre
	// complet est sur la page.
	$legales = array(
		array( 'contact',                       array( 'fr' => 'Contact',             'en' => 'Contact' ) ),
		array( 'conditions-generales-de-vente', array( 'fr' => 'Conditions de vente', 'en' => 'Terms of sale' ) ),
		array( 'retours-et-remboursements',     array( 'fr' => 'Retours',             'en' => 'Returns' ) ),
		array( 'confidentialite',               array( 'fr' => 'Confidentialité',     'en' => 'Privacy' ) ),
	);

	// Séparés par une espace : c'est là que la ligne peut se couper (voir
	// `.site-footer__links` dans app.css).
	$liens = array();
	foreach ( $legales as $page ) {
		$liens[] = '<a href="' . esc_url( morgane_url( $page[0] ) ) . '">'
			. esc_html( morgane_t( $page[1] ) ) . '</a>';
	}
	$liens = implode( ' ', $liens );

	$out .= '<div class="site-footer__legal">'
		. '<nav class="site-footer__links" aria-label="' . esc_attr( morgane_t( array(
			'fr' => 'Informations légales',
			'en' => 'Legal information',
		) ) ) . '">' . $liens . '</nav>'
		. '<p class="site-footer__copy">© ' . esc_html( $c['name'] ) . ' – Art · '
		. esc_html( morgane_t( $c['city'] ) ) . '</p>'
		. '</div>';

	$out .= '</div>';

	return $out;
}

add_action( 'init', function () {
	register_block_type(
		'morgane/footer',
		array(
			'api_version'     => 3,
			'title'           => __( 'Pied de page', 'morgane' ),
			'category'        => 'design',
			'render_callback' => 'morgane_render_footer',
		)
	);
} );

<?php
/**
 * Génère le contenu des pages du thème Shopify à partir de theme/install/pages.php.
 *
 * Les textes restent écrits à un seul endroit — pages.php, comme pour WordPress.
 * Ce script en exécute les fonctions de contenu hors de WordPress (quelques
 * fonctions WP sont simulées plus bas) et écrit, pour chaque page, un snippet
 * Liquid qui porte ses deux langues :
 *
 *   shopify-theme/snippets/page-<clé>.liquid
 *   shopify-theme/assets/art-NN.webp        les photos, en WebP 1600 px
 *
 * Il recrée au passage ce que le moteur de blocs de WordPress ajoutait au rendu
 * (classes de mise en page), pour qu'app.css retrouve exactement le même balisage.
 *
 * Lancer, avec n'importe quel PHP 8 (extensions gd et exif) :
 *   php -d extension=gd -d extension=exif scripts/shopify-pages.php
 *
 * @package Morgane
 */

$root  = dirname( __DIR__ );
$theme = $root . '/shopify-theme';

/* -------------------------------------------------------------------------
   1. Photos : JPEG d'origine → assets/art-NN.webp
   ------------------------------------------------------------------------- */

const MG_MAX_SIDE = 1600;
const MG_QUALITY  = 82;

$dims = array();
foreach ( scandir( $root . '/pictures' ) as $file ) {
	if ( ! preg_match( '/^(\d+)\s*-.*\.(jpe?g|png)$/i', $file, $m ) ) {
		continue;
	}
	$n    = (int) $m[1];
	$src  = $root . '/pictures/' . $file;
	$dest = sprintf( '%s/assets/art-%02d.webp', $theme, $n );

	if ( ! is_file( $dest ) || filemtime( $dest ) < filemtime( $src ) ) {
		$img = imagecreatefromstring( file_get_contents( $src ) );
		// Les photos d'appareil sont souvent stockées couchées, avec l'orientation
		// en EXIF. WordPress la corrige à l'import ; on fait de même.
		$exif = @exif_read_data( $src );
		switch ( $exif['Orientation'] ?? 1 ) {
			case 3: $img = imagerotate( $img, 180, 0 ); break;
			case 6: $img = imagerotate( $img, -90, 0 ); break;
			case 8: $img = imagerotate( $img, 90, 0 ); break;
		}
		$w = imagesx( $img );
		$h = imagesy( $img );
		$k = min( 1, MG_MAX_SIDE / max( $w, $h ) );
		if ( $k < 1 ) {
			$img = imagescale( $img, (int) round( $w * $k ), (int) round( $h * $k ), IMG_BICUBIC );
		}
		imagewebp( $img, $dest, MG_QUALITY );
		echo "Photo $n → " . basename( $dest ) . "\n";
	}
	$size       = getimagesize( $dest );
	$dims[ $n ] = array( $size[0], $size[1] );
}

/* -------------------------------------------------------------------------
   2. WordPress simulé, juste ce que les fonctions de contenu appellent
   ------------------------------------------------------------------------- */

define( 'ABSPATH', __DIR__ );

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function apply_filters( $hook, $value ) { return $value; }
function add_action() {}
function register_block_type() {}
function home_url() { return 'https://morganecachia.art/'; }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }

// La table « numéro de photo → attachement » : ici, le numéro lui-même.
function get_option( $name, $default = false ) {
	return 'morgane_media' === $name ? array_combine( range( 1, 99 ), range( 1, 99 ) ) : $default;
}

function wp_get_attachment_image_src( $n ) {
	global $dims;
	if ( ! isset( $dims[ $n ] ) ) {
		return false;
	}
	return array( mg_asset( $n ), $dims[ $n ][0], $dims[ $n ][1] );
}

function wp_get_attachment_image( $n, $size, $icon, $attr ) {
	$src = wp_get_attachment_image_src( $n );
	if ( ! $src ) {
		return '';
	}
	// Une seule taille de fichier : `sizes` n'a plus de srcset où choisir.
	unset( $attr['sizes'] );
	$out = '<img src="' . $src[0] . '" width="' . $src[1] . '" height="' . $src[2] . '"';
	foreach ( $attr as $k => $v ) {
		$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return $out . '>';
}

// L'URL d'une photo, résolue par Shopify au rendu.
function mg_asset( $n ) {
	return sprintf( "{{ 'art-%02d.webp' | asset_url }}", $n );
}

require $root . '/theme/inc/contact.php';

// Les fonctions de contenu de pages.php : de la section 3 à la section 4.
$code  = file_get_contents( $root . '/theme/install/pages.php' );
$start = strpos( $code, "/* =========================================================================\n   3. Contenus" );
$end   = strpos( $code, "/* =========================================================================\n   4. Passe 2" );
if ( false === $start || false === $end ) {
	fwrite( STDERR, "Repères « 3. Contenus » / « 4. Passe 2 » introuvables dans pages.php\n" );
	exit( 1 );
}
eval( substr( $code, $start, $end - $start ) );

/* -------------------------------------------------------------------------
   3. Adresses : les {{clés}} de pages.php → chemins Shopify
   ------------------------------------------------------------------------- */

/*
 * Handles Shopify, une seule fois pour les deux langues : Shopify range toutes
 * les pages au même niveau, d'où les préfixes là où WordPress avait un parent
 * (deux « murales », deux « illustration »…). Le thème retrouve la page par ce
 * handle (sections/main-page.liquid) — les changer ici, c'est les changer là.
 */
$handles = array(
	'home'           => '',
	'murals'         => 'murales',
	'portfolio'      => 'portfolio',
	'pf_youth'       => 'illustration-jeunesse',
	'pf_illus'       => 'illustration',
	'pf_draw'        => 'dessin',
	'pf_murals'      => 'portfolio-murales',
	'about'          => 'a-propos',
	'hub'            => 'commissions-et-collaborations',
	'commissions'    => 'commissions',
	'collaborations' => 'collaborations',
	'contact'        => 'contact',
	'terms'          => 'conditions-generales-de-vente',
	'returns'        => 'retours-et-remboursements',
	'privacy'        => 'confidentialite',
);

// `r` vaut « / » ou « /en/ » : posé en tête de chaque snippet.
// Une page passe par `pages[…].url`, qui suit le handle traduit dans Translate & Adapt.
$urls = array( '{{shop}}' => '{{ r }}collections' );
foreach ( $handles as $key => $handle ) {
	$urls[ '{{' . $key . '}}' ] = '' === $handle ? '{{ r }}' : "{{ pages['" . $handle . "'].url }}";
}

/* -------------------------------------------------------------------------
   4. Rendu : ce que do_blocks() ajoutait au balisage enregistré
   ------------------------------------------------------------------------- */

function mg_render( $html ) {
	// Les deux blocs dynamiques du thème deviennent des snippets.
	$html = preg_replace( '#<!-- wp:morgane/form \{"type":"([a-z]+)"\} /-->#', "{% render 'request-form', type: '$1' %}", $html );
	$html = preg_replace( '#<!-- wp:morgane/artworks \{"limit":(\d+)\} /-->#', "{% render 'artworks-grid', limit: $1 %}", $html );

	// Classes de mise en page, posées sur la balise qui suit le commentaire.
	$html = preg_replace_callback(
		'#<!-- wp:([a-z/-]+)( \{.*?\})? -->(\s*)<([a-z0-9]+)([^>]*)>#s',
		function ( $m ) {
			$attrs = isset( $m[2] ) && '' !== trim( $m[2] ) ? json_decode( trim( $m[2] ), true ) : array();
			$add   = array();
			switch ( $m[1] ) {
				case 'group':
					if ( 'constrained' === ( $attrs['layout']['type'] ?? '' ) ) {
						$add[] = 'has-global-padding is-layout-constrained wp-block-group-is-layout-constrained';
						if ( ! empty( $attrs['layout']['contentSize'] ) ) {
							$add[] = 'mg-cs-' . (int) $attrs['layout']['contentSize'];
						}
					} else {
						$add[] = 'is-layout-flow wp-block-group-is-layout-flow';
					}
					break;
				case 'buttons':
					$add[] = 'is-layout-flex wp-block-buttons-is-layout-flex';
					break;
				case 'gallery':
					$add[] = 'is-layout-flex wp-block-gallery-is-layout-flex';
					break;
			}
			$tag = $m[5];
			if ( $add ) {
				$tag = preg_match( '/class="/', $tag )
					? preg_replace( '/class="([^"]*)"/', 'class="$1 ' . implode( ' ', $add ) . '"', $tag, 1 )
					: ' class="' . implode( ' ', $add ) . '"' . $tag;
			}
			return $m[3] . '<' . $m[4] . $tag . '>';
		},
		$html
	);

	// Le reste des commentaires de blocs ne sert qu'à l'éditeur.
	$html = preg_replace( '#\s*<!-- /?wp:[^>]*?-->#', '', $html );
	return trim( $html );
}

/* -------------------------------------------------------------------------
   5. Écriture
   ------------------------------------------------------------------------- */

foreach ( array_keys( $handles ) as $key ) {
	$out = array();
	foreach ( array( 'fr', 'en' ) as $lang ) {
		$html = strtr( mg_content( $key, $lang ), $urls );
		// Le domaine de la boutique, lu par Shopify et non figé ici.
		$html = str_replace( 'morganecachia.art,', "{{ shop.domain | remove_first: 'www.' }},", $html );
		$out[ $lang ] = mg_render( $html );
	}

	$snippet = "{%- comment -%}\n"
		. "  Généré par scripts/shopify-pages.php depuis theme/install/pages.php (clé « $key »).\n"
		. "  Ne pas modifier ici : changer le texte dans pages.php, puis relancer le script.\n"
		. "{%- endcomment -%}\n"
		. ( false !== strpos( $out['fr'] . $out['en'], '{{ r }}' )
			? "{%- assign r = routes.root_url | append: '/' | replace: '//', '/' -%}\n"
			: '' )
		. "{%- if request.locale.iso_code == 'en' -%}\n" . $out['en'] . "\n"
		. "{%- else -%}\n" . $out['fr'] . "\n"
		. "{%- endif -%}\n";

	file_put_contents( "$theme/snippets/page-" . str_replace( '_', '-', $key ) . '.liquid', $snippet );
	echo "page-$key.liquid\n";
}

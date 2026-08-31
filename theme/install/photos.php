<?php
/**
 * Table des photos : numéro de fichier → nom de fichier, titre, texte alternatif.
 *
 * Sortie de media.php pour être lisible par deux appelants : le script d'import
 * lui-même, et l'installateur de wp-admin, qui a besoin de la liste pour
 * découper le travail en une requête par photo — un hébergement mutualisé coupe
 * PHP bien avant la vingt-troisième.
 *
 * Le numéro vient du nom du fichier d'origine (« 3 - site internet.jpg » → 3).
 * `pages.php` ne connaît que ces numéros et ne voit jamais une URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	// Photo 1 : plus posée dans aucune page (le bandeau d'accueil a été retiré),
	// mais gardée en médiathèque — c'est un portrait utilisable ailleurs.
	1  => array(
		'slug'  => 'morgane-cachia-artiste-peintre-oeuvre-encadree',
		'title' => 'Morgane Cachia avec une de ses peintures',
		'alt'   => 'Morgane Cachia tenant une de ses peintures à la gouache encadrée, une fée dans un jardin de roses',
	),
	2  => array(
		'slug'  => 'tirages-illustrations-gouache-morgane-cachia',
		'title' => 'Tirages d’art',
		'alt'   => 'Ensemble de tirages d’art des illustrations à la gouache de Morgane Cachia',
	),
	3  => array(
		'slug'  => 'peinture-personnalisee-epouvantail-tournesols',
		'title' => 'Épouvantail dans les tournesols — peinture sur commande',
		'alt'   => 'Peinture d’un épouvantail coiffé d’un chapeau fleuri dans un champ de tournesols, réalisée sur commande',
	),
	4  => array(
		'slug'  => 'morgane-cachia-muraliste-montreal',
		'title' => 'Morgane Cachia devant une de ses murales',
		'alt'   => 'Morgane Cachia devant sa murale : un enfant en imperméable jaune qui creuse la terre',
	),
	5  => array(
		'slug'  => 'morgane-cachia-illustratrice-montreal',
		'title' => 'Portrait de Morgane Cachia',
		'alt'   => 'Morgane Cachia, illustratrice et artiste peintre à Montréal, assise dans l’herbe avec une de ses peintures',
	),
	6  => array(
		'slug'  => 'illustration-gouache-lune-foret-hiver',
		'title' => 'La lune dans la forêt d’hiver',
		'alt'   => 'Illustration à la gouache : une femme aux longs cheveux serre une lune lumineuse dans une forêt d’hiver bleutée',
	),
	7  => array(
		'slug'  => 'illustration-gouache-miroir-foret-enchantee',
		'title' => 'Le miroir de la forêt',
		'alt'   => 'Illustration à la gouache : un miroir ancien au sommet d’un escalier de pierre, dans une forêt enchantée',
	),
	8  => array(
		'slug'  => 'illustration-gouache-bibliotheque-enchantee',
		'title' => 'La bibliothèque enchantée',
		'alt'   => 'Illustration à la gouache : une femme aux cheveux dorés flottants dans une bibliothèque envahie de lierre',
	),
	9  => array(
		'slug'  => 'illustration-gouache-fee-pivoine',
		'title' => 'La fée de la pivoine',
		'alt'   => 'Illustration à la gouache : une fée assise au cœur d’une pivoine rose immense',
	),
	10 => array(
		'slug'  => 'illustration-gouache-fee-jardin-lierre',
		'title' => 'La fée au jardin',
		'alt'   => 'Illustration à la gouache : une fée éclaire une fleur de lierre au creux de sa main, dans un jardin de roses',
	),
	11 => array(
		'slug'  => 'illustration-jeunesse-faon-chiot-neige',
		'title' => 'Le faon et le chiot',
		'alt'   => 'Illustration jeunesse : un faon et un chiot se rencontrent dans une forêt enneigée aux teintes violettes',
	),
	12 => array(
		'slug'  => 'illustration-jeunesse-souris-arrosoir',
		'title' => 'La souris et les primevères',
		'alt'   => 'Illustration jeunesse : une souris arrose des primevères devant sa maison creusée dans un arbre',
	),
	13 => array(
		'slug'  => 'illustration-jeunesse-pique-nique-ourson',
		'title' => 'Le pique-nique',
		'alt'   => 'Illustration jeunesse : une petite fille en robe rose serre son ourson pendant un pique-nique',
	),
	14 => array(
		'slug'  => 'illustration-jeunesse-ourson-reverbere-neige',
		'title' => 'L’allumeur de réverbères',
		'alt'   => 'Illustration jeunesse : un ourson grimpe à une échelle pour allumer un réverbère dans une ville enneigée',
	),
	15 => array(
		'slug'  => 'illustration-jeunesse-fillette-oie-jardin',
		'title' => 'La fillette et l’oie',
		'alt'   => 'Illustration jeunesse : une fillette plante une pousse dans un jardin de roses, observée par une oie blanche',
	),
	16 => array(
		'slug'  => 'dessin-encre-vieil-homme-ecrivain',
		'title' => 'L’écrivain',
		'alt'   => 'Dessin à l’encre : un vieil homme barbu écrit à la plume dans une bibliothèque sombre',
	),
	17 => array(
		'slug'  => 'dessin-encre-montagne-cascade',
		'title' => 'La cascade',
		'alt'   => 'Dessin à l’encre : une cascade dévale une paroi de montagne sous des nuages en volutes',
	),
	18 => array(
		'slug'  => 'dessin-encre-fillette-pissenlit',
		'title' => 'Le pissenlit',
		'alt'   => 'Dessin à l’encre : une fillette en bonnet souffle sur un pissenlit',
	),
	19 => array(
		'slug'  => 'dessin-encre-nature-morte-violoncelle',
		'title' => 'Nature morte au violoncelle',
		'alt'   => 'Dessin à l’encre : nature morte au violoncelle, partition et bouquet posés sur une table drapée',
	),
	20 => array(
		'slug'  => 'murale-enfant-impermeable-bleu',
		'title' => 'Murale — l’enfant à la fleur',
		'alt'   => 'Murale : un enfant en imperméable bleu respire une fleur d’où s’échappent des lettres',
	),
	21 => array(
		'slug'  => 'murale-enfant-impermeable-jaune',
		'title' => 'Murale — la graine lumineuse',
		'alt'   => 'Murale : un enfant en imperméable jaune déterre une graine lumineuse',
	),
	22 => array(
		'slug'  => 'murale-arbre-du-savoir',
		'title' => 'Murale « L’arbre du savoir » (2024)',
		'alt'   => 'Murale « L’arbre du savoir » : un arbre en fleurs dont les branches portent des lettres',
	),
	23 => array(
		'slug'  => 'illustration-gouache-atelier-alchimiste',
		'title' => 'L’atelier de l’alchimiste',
		'alt'   => 'Illustration à la gouache : une table d’alchimiste éclairée à la bougie, fioles fumantes et vieux livres',
	),
);

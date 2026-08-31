<?php
/**
 * Cale WP-CLI, pour faire tourner les scripts d'installation hors ligne de commande.
 *
 * Les sept scripts de `install/` ne parlent à WP-CLI que pour écrire dans le
 * journal : `log`, `success`, `warning`, `error`. Aucun n'appelle de commande.
 * Il suffit donc d'une classe du même nom pour qu'ils s'exécutent depuis
 * wp-admin sans qu'une seule ligne soit réécrite — et ils restent utilisables
 * tels quels en SSH le jour où l'hébergement en offre un.
 *
 * `error()` doit interrompre, comme en ligne de commande : elle lève une
 * exception que le lanceur rattrape et affiche.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {

	class WP_CLI {

		/** @var array<int,array{0:string,1:string}> */
		public static $lignes = array();

		public static function log( $message ) {
			self::$lignes[] = array( 'log', (string) $message );
		}

		public static function success( $message ) {
			self::$lignes[] = array( 'success', (string) $message );
		}

		public static function warning( $message ) {
			self::$lignes[] = array( 'warning', (string) $message );
		}

		public static function error( $message, $exit = true ) {
			throw new RuntimeException( (string) $message );
		}

		public static function colorize( $message ) {
			return $message;
		}

		public static function line( $message = '' ) {
			self::log( $message );
		}
	}
}

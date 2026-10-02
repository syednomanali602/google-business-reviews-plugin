<?php
/**
 * Local review cache. Each sync merges new reviews into what is already stored,
 * so the collection keeps growing past what a single API call returns.
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Store {

	const MAX_REVIEWS = 300;

	public static function get() {
		$data = get_option( D360_GBR_DATA, array() );

		return wp_parse_args(
			is_array( $data ) ? $data : array(),
			array(
				'place'      => array(),
				'reviews'    => array(),
				'synced_at'  => 0,
				'last_error' => '',
			)
		);
	}

	public static function save_sync( array $result ) {
		$data = self::get();
		$byid = array();

		foreach ( $data['reviews'] as $r ) {
			$byid[ $r['id'] ] = $r;
		}
		foreach ( $result['reviews'] as $r ) {
			$byid[ $r['id'] ] = $r; // Fresh copy wins (edited reviews, new avatars).
		}

		$all = array_values( $byid );
		usort(
			$all,
			static function ( $a, $b ) {
				return (int) $b['time'] <=> (int) $a['time'];
			}
		);

		$data['reviews']    = array_slice( $all, 0, self::MAX_REVIEWS );
		$data['place']      = array_merge( (array) $data['place'], array_filter( (array) $result['place'] ) );
		$data['synced_at']  = time();
		$data['last_error'] = '';

		update_option( D360_GBR_DATA, $data, false );

		return count( $data['reviews'] );
	}

	public static function set_error( $message ) {
		$data               = self::get();
		$data['last_error'] = (string) $message;
		update_option( D360_GBR_DATA, $data, false );
	}

	public static function reset() {
		delete_option( D360_GBR_DATA );
	}
}

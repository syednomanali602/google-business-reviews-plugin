<?php
/**
 * Outscraper provider — Google Maps search + Google Maps reviews.
 * Docs: https://app.outscraper.com/api-docs
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Provider_Outscraper implements D360_GBR_Provider {

	const BASE           = 'https://api.app.outscraper.com';
	const BALANCE_PATH   = '/profile/balance';

	/** @var string */
	private $key;

	public function __construct( $key ) {
		$this->key = trim( (string) $key );
	}

	/**
	 * @return array|WP_Error Flattened list of place objects.
	 */
	private function request( $path, array $args ) {
		if ( '' === $this->key ) {
			return new WP_Error( 'd360_gbr_key', __( 'Outscraper API key is missing. Add it under Settings.', 'd360-gbr' ) );
		}

		$args['async'] = 'false';

		$res = wp_remote_get(
			self::BASE . $path . '?' . http_build_query( $args ),
			array(
				'timeout' => 60,
				'headers' => array( 'X-API-KEY' => $this->key ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code >= 400 ) {
			$msg = is_array( $body ) && ! empty( $body['errorMessage'] ) ? $body['errorMessage'] : 'HTTP ' . $code;
			return new WP_Error( 'd360_gbr_api', 'Outscraper: ' . $msg );
		}
		if ( ! is_array( $body ) || ! isset( $body['data'] ) ) {
			return new WP_Error( 'd360_gbr_bad_response', __( 'Outscraper returned an unreadable response.', 'd360-gbr' ) );
		}

		return self::flatten( $body['data'] );
	}

	/** Search returns [[place, place]] per query; reviews returns [place]. Normalize both. */
	private static function flatten( $data ) {
		$out = array();
		foreach ( (array) $data as $item ) {
			if ( is_array( $item ) && isset( $item[0] ) && is_array( $item[0] ) ) {
				$out = array_merge( $out, $item );
			} elseif ( is_array( $item ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Outscraper's free allowance is a one-time signup credit pool, not a monthly quota —
	 * so this reports remaining balance only; 'limit' and 'renews' are left empty.
	 */
	public function quota() {
		if ( '' === $this->key ) {
			return new WP_Error( 'd360_gbr_key', __( 'Outscraper API key is missing. Add it under Settings.', 'd360-gbr' ) );
		}

		$res = wp_remote_get(
			self::BASE . self::BALANCE_PATH,
			array(
				'timeout' => 15,
				'headers' => array( 'X-API-KEY' => $this->key ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code >= 400 || ! is_array( $body ) ) {
			return new WP_Error( 'd360_gbr_api', __( 'Outscraper did not return a balance.', 'd360-gbr' ) );
		}

		// Field name isn't fully documented publicly; cover the variants seen in the wild.
		$remaining = $body['balance'] ?? ( $body['credits'] ?? ( $body['remaining_credits'] ?? null ) );

		if ( null === $remaining ) {
			return null; // Unrecognized shape — admin UI falls back to "not available" rather than showing a wrong number.
		}

		return array(
			'used'      => 0,
			'remaining' => (int) $remaining,
			'limit'     => 0, // Credit pool, not a monthly cap.
			'plan'      => (string) ( $body['plan'] ?? '' ),
			'renews'    => '',
		);
	}

	public function search( $query, $lang ) {
		$rows = $this->request(
			'/maps/search-v3',
			array(
				'query'    => $query,
				'limit'    => 10,
				'language' => $lang,
			)
		);

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$out = array();
		foreach ( $rows as $r ) {
			if ( empty( $r['place_id'] ) && empty( $r['google_id'] ) ) {
				continue;
			}
			$out[] = array(
				'name'     => (string) ( $r['name'] ?? '' ),
				'address'  => (string) ( $r['full_address'] ?? '' ),
				'place_id' => (string) ( $r['place_id'] ?? '' ),
				'data_id'  => (string) ( $r['google_id'] ?? '' ),
				'rating'   => (float) ( $r['rating'] ?? 0 ),
				'total'    => (int) ( $r['reviews'] ?? 0 ),
			);
		}

		return $out;
	}

	public function fetch_reviews( array $place, $pages, $lang ) {
		$query = $place['place_id'] ?: ( $place['data_id'] ?: ( $place['name'] ?? '' ) );

		if ( '' === $query ) {
			return new WP_Error( 'd360_gbr_no_place', __( 'No business selected.', 'd360-gbr' ) );
		}

		$rows = $this->request(
			'/maps/reviews-v3',
			array(
				'query'        => $query,
				'reviewsLimit' => max( 1, (int) $pages ) * 20,
				'sort'         => 'newest',
				'language'     => $lang,
			)
		);

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( empty( $rows[0] ) ) {
			return new WP_Error( 'd360_gbr_not_found', __( 'Outscraper could not find that business.', 'd360-gbr' ) );
		}

		$p       = $rows[0];
		$reviews = array();

		foreach ( (array) ( $p['reviews_data'] ?? array() ) as $r ) {
			$text = (string) ( $r['review_text'] ?? '' );
			$time = ! empty( $r['review_timestamp'] ) ? (int) $r['review_timestamp'] : ( ! empty( $r['review_datetime_utc'] ) ? (int) strtotime( $r['review_datetime_utc'] . ' UTC' ) : 0 );

			$reviews[] = array(
				'id'         => (string) ( $r['review_id'] ?? md5( ( $r['author_title'] ?? '' ) . '|' . $time . '|' . $text ) ),
				'author'     => (string) ( $r['author_title'] ?? '' ),
				'author_url' => (string) ( $r['author_link'] ?? '' ),
				'avatar'     => (string) ( $r['author_image'] ?? '' ),
				'rating'     => (int) ( $r['review_rating'] ?? 0 ),
				'text'       => $text,
				'time'       => $time,
				'date_label' => '',
			);
		}

		return array(
			'place'   => array(
				'name'    => (string) ( $p['name'] ?? '' ),
				'rating'  => (float) ( $p['rating'] ?? 0 ),
				'total'   => (int) ( $p['reviews'] ?? 0 ),
				'address' => (string) ( $p['full_address'] ?? '' ),
			),
			'reviews' => $reviews,
		);
	}
}

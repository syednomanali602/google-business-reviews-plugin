<?php
/**
 * SerpApi provider — Google Maps search + Google Maps Reviews engines.
 * Docs: https://serpapi.com/google-maps-api and https://serpapi.com/google-maps-reviews-api
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Provider_SerpApi implements D360_GBR_Provider {

	const ENDPOINT = 'https://serpapi.com/search.json';
	const ACCOUNT_ENDPOINT = 'https://serpapi.com/account.json';

	/** @var string */
	private $key;

	public function __construct( $key ) {
		$this->key = trim( (string) $key );
	}

	/**
	 * @return array|WP_Error Decoded body.
	 */
	private function request( array $args ) {
		if ( '' === $this->key ) {
			return new WP_Error( 'd360_gbr_key', __( 'SerpApi key is missing. Add it under Settings.', 'd360-gbr' ) );
		}

		$args['api_key'] = $this->key;
		$res             = wp_remote_get( self::ENDPOINT . '?' . http_build_query( $args ), array( 'timeout' => 30 ) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'd360_gbr_bad_response', __( 'SerpApi returned an unreadable response.', 'd360-gbr' ) );
		}
		if ( ! empty( $body['error'] ) ) {
			return new WP_Error( 'd360_gbr_api', 'SerpApi: ' . $body['error'] );
		}

		return $body;
	}

	public function search( $query, $lang ) {
		$body = $this->request(
			array(
				'engine' => 'google_maps',
				'type'   => 'search',
				'q'      => $query,
				'hl'     => $lang,
			)
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		// An exact match comes back as place_results, otherwise a list in local_results.
		$rows = array();
		if ( ! empty( $body['place_results'] ) ) {
			$rows[] = $body['place_results'];
		}
		if ( ! empty( $body['local_results'] ) && is_array( $body['local_results'] ) ) {
			$rows = array_merge( $rows, $body['local_results'] );
		}

		$out = array();
		foreach ( array_slice( $rows, 0, 10 ) as $r ) {
			if ( empty( $r['data_id'] ) && empty( $r['place_id'] ) ) {
				continue;
			}
			$out[] = array(
				'name'     => (string) ( $r['title'] ?? '' ),
				'address'  => (string) ( $r['address'] ?? '' ),
				'place_id' => (string) ( $r['place_id'] ?? '' ),
				'data_id'  => (string) ( $r['data_id'] ?? '' ),
				'rating'   => (float) ( $r['rating'] ?? 0 ),
				'total'    => (int) ( $r['reviews'] ?? 0 ),
			);
		}

		return $out;
	}

	public function fetch_reviews( array $place, $pages, $lang ) {
		$args = array(
			'engine'  => 'google_maps_reviews',
			'hl'      => $lang,
			'sort_by' => 'newestFirst',
		);

		if ( ! empty( $place['data_id'] ) ) {
			$args['data_id'] = $place['data_id'];
		} elseif ( ! empty( $place['place_id'] ) ) {
			$args['place_id'] = $place['place_id'];
		} else {
			return new WP_Error( 'd360_gbr_no_place', __( 'No business selected.', 'd360-gbr' ) );
		}

		$info    = array();
		$reviews = array();
		$token   = '';
		$pages   = max( 1, (int) $pages );

		for ( $i = 0; $i < $pages; $i++ ) {
			$q = $args;
			if ( $token ) {
				$q['next_page_token'] = $token;
				$q['num']             = 20; // Only allowed after the first page.
			}

			$body = $this->request( $q );

			if ( is_wp_error( $body ) ) {
				if ( 0 === $i ) {
					return $body;
				}
				break; // Keep what we already have.
			}

			if ( 0 === $i && ! empty( $body['place_info'] ) ) {
				$info = $body['place_info'];
			}

			foreach ( (array) ( $body['reviews'] ?? array() ) as $r ) {
				$reviews[] = $this->normalize( $r );
			}

			$token = $body['serpapi_pagination']['next_page_token'] ?? '';
			if ( ! $token ) {
				break;
			}
		}

		return array(
			'place'   => array(
				'name'    => (string) ( $info['title'] ?? ( $place['name'] ?? '' ) ),
				'rating'  => (float) ( $info['rating'] ?? 0 ),
				'total'   => (int) ( $info['reviews'] ?? 0 ),
				'address' => (string) ( $info['address'] ?? '' ),
			),
			'reviews' => $reviews,
		);
	}

	/**
	 * Free of charge per SerpApi's docs — does not use a search credit.
	 * https://serpapi.com/account-api
	 */
	public function quota() {
		if ( '' === $this->key ) {
			return new WP_Error( 'd360_gbr_key', __( 'SerpApi key is missing. Add it under Settings.', 'd360-gbr' ) );
		}

		$res = wp_remote_get( self::ACCOUNT_ENDPOINT . '?' . http_build_query( array( 'api_key' => $this->key ) ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'd360_gbr_bad_response', __( 'SerpApi returned an unreadable response.', 'd360-gbr' ) );
		}
		if ( ! empty( $body['error'] ) ) {
			return new WP_Error( 'd360_gbr_api', 'SerpApi: ' . $body['error'] );
		}

		return array(
			'used'      => (int) ( $body['this_month_usage'] ?? 0 ),
			'remaining' => (int) ( $body['total_searches_left'] ?? 0 ),
			'limit'     => (int) ( $body['searches_per_month'] ?? 0 ),
			'plan'      => (string) ( $body['plan_name'] ?? '' ),
			'renews'    => (string) ( $body['plan_renewal_date'] ?? '' ),
		);
	}

	private function normalize( array $r ) {
		$user = isset( $r['user'] ) && is_array( $r['user'] ) ? $r['user'] : array();
		$text = $r['extracted_snippet']['original'] ?? ( $r['snippet'] ?? '' );
		$time = ! empty( $r['iso_date'] ) ? (int) strtotime( $r['iso_date'] ) : 0;
		$id   = ! empty( $r['review_id'] ) ? $r['review_id'] : md5( ( $user['name'] ?? '' ) . '|' . $time . '|' . $text );

		return array(
			'id'         => (string) $id,
			'author'     => (string) ( $user['name'] ?? '' ),
			'author_url' => (string) ( $user['link'] ?? '' ),
			'avatar'     => (string) ( $user['thumbnail'] ?? '' ),
			'rating'     => (int) round( (float) ( $r['rating'] ?? 0 ) ),
			'text'       => (string) $text,
			'time'       => $time,
			'date_label' => (string) ( $r['date'] ?? '' ),
		);
	}
}

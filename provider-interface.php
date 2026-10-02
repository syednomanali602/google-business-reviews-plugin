<?php
/**
 * Contract every review source implements.
 *
 * Normalized review shape:
 * [
 *   'id'         => string,
 *   'author'     => string,
 *   'author_url' => string,
 *   'avatar'     => string,
 *   'rating'     => int (1–5),
 *   'text'       => string,
 *   'time'       => int (unix timestamp, 0 if unknown),
 *   'date_label' => string (provider's relative label, used only when time is 0),
 * ]
 */

defined( 'ABSPATH' ) || exit;

interface D360_GBR_Provider {

	/**
	 * Find businesses on Google Maps by name.
	 *
	 * @param string $query Business name, ideally with the city.
	 * @param string $lang  Language code.
	 * @return array|WP_Error List of [name, address, place_id, data_id, rating, total].
	 */
	public function search( $query, $lang );

	/**
	 * Fetch the newest reviews for a business.
	 *
	 * @param array  $place [place_id, data_id, name].
	 * @param int    $pages Number of review pages to pull.
	 * @param string $lang  Language code.
	 * @return array|WP_Error [ 'place' => [name, rating, total, address], 'reviews' => [ normalized... ] ].
	 */
	public function fetch_reviews( array $place, $pages, $lang );

	/**
	 * Remaining quota on the connected account, if the provider exposes one.
	 *
	 * @return array|WP_Error|null [
	 *   'used'       => int,
	 *   'remaining'  => int,
	 *   'limit'      => int,       // 0 when the provider has no fixed monthly cap (credit-pool accounts).
	 *   'plan'       => string,
	 *   'renews'     => string,    // Y-m-d, '' if unknown or not applicable.
	 * ]. Return null if this provider has no usage endpoint.
	 */
	public function quota();
}

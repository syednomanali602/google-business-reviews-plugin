<?php
/**
 * Scheduled + manual syncing.
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Sync {

	public static function init() {
		add_action( D360_GBR_CRON, array( __CLASS__, 'run' ) );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['d360_gbr_twice_weekly'] = array(
			'interval' => (int) ( 3.5 * DAY_IN_SECONDS ),
			'display'  => __( 'Twice weekly', 'd360-gbr' ),
		);
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once weekly', 'd360-gbr' ),
			);
		}
		return $schedules;
	}

	public static function activate() {
		self::reschedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( D360_GBR_CRON );
	}

	public static function reschedule() {
		wp_clear_scheduled_hook( D360_GBR_CRON );

		$s = d360_gbr_settings();
		if ( 'manual' === $s['frequency'] ) {
			return;
		}

		$map = array(
			'daily'        => 'daily',
			'twice_weekly' => 'd360_gbr_twice_weekly',
			'weekly'       => 'weekly',
		);

		wp_schedule_event( time() + HOUR_IN_SECONDS, $map[ $s['frequency'] ] ?? 'weekly', D360_GBR_CRON );
	}

	/**
	 * @return D360_GBR_Provider
	 */
	public static function provider( array $s = array() ) {
		$s = $s ? $s : d360_gbr_settings();

		if ( 'outscraper' === $s['provider'] ) {
			return new D360_GBR_Provider_Outscraper( $s['outscraper_key'] );
		}
		return new D360_GBR_Provider_SerpApi( $s['serpapi_key'] );
	}

	/**
	 * @return int|WP_Error Number of stored reviews, or error.
	 */
	public static function run() {
		$s = d360_gbr_settings();

		if ( empty( $s['place_id'] ) && empty( $s['data_id'] ) ) {
			return new WP_Error( 'd360_gbr_no_place', __( 'Pick your business first.', 'd360-gbr' ) );
		}

		$result = self::provider( $s )->fetch_reviews(
			array(
				'place_id' => $s['place_id'],
				'data_id'  => $s['data_id'],
				'name'     => $s['place_name'],
			),
			(int) $s['pages'],
			$s['language']
		);

		if ( is_wp_error( $result ) ) {
			D360_GBR_Store::set_error( $result->get_error_message() );
			return $result;
		}

		return D360_GBR_Store::save_sync( $result );
	}

	/**
	 * Cached for 10 minutes so opening Settings repeatedly doesn't re-hit the provider.
	 * SerpApi's /account endpoint is free of charge, but still an external call.
	 *
	 * @param bool $force Bypass the cache (e.g. a manual "Refresh" click).
	 * @return array|WP_Error|null
	 */
	public static function quota( $force = false ) {
		$cache_key = 'd360_gbr_quota';

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$result = self::provider()->quota();
		set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );

		return $result;
	}
}

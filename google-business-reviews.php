<?php
/**
 * Plugin Name:       Google Business Reviews
 * Description:       Show Google Business reviews by business name — no Google Places API. Reviews are fetched through SerpApi or Outscraper, cached in WordPress, and rendered as a carousel with [google_reviews].
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Plugin URI:        https://syednomanali.vercel.app/
 * Author:            Syed Noman Ali
 * Author URI:        https://syednomanali.vercel.app/
 * Text Domain:       d360-gbr
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'D360_GBR_VERSION', '1.2.0' );
define( 'D360_GBR_FILE', __FILE__ );
define( 'D360_GBR_DIR', plugin_dir_path( __FILE__ ) );
define( 'D360_GBR_URL', plugin_dir_url( __FILE__ ) );
define( 'D360_GBR_OPTION', 'd360_gbr_settings' );
define( 'D360_GBR_DATA', 'd360_gbr_data' );
define( 'D360_GBR_CRON', 'd360_gbr_sync_event' );

require_once D360_GBR_DIR . 'provider-interface.php';
require_once D360_GBR_DIR . 'provider-serpapi.php';
require_once D360_GBR_DIR . 'provider-outscraper.php';
require_once D360_GBR_DIR . 'class-gbr-store.php';
require_once D360_GBR_DIR . 'class-gbr-sync.php';
require_once D360_GBR_DIR . 'class-gbr-shortcode.php';
require_once D360_GBR_DIR . 'class-gbr-admin.php';

/**
 * Plugin settings merged with defaults.
 *
 * @return array
 */
function d360_gbr_settings() {
	$defaults = array(
		'provider'       => 'serpapi',   // serpapi | outscraper
		'serpapi_key'    => '',
		'outscraper_key' => '',
		'language'       => 'en',
		'frequency'      => 'weekly',    // daily | twice_weekly | weekly | manual
		'pages'          => 1,           // review pages per sync (1–5)
		'min_rating'     => 4,
		'hide_empty'     => 1,           // hide star-only reviews with no text
		'logo_url'       => '',
		// Selected business (filled from "Find your business").
		'place_id'       => '',
		'data_id'        => '',
		'place_name'     => '',
		'place_address'  => '',
	);

	$saved = get_option( D360_GBR_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

// Must be registered before activation so the custom recurrence exists when the event is scheduled.
add_filter( 'cron_schedules', array( 'D360_GBR_Sync', 'cron_schedules' ) );

register_activation_hook( __FILE__, array( 'D360_GBR_Sync', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'D360_GBR_Sync', 'deactivate' ) );

D360_GBR_Sync::init();
D360_GBR_Shortcode::init();

if ( is_admin() ) {
	D360_GBR_Admin::init();
}

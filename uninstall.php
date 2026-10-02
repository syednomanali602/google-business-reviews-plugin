<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'd360_gbr_settings' );
delete_option( 'd360_gbr_data' );
wp_clear_scheduled_hook( 'd360_gbr_sync_event' );

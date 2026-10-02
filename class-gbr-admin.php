<?php
/**
 * Settings → Google Reviews
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Admin {

	const SLUG = 'd360-google-reviews';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_d360_gbr_search', array( __CLASS__, 'handle_search' ) );
		add_action( 'admin_post_d360_gbr_select', array( __CLASS__, 'handle_select' ) );
		add_action( 'admin_post_d360_gbr_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_d360_gbr_refresh_quota', array( __CLASS__, 'handle_refresh_quota' ) );
		add_action( 'update_option_' . D360_GBR_OPTION, array( __CLASS__, 'maybe_reschedule' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( D360_GBR_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Google Reviews', 'd360-gbr' ),
			__( 'Google Reviews', 'd360-gbr' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'd360-gbr' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting( 'd360_gbr', D360_GBR_OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	/**
	 * Merges into existing settings so the settings form never wipes the selected business.
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = d360_gbr_settings();

		if ( isset( $input['provider'] ) ) {
			$out['provider'] = in_array( $input['provider'], array( 'serpapi', 'outscraper' ), true ) ? $input['provider'] : 'serpapi';
		}

		foreach ( array( 'serpapi_key', 'outscraper_key', 'place_id', 'data_id', 'place_name', 'place_address' ) as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $input[ $k ] );
			}
		}

		if ( isset( $input['language'] ) ) {
			$lang            = preg_replace( '/[^a-zA-Z\-]/', '', (string) $input['language'] );
			$out['language'] = $lang ? $lang : 'en';
		}
		if ( isset( $input['frequency'] ) ) {
			$out['frequency'] = in_array( $input['frequency'], array( 'daily', 'twice_weekly', 'weekly', 'manual' ), true ) ? $input['frequency'] : 'weekly';
		}
		if ( isset( $input['pages'] ) ) {
			$out['pages'] = min( 5, max( 1, (int) $input['pages'] ) );
		}
		if ( isset( $input['min_rating'] ) ) {
			$out['min_rating'] = min( 5, max( 1, (int) $input['min_rating'] ) );
		}
		if ( array_key_exists( 'logo_url', $input ) ) {
			$out['logo_url'] = esc_url_raw( trim( (string) $input['logo_url'] ) );
		}
		// Checkbox: only touch it when the settings form itself was submitted.
		if ( isset( $input['_form'] ) && 'settings' === $input['_form'] ) {
			$out['hide_empty'] = empty( $input['hide_empty'] ) ? 0 : 1;
		}

		return $out;
	}

	public static function maybe_reschedule( $old, $new ) {
		$old = is_array( $old ) ? $old : array();
		if ( ( $old['frequency'] ?? '' ) !== ( $new['frequency'] ?? '' ) ) {
			D360_GBR_Sync::reschedule();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'd360-gbr' ) );
		}
		check_admin_referer( $action );
	}

	private static function results_key() {
		return 'd360_gbr_results_' . get_current_user_id();
	}

	private static function back( $type, $message ) {
		set_transient( 'd360_gbr_notice_' . get_current_user_id(), array( $type, $message ), 60 );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function handle_search() {
		self::guard( 'd360_gbr_search' );

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		if ( '' === $query ) {
			self::back( 'error', __( 'Enter your business name to search.', 'd360-gbr' ) );
		}

		$s       = d360_gbr_settings();
		$results = D360_GBR_Sync::provider( $s )->search( $query, $s['language'] );

		if ( is_wp_error( $results ) ) {
			self::back( 'error', $results->get_error_message() );
		}

		set_transient(
			self::results_key(),
			array(
				'query'   => $query,
				'results' => $results,
			),
			HOUR_IN_SECONDS
		);

		if ( ! $results ) {
			self::back( 'warning', __( 'No businesses matched. Try adding the city or area, e.g. "Chem Center Karachi".', 'd360-gbr' ) );
		}

		/* translators: %d: number of matches */
		self::back( 'success', sprintf( _n( '%d match found. Pick yours below.', '%d matches found. Pick yours below.', count( $results ), 'd360-gbr' ), count( $results ) ) );
	}

	public static function handle_select() {
		self::guard( 'd360_gbr_select' );

		$saved = get_transient( self::results_key() );
		$index = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;

		if ( ! is_array( $saved ) || ! isset( $saved['results'][ $index ] ) ) {
			self::back( 'error', __( 'Search results expired. Search again.', 'd360-gbr' ) );
		}

		$pick     = $saved['results'][ $index ];
		$settings = d360_gbr_settings();

		$settings['place_id']      = $pick['place_id'];
		$settings['data_id']       = $pick['data_id'];
		$settings['place_name']    = $pick['name'];
		$settings['place_address'] = $pick['address'];

		update_option( D360_GBR_OPTION, $settings );
		delete_transient( self::results_key() );
		D360_GBR_Store::reset(); // Old business reviews must not mix in.

		$count = D360_GBR_Sync::run();

		if ( is_wp_error( $count ) ) {
			/* translators: %s: error message */
			self::back( 'error', sprintf( __( 'Business saved, but the first sync failed: %s', 'd360-gbr' ), $count->get_error_message() ) );
		}

		/* translators: 1: business name, 2: review count */
		self::back( 'success', sprintf( __( '%1$s connected. %2$d reviews stored.', 'd360-gbr' ), $pick['name'], $count ) );
	}

	public static function handle_sync() {
		self::guard( 'd360_gbr_sync' );

		$count = D360_GBR_Sync::run();

		if ( is_wp_error( $count ) ) {
			self::back( 'error', $count->get_error_message() );
		}

		/* translators: %d: review count */
		self::back( 'success', sprintf( __( 'Synced. %d reviews stored.', 'd360-gbr' ), $count ) );
	}

	public static function handle_refresh_quota() {
		self::guard( 'd360_gbr_refresh_quota' );
		D360_GBR_Sync::quota( true ); // Re-populates the 10-minute cache.
		self::back( 'success', __( 'Quota refreshed.', 'd360-gbr' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	private static function url() {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s      = d360_gbr_settings();
		$data   = D360_GBR_Store::get();
		$search = get_transient( self::results_key() );
		$notice = get_transient( 'd360_gbr_notice_' . get_current_user_id() );
		$opt    = D360_GBR_OPTION;

		if ( $notice ) {
			delete_transient( 'd360_gbr_notice_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Google Reviews', 'd360-gbr' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>

			<?php /* ---------- API quota ---------- */ ?>
			<?php $quota = D360_GBR_Sync::quota(); ?>
			<div class="card" style="max-width:780px">
				<h2 style="margin-top:.5em"><?php esc_html_e( 'API quota', 'd360-gbr' ); ?></h2>
				<?php if ( is_wp_error( $quota ) ) : ?>
					<p style="color:#b32d2e"><?php echo esc_html( $quota->get_error_message() ); ?></p>
				<?php elseif ( null === $quota ) : ?>
					<p class="description"><?php esc_html_e( 'This provider does not expose a usage balance. Check your usage on their dashboard.', 'd360-gbr' ); ?></p>
				<?php else : ?>
					<?php
					$limit = (int) $quota['limit'];
					$used  = (int) $quota['used'];
					$left  = (int) $quota['remaining'];
					$pct   = $limit > 0 ? min( 100, round( $used / $limit * 100 ) ) : 0;
					?>
					<p style="font-size:1.3rem;font-weight:600;margin:0 0 4px">
						<?php
						if ( $limit > 0 ) {
							/* translators: 1: searches used, 2: monthly limit */
							echo esc_html( sprintf( __( '%1$s / %2$s searches used this month', 'd360-gbr' ), number_format_i18n( $used ), number_format_i18n( $limit ) ) );
						} else {
							/* translators: %s: credits remaining */
							echo esc_html( sprintf( __( '%s credits remaining', 'd360-gbr' ), number_format_i18n( $left ) ) );
						}
						?>
					</p>
					<?php if ( $limit > 0 ) : ?>
						<div style="background:#eee;border-radius:4px;height:8px;overflow:hidden;max-width:360px">
							<div style="width:<?php echo (int) $pct; ?>%;background:<?php echo $pct >= 90 ? '#b32d2e' : ( $pct >= 70 ? '#dba617' : '#1c9a6c' ); ?>;height:100%"></div>
						</div>
						<p class="description" style="margin-top:6px">
							<?php
							/* translators: %s: searches remaining */
							echo esc_html( sprintf( __( '%s left this month.', 'd360-gbr' ), number_format_i18n( $left ) ) );
							if ( ! empty( $quota['renews'] ) ) {
								/* translators: %s: renewal date */
								echo ' ' . esc_html( sprintf( __( 'Renews %s.', 'd360-gbr' ), date_i18n( get_option( 'date_format' ), strtotime( $quota['renews'] ) ) ) );
							}
							?>
							<?php esc_html_e( 'One review-pages setting ≈ that many credits per sync; a weekly sync at 1 page uses about 4–5 a month.', 'd360-gbr' ); ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $quota['plan'] ) ) : ?>
						<p class="description"><?php echo esc_html( sprintf( '%s · %s', $quota['plan'], ucfirst( $s['provider'] ) ) ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
					<input type="hidden" name="action" value="d360_gbr_refresh_quota">
					<?php wp_nonce_field( 'd360_gbr_refresh_quota' ); ?>
					<?php submit_button( __( 'Refresh', 'd360-gbr' ), 'secondary small', 'submit', false ); ?>
				</form>
			</div>

			<?php /* ---------- Connected business ---------- */ ?>
			<?php if ( $s['place_id'] || $s['data_id'] ) : ?>
				<div class="card" style="max-width:780px">
					<h2 style="margin-top:.5em"><?php echo esc_html( $s['place_name'] ); ?></h2>
					<p style="color:#646970;margin-top:-6px"><?php echo esc_html( $s['place_address'] ); ?></p>
					<table class="widefat striped" style="margin-bottom:12px">
						<tbody>
							<tr>
								<td style="width:200px"><?php esc_html_e( 'Google rating', 'd360-gbr' ); ?></td>
								<td>
									<?php
									echo ! empty( $data['place']['rating'] )
										? esc_html( number_format_i18n( $data['place']['rating'], 1 ) . ' / 5 · ' . number_format_i18n( (int) ( $data['place']['total'] ?? 0 ) ) . ' ' . __( 'reviews on Google', 'd360-gbr' ) )
										: '—';
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Reviews stored', 'd360-gbr' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( count( $data['reviews'] ) ) ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Last sync', 'd360-gbr' ); ?></td>
								<td>
									<?php
									echo $data['synced_at']
										/* translators: %s: human time diff */
										? esc_html( sprintf( __( '%s ago', 'd360-gbr' ), human_time_diff( $data['synced_at'] ) ) )
										: esc_html__( 'Never', 'd360-gbr' );
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Next automatic sync', 'd360-gbr' ); ?></td>
								<td>
									<?php
									$next = wp_next_scheduled( D360_GBR_CRON );
									echo $next
										/* translators: %s: human time diff */
										? esc_html( sprintf( __( 'in %s', 'd360-gbr' ), human_time_diff( $next ) ) )
										: esc_html__( 'Off (manual only)', 'd360-gbr' );
									?>
								</td>
							</tr>
							<?php if ( $data['last_error'] ) : ?>
								<tr>
									<td><?php esc_html_e( 'Last error', 'd360-gbr' ); ?></td>
									<td style="color:#b32d2e"><?php echo esc_html( $data['last_error'] ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="d360_gbr_sync">
						<?php wp_nonce_field( 'd360_gbr_sync' ); ?>
						<?php submit_button( __( 'Sync now', 'd360-gbr' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php /* ---------- Find business ---------- */ ?>
			<div class="card" style="max-width:780px">
				<h2 style="margin-top:.5em">
					<?php ( $s['place_id'] || $s['data_id'] ) ? esc_html_e( 'Change business', 'd360-gbr' ) : esc_html_e( 'Find your business', 'd360-gbr' ); ?>
				</h2>
				<p><?php esc_html_e( 'Type the name as it appears on Google Maps. Adding the city gives better matches. Each search uses one API credit.', 'd360-gbr' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap">
					<input type="hidden" name="action" value="d360_gbr_search">
					<?php wp_nonce_field( 'd360_gbr_search' ); ?>
					<input type="text" name="query" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Chem Center Bio Karachi', 'd360-gbr' ); ?>" value="<?php echo esc_attr( $search['query'] ?? '' ); ?>" required>
					<?php submit_button( __( 'Search', 'd360-gbr' ), 'primary', 'submit', false ); ?>
				</form>

				<?php if ( ! empty( $search['results'] ) ) : ?>
					<table class="widefat striped" style="margin-top:14px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Business', 'd360-gbr' ); ?></th>
								<th style="width:120px"><?php esc_html_e( 'Rating', 'd360-gbr' ); ?></th>
								<th style="width:110px"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $search['results'] as $i => $r ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $r['name'] ); ?></strong><br>
										<span style="color:#646970"><?php echo esc_html( $r['address'] ); ?></span>
									</td>
									<td><?php echo $r['rating'] ? esc_html( number_format_i18n( $r['rating'], 1 ) . ' (' . number_format_i18n( $r['total'] ) . ')' ) : '—'; ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="d360_gbr_select">
											<input type="hidden" name="index" value="<?php echo (int) $i; ?>">
											<?php wp_nonce_field( 'd360_gbr_select' ); ?>
											<?php submit_button( __( 'Use this', 'd360-gbr' ), 'secondary small', 'submit', false ); ?>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php /* ---------- Settings ---------- */ ?>
			<form method="post" action="options.php" style="max-width:780px">
				<?php settings_fields( 'd360_gbr' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_form]" value="settings">
				<h2><?php esc_html_e( 'Settings', 'd360-gbr' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Review source', 'd360-gbr' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[provider]" value="serpapi" <?php checked( $s['provider'], 'serpapi' ); ?>> SerpApi</label><br>
							<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[provider]" value="outscraper" <?php checked( $s['provider'], 'outscraper' ); ?>> Outscraper</label>
							<p class="description"><?php esc_html_e( 'Both have a free monthly quota. Save after switching, then search for the business again.', 'd360-gbr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-serp"><?php esc_html_e( 'SerpApi key', 'd360-gbr' ); ?></label></th>
						<td>
							<input id="d360-gbr-serp" type="password" autocomplete="off" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[serpapi_key]" value="<?php echo esc_attr( $s['serpapi_key'] ); ?>">
							<p class="description"><a href="https://serpapi.com/manage-api-key" target="_blank" rel="noopener"><?php esc_html_e( 'Get your key', 'd360-gbr' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-out"><?php esc_html_e( 'Outscraper key', 'd360-gbr' ); ?></label></th>
						<td>
							<input id="d360-gbr-out" type="password" autocomplete="off" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[outscraper_key]" value="<?php echo esc_attr( $s['outscraper_key'] ); ?>">
							<p class="description"><a href="https://app.outscraper.com/profile" target="_blank" rel="noopener"><?php esc_html_e( 'Get your key', 'd360-gbr' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-freq"><?php esc_html_e( 'Automatic sync', 'd360-gbr' ); ?></label></th>
						<td>
							<select id="d360-gbr-freq" name="<?php echo esc_attr( $opt ); ?>[frequency]">
								<option value="daily" <?php selected( $s['frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'd360-gbr' ); ?></option>
								<option value="twice_weekly" <?php selected( $s['frequency'], 'twice_weekly' ); ?>><?php esc_html_e( 'Twice a week', 'd360-gbr' ); ?></option>
								<option value="weekly" <?php selected( $s['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'd360-gbr' ); ?></option>
								<option value="manual" <?php selected( $s['frequency'], 'manual' ); ?>><?php esc_html_e( 'Off — I will click Sync now', 'd360-gbr' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Weekly is plenty for most businesses and keeps you well inside free quotas.', 'd360-gbr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-pages"><?php esc_html_e( 'Review pages per sync', 'd360-gbr' ); ?></label></th>
						<td>
							<input id="d360-gbr-pages" type="number" min="1" max="5" class="small-text" name="<?php echo esc_attr( $opt ); ?>[pages]" value="<?php echo (int) $s['pages']; ?>">
							<p class="description"><?php esc_html_e( 'SerpApi: each page is one credit (first page about 8 reviews, later pages 20). Outscraper: 20 reviews per page. Reviews accumulate across syncs.', 'd360-gbr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-min"><?php esc_html_e( 'Minimum stars shown', 'd360-gbr' ); ?></label></th>
						<td>
							<select id="d360-gbr-min" name="<?php echo esc_attr( $opt ); ?>[min_rating]">
								<?php for ( $n = 5; $n >= 1; $n-- ) : ?>
									<option value="<?php echo (int) $n; ?>" <?php selected( (int) $s['min_rating'], $n ); ?>><?php echo esc_html( $n . '+' ); ?></option>
								<?php endfor; ?>
							</select>
							<label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_empty]" value="1" <?php checked( $s['hide_empty'], 1 ); ?>> <?php esc_html_e( 'Hide reviews with no text', 'd360-gbr' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-lang"><?php esc_html_e( 'Language', 'd360-gbr' ); ?></label></th>
						<td><input id="d360-gbr-lang" type="text" class="small-text" name="<?php echo esc_attr( $opt ); ?>[language]" value="<?php echo esc_attr( $s['language'] ); ?>"> <span class="description">en, de, es, ur…</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="d360-gbr-logo"><?php esc_html_e( 'Source logo URL', 'd360-gbr' ); ?></label></th>
						<td>
							<input id="d360-gbr-logo" type="url" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[logo_url]" value="<?php echo esc_attr( $s['logo_url'] ); ?>">
							<p class="description"><?php esc_html_e( 'Optional. Upload the official Google logo to the Media Library and paste its URL. Left empty, the widget shows the word "Google".', 'd360-gbr' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php /* ---------- Usage ---------- */ ?>
			<h2><?php esc_html_e( 'Show reviews', 'd360-gbr' ); ?></h2>
			<p><?php esc_html_e( 'Paste this shortcode into any page, Elementor Shortcode widget, or block:', 'd360-gbr' ); ?></p>
			<p><code>[google_reviews]</code></p>
			<p><code>[google_reviews limit="9" min_rating="5" header="yes" lines="5"]</code></p>
		</div>
		<?php
	}
}

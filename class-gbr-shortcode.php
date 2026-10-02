<?php
/**
 * [google_reviews limit="12" min_rating="4" header="no" lines="4" hide_empty="yes"]
 */

defined( 'ABSPATH' ) || exit;

class D360_GBR_Shortcode {

	public static function init() {
		add_shortcode( 'google_reviews', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'd360-gbr', D360_GBR_URL . 'assets/gbr.css', array(), D360_GBR_VERSION );
		wp_register_script( 'd360-gbr', D360_GBR_URL . 'assets/gbr.js', array(), D360_GBR_VERSION, true );
	}

	public static function render( $atts ) {
		$s = d360_gbr_settings();
		$a = shortcode_atts(
			array(
				'limit'      => 12,
				'min_rating' => $s['min_rating'],
				'header'     => 'no',
				'lines'      => 4,
				'hide_empty' => $s['hide_empty'] ? 'yes' : 'no',
			),
			$atts,
			'google_reviews'
		);

		$data       = D360_GBR_Store::get();
		$min        = (int) $a['min_rating'];
		$hide_empty = 'yes' === $a['hide_empty'];

		$reviews = array_filter(
			$data['reviews'],
			static function ( $r ) use ( $min, $hide_empty ) {
				return (int) $r['rating'] >= $min && ( ! $hide_empty || '' !== trim( (string) $r['text'] ) );
			}
		);
		$reviews = array_slice( array_values( $reviews ), 0, max( 1, (int) $a['limit'] ) );

		if ( ! $reviews ) {
			return current_user_can( 'manage_options' )
				? '<p><em>' . esc_html__( 'No reviews to show yet. Connect your business under Settings → Google Reviews.', 'd360-gbr' ) . '</em></p>'
				: '';
		}

		if ( ! wp_style_is( 'd360-gbr', 'registered' ) ) {
			self::register_assets(); // Shortcode rendered outside the normal front-end flow (e.g. page builder preview).
		}
		wp_enqueue_style( 'd360-gbr' );
		wp_enqueue_script( 'd360-gbr' );

		$place = wp_parse_args(
			(array) $data['place'],
			array(
				'name'   => $s['place_name'],
				'rating' => 0,
				'total'  => 0,
			)
		);

		ob_start();
		include D360_GBR_DIR . 'template-carousel.php';
		return ob_get_clean();
	}

	/* ---------------- Helpers used by the template ---------------- */

	public static function stars( $rating ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		$star   = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
		$row    = str_repeat( $star, 5 );
		$pct    = number_format( $rating / 5 * 100, 2, '.', '' );

		/* translators: %s: rating */
		$label = sprintf( __( 'Rated %s out of 5', 'd360-gbr' ), number_format_i18n( $rating, 1 ) );

		return '<span class="d360-gbr__stars" role="img" aria-label="' . esc_attr( $label ) . '" style="--gbr-pct:' . esc_attr( $pct ) . '%">'
			. '<span class="d360-gbr__stars-row">' . $row . '</span>'
			. '<span class="d360-gbr__stars-row d360-gbr__stars-row--fill">' . $row . '</span>'
			. '</span>';
	}

	public static function label( $rating ) {
		$rating = (float) $rating;
		if ( $rating >= 4.5 ) {
			return __( 'Excellent', 'd360-gbr' );
		}
		if ( $rating >= 4 ) {
			return __( 'Very good', 'd360-gbr' );
		}
		if ( $rating >= 3.5 ) {
			return __( 'Good', 'd360-gbr' );
		}
		if ( $rating >= 3 ) {
			return __( 'Average', 'd360-gbr' );
		}
		return __( 'Poor', 'd360-gbr' );
	}

	/** Computed at render time so "2 days ago" stays accurate between syncs. */
	public static function when( array $r ) {
		if ( ! empty( $r['time'] ) ) {
			/* translators: %s: human time diff */
			return sprintf( __( '%s ago', 'd360-gbr' ), human_time_diff( (int) $r['time'] ) );
		}
		return (string) ( $r['date_label'] ?? '' );
	}

	public static function initial( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return '?';
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
	}

	public static function initial_color( $name ) {
		$palette = array( '#5e35b1', '#8d6e63', '#1e88e5', '#43a047', '#f4511e', '#00897b', '#7cb342', '#c2185b', '#546e7a' );
		return $palette[ abs( crc32( (string) $name ) ) % count( $palette ) ];
	}
}

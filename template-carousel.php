<?php
/**
 * Review carousel markup.
 *
 * @var array $place
 * @var array $reviews
 * @var array $a  Shortcode attributes.
 * @var array $s  Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

$logo        = $s['logo_url'];
$verified    = '<svg class="d360-gbr__verified" viewBox="0 0 24 24" role="img" aria-label="' . esc_attr__( 'Verified Google review', 'd360-gbr' ) . '"><path fill="currentColor" d="m23 12-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>';
$chev_left   = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$chev_right  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$check       = '<svg class="d360-gbr__check" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M7.2 12.4l3.1 3.1 6.5-6.6" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$read_more   = __( 'Read more', 'd360-gbr' );
$read_less   = __( 'Show less', 'd360-gbr' );
?>
<div class="d360-gbr" data-d360-gbr style="--gbr-lines:<?php echo (int) max( 2, (int) $a['lines'] ); ?>">

	<?php if ( 'yes' === $a['header'] && ! empty( $place['rating'] ) ) : ?>
		<div class="d360-gbr__summary">
			<div class="d360-gbr__label"><?php echo esc_html( D360_GBR_Shortcode::label( $place['rating'] ) ); ?></div>
			<?php echo D360_GBR_Shortcode::stars( $place['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
			<?php if ( ! empty( $place['total'] ) ) : ?>
				<div class="d360-gbr__based">
					<?php
					/* translators: %s: number of reviews */
					echo wp_kses( sprintf( __( 'Based on <strong>%s reviews</strong>', 'd360-gbr' ), number_format_i18n( (int) $place['total'] ) ), array( 'strong' => array() ) );
					?>
				</div>
			<?php endif; ?>
			<div class="d360-gbr__source">
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="Google" height="40" loading="lazy">
				<?php else : ?>
					<span>Google</span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="d360-gbr__viewport">
		<button type="button" class="d360-gbr__nav d360-gbr__nav--prev" hidden aria-label="<?php esc_attr_e( 'Previous reviews', 'd360-gbr' ); ?>"><?php echo $chev_left; // phpcs:ignore ?></button>

		<ul class="d360-gbr__track">
			<?php foreach ( $reviews as $r ) : ?>
				<?php
				$initial = D360_GBR_Shortcode::initial( $r['author'] );
				$color   = D360_GBR_Shortcode::initial_color( $r['author'] );
				?>
				<li class="d360-gbr__card">
					<div class="d360-gbr__head">
						<?php if ( ! empty( $r['avatar'] ) ) : ?>
							<img class="d360-gbr__avatar" src="<?php echo esc_url( $r['avatar'] ); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-initial="<?php echo esc_attr( $initial ); ?>" data-bg="<?php echo esc_attr( $color ); ?>">
						<?php else : ?>
							<span class="d360-gbr__avatar d360-gbr__avatar--initial" style="--gbr-initial-bg:<?php echo esc_attr( $color ); ?>" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
						<?php endif; ?>

						<div class="d360-gbr__who">
							<?php if ( ! empty( $r['author_url'] ) ) : ?>
								<a class="d360-gbr__name" href="<?php echo esc_url( $r['author_url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $r['author'] ); ?></a>
							<?php else : ?>
								<span class="d360-gbr__name"><?php echo esc_html( $r['author'] ); ?></span>
							<?php endif; ?>
							<span class="d360-gbr__time"><?php echo esc_html( D360_GBR_Shortcode::when( $r ) ); ?></span>
						</div>

						<?php echo $check; // phpcs:ignore ?>
					</div>

					<div class="d360-gbr__rating">
						<?php echo D360_GBR_Shortcode::stars( $r['rating'] ); // phpcs:ignore ?>
						<?php echo $verified; // phpcs:ignore ?>
					</div>

					<?php if ( '' !== trim( (string) $r['text'] ) ) : ?>
						<p class="d360-gbr__text"><?php echo esc_html( trim( $r['text'] ) ); ?></p>
						<button type="button" class="d360-gbr__more" hidden aria-expanded="false" data-more="<?php echo esc_attr( $read_more ); ?>" data-less="<?php echo esc_attr( $read_less ); ?>"><?php echo esc_html( $read_more ); ?></button>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<button type="button" class="d360-gbr__nav d360-gbr__nav--next" aria-label="<?php esc_attr_e( 'Next reviews', 'd360-gbr' ); ?>"><?php echo $chev_right; // phpcs:ignore ?></button>
	</div>
</div>

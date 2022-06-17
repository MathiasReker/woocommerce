<?php declare(strict_types=1);
defined( 'ABSPATH' ) or exit(); ?>

<?php foreach ( $notices as $notice ) : ?>
	<div class="notice <?php echo sanitize_html_class( $notice['type'] ); ?>">
		<?php echo wpautop( $notice['message'] ); ?>
	</div>
<?php endforeach; ?>

<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<section class="tthpr-section" data-tthpr-slider id="<?php echo esc_attr($instance_id); ?>">
	<div class="tthpr-shell">
		<div class="tthpr-header">
			<h2 class="tthpr-title"><?php echo esc_html($settings['title']); ?></h2>
			<?php if (!empty($settings['view_all_url'])): ?>
				<a class="tthpr-view-all" href="<?php echo esc_url($settings['view_all_url']); ?>">
					<?php echo esc_html($settings['view_all_label']); ?>
					<span class="tthpr-view-all-icon" aria-hidden="true">&rsaquo;</span>
				</a>
			<?php endif; ?>
		</div>

		<div class="tthpr-track-wrap">
			<div class="tthpr-track" data-tthpr-track>
				<?php require __DIR__ . '/cards.php'; ?>
			</div>
		</div>

		<div class="tthpr-controls">
			<div class="tthpr-pagination" data-tthpr-pagination></div>
			<div class="tthpr-arrows">
				<button type="button" class="tthpr-arrow tthpr-arrow--prev" data-tthpr-prev
					aria-label="<?php esc_attr_e('การ์ดก่อนหน้า', 'tumtook-home-product-recommendations'); ?>">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M14.5 6.5L9 12l5.5 5.5" />
					</svg>
				</button>
				<button type="button" class="tthpr-arrow tthpr-arrow--next" data-tthpr-next
					aria-label="<?php esc_attr_e('การ์ดถัดไป', 'tumtook-home-product-recommendations'); ?>">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M9.5 6.5L15 12l-5.5 5.5" />
					</svg>
				</button>
			</div>
		</div>
	</div>
</section>

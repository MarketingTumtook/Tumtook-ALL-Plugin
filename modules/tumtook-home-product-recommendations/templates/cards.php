<?php
if (!defined('ABSPATH')) {
	exit;
}
foreach ($items as $item):
	?>
	<article class="tthpr-card" data-card-url="<?php echo esc_url($item['url']); ?>">
		<a class="tthpr-card-link" href="<?php echo esc_url($item['url']); ?>"
			aria-label="<?php echo esc_attr($item['title']); ?>" draggable="false"></a>
		<div class="tthpr-card-media">
			<div class="tthpr-image-link<?php echo empty($item['image']) ? ' tthpr-image-link--missing' : ''; ?>">
				<?php if (!empty($item['badge'])): ?>
					<span
						class="tthpr-badge tthpr-badge--<?php echo esc_attr($item['badge_type']); ?>"><?php echo esc_html($item['badge']); ?></span>
				<?php endif; ?>
				<?php if (!empty($item['image'])): ?>
					<img class="tthpr-image" src="<?php echo esc_url($item['image']); ?>"
						alt="<?php echo esc_attr($item['title']); ?>" loading="lazy" decoding="async"
						onerror="this.style.display='none';this.parentNode.classList.add('tthpr-image-link--missing');" />
				<?php endif; ?>
				<div class="tthpr-image tthpr-image--placeholder" aria-hidden="true">
					<div class="tthpr-image-fallback">
						<span class="tthpr-image-fallback-badge">NO IMAGE</span>
						<div class="tthpr-image-fallback-box"></div>
						<div class="tthpr-image-fallback-lines">
							<span></span>
							<span></span>
							<span></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="tthpr-card-body">
			<div class="tthpr-content">
				<h3 class="tthpr-product-title"><?php echo esc_html($item['title']); ?></h3>
				<div class="tthpr-footer">
					<?php if ('' !== $item['price']): ?>
						<div class="tthpr-price"><?php echo esc_html($item['price']); ?></div>
					<?php endif; ?>
					<a class="tthpr-button" href="<?php echo esc_url($item['url']); ?>"
						aria-label="<?php echo esc_attr(trim($settings['button_label'] . ' ' . $item['title'])); ?>">
						<span class="tthpr-button-arrow" aria-hidden="true"></span>
						<span class="tthpr-button-label"><?php echo esc_html($settings['button_label']); ?></span>
					</a>
				</div>
			</div>
		</div>
	</article>
	<?php
endforeach;


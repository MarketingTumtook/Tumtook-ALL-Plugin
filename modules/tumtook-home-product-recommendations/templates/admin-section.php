<?php
if (!defined('ABSPATH')) {
	exit;
}
$shortcode = sprintf('[tumtook_home_recommended_products section="%s"]', $section['id']);
?>
<article class="tthpr-admin-section" data-tthpr-section data-section-id="<?php echo esc_attr($section['id']); ?>">
	<input type="hidden" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][id]" value="<?php echo esc_attr($section['id']); ?>" data-tthpr-section-id />
	<header class="tthpr-admin-section-header">
		<h3><?php esc_html_e('Section', 'tumtook-home-product-recommendations'); ?> <span data-tthpr-section-number></span> — <span data-tthpr-section-heading><?php echo esc_html($section['title']); ?></span></h3>
		<div class="tthpr-admin-section-actions">
			<button type="button" class="button" data-tthpr-section-move="up" aria-label="<?php esc_attr_e('เลื่อน Section ขึ้น', 'tumtook-home-product-recommendations'); ?>">↑</button>
			<button type="button" class="button" data-tthpr-section-move="down" aria-label="<?php esc_attr_e('เลื่อน Section ลง', 'tumtook-home-product-recommendations'); ?>">↓</button>
			<button type="button" class="button" data-tthpr-duplicate-section><?php esc_html_e('ทำสำเนา', 'tumtook-home-product-recommendations'); ?></button>
			<button type="button" class="button button-link-delete" data-tthpr-remove-section><?php esc_html_e('ลบ Section', 'tumtook-home-product-recommendations'); ?></button>
		</div>
	</header>

	<div class="tthpr-admin-section-body">
		<p><label><input type="checkbox" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][enabled]" value="1" <?php checked($section['enabled'], '1'); ?> /> <?php esc_html_e('เปิดใช้งาน Section นี้', 'tumtook-home-product-recommendations'); ?></label></p>
		<div class="tthpr-admin-grid">
			<label class="tthpr-admin-field">
				<span><?php esc_html_e('หัวข้อ Section', 'tumtook-home-product-recommendations'); ?></span>
				<input type="text" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][title]" value="<?php echo esc_attr($section['title']); ?>" data-tthpr-section-title />
			</label>
			<label class="tthpr-admin-field">
				<span><?php esc_html_e('จำนวนการ์ดสูงสุด', 'tumtook-home-product-recommendations'); ?></span>
				<input type="number" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][limit]" value="<?php echo esc_attr($section['limit']); ?>" min="0" max="99" />
				<span class="description"><?php esc_html_e('ใส่ 0 เพื่อแสดงทุกหน้าที่เลือก', 'tumtook-home-product-recommendations'); ?></span>
			</label>
			<label class="tthpr-admin-field">
				<span><?php esc_html_e('ข้อความลิงก์เพิ่มเติม', 'tumtook-home-product-recommendations'); ?></span>
				<input type="text" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][view_all_label]" value="<?php echo esc_attr($section['view_all_label']); ?>" />
			</label>
			<label class="tthpr-admin-field">
				<span><?php esc_html_e('URL ลิงก์เพิ่มเติม', 'tumtook-home-product-recommendations'); ?></span>
				<input type="url" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][view_all_url]" value="<?php echo esc_attr($section['view_all_url']); ?>" />
			</label>
			<label class="tthpr-admin-field">
				<span><?php esc_html_e('ข้อความปุ่มในการ์ด', 'tumtook-home-product-recommendations'); ?></span>
				<input type="text" name="tthpr_sections[<?php echo esc_attr($section_key); ?>][button_label]" value="<?php echo esc_attr($section['button_label']); ?>" />
			</label>
		</div>

		<div class="tthpr-admin-shortcode">
			<span><?php esc_html_e('Shortcode สำหรับ Section นี้', 'tumtook-home-product-recommendations'); ?></span>
			<code data-tthpr-section-shortcode><?php echo esc_html($shortcode); ?></code>
			<button type="button" class="button" data-tthpr-copy-shortcode><?php esc_html_e('คัดลอก', 'tumtook-home-product-recommendations'); ?></button>
			<span class="tthpr-admin-copy-status" data-tthpr-copy-status aria-live="polite"></span>
		</div>

		<div class="tthpr-admin-pages">
			<h4><?php esc_html_e('เลือก Card ที่แสดงใน Section นี้', 'tumtook-home-product-recommendations'); ?></h4>
			<p class="description"><?php esc_html_e('แต่ละ Card คือ Page หนึ่งหน้า สามารถเพิ่ม ลบ และใช้ปุ่ม ↑ ↓ จัดลำดับได้', 'tumtook-home-product-recommendations'); ?></p>
			<div data-tthpr-rows>
				<?php foreach ($section['page_ids'] ? $section['page_ids'] : array(0) as $page_id): ?>
					<?php $this->render_page_select($pages, $section_key, $page_id); ?>
				<?php endforeach; ?>
			</div>
			<template data-tthpr-row-template><?php $this->render_page_select($pages, $section_key); ?></template>
			<p><button type="button" class="button button-secondary" data-tthpr-add-card><?php esc_html_e('+ เพิ่ม Card', 'tumtook-home-product-recommendations'); ?></button></p>
		</div>
	</div>
</article>

<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="tthpr-admin" data-tthpr-admin>
	<p><?php esc_html_e('เลือกหน้าที่จะนำมาแสดงเป็นการ์ดบนหน้า Home ระบบใช้ชื่อ รูป ราคา และป้ายสถานะจากข้อมูลการ์ดของแต่ละหน้า', 'tumtook-home-product-recommendations'); ?></p>
	<p><label><input type="checkbox" name="tthpr_settings[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?> /> <?php esc_html_e('เปิดใช้งานส่วนนี้', 'tumtook-home-product-recommendations'); ?></label></p>
	<div class="tthpr-admin-grid">
		<label class="tthpr-admin-field">
			<span><?php esc_html_e('หัวข้อ', 'tumtook-home-product-recommendations'); ?></span>
			<input type="text" name="tthpr_settings[title]" value="<?php echo esc_attr($settings['title']); ?>" />
		</label>
		<label class="tthpr-admin-field">
			<span><?php esc_html_e('จำนวนการ์ดสูงสุด', 'tumtook-home-product-recommendations'); ?></span>
			<input type="number" name="tthpr_settings[limit]" value="<?php echo esc_attr($settings['limit']); ?>" min="0" max="99" />
			<span class="description"><?php esc_html_e('ใส่ 0 เพื่อแสดงทุกหน้าที่เลือก', 'tumtook-home-product-recommendations'); ?></span>
		</label>
		<label class="tthpr-admin-field">
			<span><?php esc_html_e('ข้อความลิงก์ทั้งหมด', 'tumtook-home-product-recommendations'); ?></span>
			<input type="text" name="tthpr_settings[view_all_label]" value="<?php echo esc_attr($settings['view_all_label']); ?>" />
		</label>
		<label class="tthpr-admin-field">
			<span><?php esc_html_e('ลิงก์ทั้งหมด', 'tumtook-home-product-recommendations'); ?></span>
			<input type="url" name="tthpr_settings[view_all_url]" value="<?php echo esc_attr($settings['view_all_url']); ?>" />
		</label>
		<label class="tthpr-admin-field">
			<span><?php esc_html_e('ข้อความปุ่มในการ์ด', 'tumtook-home-product-recommendations'); ?></span>
			<input type="text" name="tthpr_settings[button_label]" value="<?php echo esc_attr($settings['button_label']); ?>" />
		</label>
	</div>
	<div class="tthpr-admin-pages">
		<h3><?php esc_html_e('เลือกหน้าที่แสดง', 'tumtook-home-product-recommendations'); ?></h3>
		<p class="description"><?php esc_html_e('เพิ่มได้หลายหน้า ใช้ปุ่ม ↑ ↓ จัดลำดับการ์ด เลือกได้เฉพาะหน้าที่เผยแพร่และไม่มีรหัสผ่าน หากยังไม่เลือกหน้า ส่วนนี้จะไม่แสดงบนเว็บไซต์', 'tumtook-home-product-recommendations'); ?></p>
		<div data-tthpr-rows>
			<?php foreach ($settings['page_ids'] ? $settings['page_ids'] : array(0) as $page_id): ?>
				<?php $this->render_page_select($pages, $page_id); ?>
			<?php endforeach; ?>
		</div>
		<template data-tthpr-row-template><?php $this->render_page_select($pages); ?></template>
		<p><button type="button" class="button button-secondary" data-tthpr-add><?php esc_html_e('+ เพิ่มหน้า', 'tumtook-home-product-recommendations'); ?></button></p>
		<p class="description"><?php esc_html_e('ถ้าไม่ได้ตั้งชื่อหรือรูปการ์ด จะใช้ชื่อหน้าและ Featured Image ถ้าไม่มีราคา จะซ่อนราคา', 'tumtook-home-product-recommendations'); ?></p>
	</div>
	<p><?php esc_html_e('วาง Shortcode นี้ในหน้า Home หรือ widget Shortcode ของ Elementor แล้วบันทึกหน้า:', 'tumtook-home-product-recommendations'); ?> <code>[tumtook_home_recommended_products]</code></p>
</div>

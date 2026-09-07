<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="tthpr-admin" data-tthpr-admin>
	<p><?php esc_html_e('สร้างได้หลาย Section โดยแต่ละ Section มีหัวข้อ ลิงก์ และรายการ Page ของตัวเอง การ์ดจะใช้ข้อมูลจาก Card Category ทั้งหมดของหน้าที่เลือก', 'tumtook-home-product-recommendations'); ?></p>
	<div class="tthpr-admin-sections" data-tthpr-sections>
		<?php foreach ($settings['sections'] as $section): ?>
			<?php $this->render_admin_section($pages, $section); ?>
		<?php endforeach; ?>
	</div>
	<template data-tthpr-section-template>
		<?php $this->render_admin_section($pages, $this->get_default_section('__SECTION_ID__')); ?>
	</template>
	<p class="tthpr-admin-add-section-wrap">
		<button type="button" class="button button-primary" data-tthpr-add-section><?php esc_html_e('+ เพิ่ม Section', 'tumtook-home-product-recommendations'); ?></button>
	</p>
	<div class="tthpr-admin-usage">
		<strong><?php esc_html_e('วิธีใช้', 'tumtook-home-product-recommendations'); ?></strong>
		<p><?php esc_html_e('แสดงทุก Section ตามลำดับ:', 'tumtook-home-product-recommendations'); ?> <code>[tumtook_home_recommended_products]</code></p>
		<p><?php esc_html_e('หากวาง shortcode ใน Template ให้ระบุ ID ของหน้า Home เพิ่มด้วย เช่น:', 'tumtook-home-product-recommendations'); ?> <code>[tumtook_home_recommended_products page_id="<?php echo esc_attr($post->ID); ?>"]</code></p>
	</div>
</div>

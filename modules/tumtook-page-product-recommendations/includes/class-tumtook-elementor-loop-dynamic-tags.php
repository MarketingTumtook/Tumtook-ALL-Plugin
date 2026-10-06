<?php
/**
 * Elementor dynamic tags for Tumtook product-card fields.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Elementor_Loop_Dynamic_Tags
{
	const GROUP = 'tumtook-product-card';

	public function __construct()
	{
		add_action('elementor/dynamic_tags/register', array($this, 'register_tags'));
	}

	public function register_tags($dynamic_tags)
	{
		if (!is_object($dynamic_tags) || !method_exists($dynamic_tags, 'register') || !method_exists($dynamic_tags, 'register_group')) {
			return;
		}

		if (!class_exists('Elementor\\Core\\DynamicTags\\Tag') || !class_exists('Elementor\\Core\\DynamicTags\\Data_Tag')) {
			return;
		}

		require_once __DIR__ . '/elementor-loop-tags.php';

		$dynamic_tags->register_group(
			self::GROUP,
			array('title' => __('Tumtook Product Card', 'tumtook-page-product-recommendations'))
		);
		$dynamic_tags->register(new Tumtook_Elementor_Tag_Product_Image());
		$dynamic_tags->register(new Tumtook_Elementor_Tag_Product_Title());
		$dynamic_tags->register(new Tumtook_Elementor_Tag_Product_Price());
		$dynamic_tags->register(new Tumtook_Elementor_Tag_Product_Url());
	}

	public static function get_context_post_id()
	{
		$post_id = absint(get_the_ID());

		if ($post_id && 'elementor_library' !== get_post_type($post_id)) {
			return $post_id;
		}

		$queried_id = absint(get_queried_object_id());
		return $queried_id && 'elementor_library' !== get_post_type($queried_id) ? $queried_id : 0;
	}

	public static function get_image($post_id = 0)
	{
		$post_id = $post_id ? absint($post_id) : self::get_context_post_id();
		if (!$post_id) {
			return array('id' => 0, 'url' => '');
		}

		$image_id = absint(get_post_meta($post_id, '_ttpr_page_image_id', true));
		if (!$image_id) {
			$image_id = absint(get_post_meta($post_id, '_ttpc_page_image_id', true));
		}
		if (!$image_id) {
			$image_id = absint(get_post_thumbnail_id($post_id));
		}

		$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
		return array(
			'id' => $image_url ? $image_id : 0,
			'url' => $image_url ? $image_url : '',
		);
	}

	public static function get_title($post_id = 0)
	{
		$post_id = $post_id ? absint($post_id) : self::get_context_post_id();
		if (!$post_id) {
			return '';
		}

		foreach (array('_ttpr_page_card_title', '_ttpc_page_card_title') as $meta_key) {
			$title = sanitize_text_field((string) get_post_meta($post_id, $meta_key, true));
			if ('' !== $title) {
				return $title;
			}
		}

		return sanitize_text_field((string) get_the_title($post_id));
	}

	public static function get_price($post_id = 0)
	{
		$post_id = $post_id ? absint($post_id) : self::get_context_post_id();
		if (!$post_id) {
			return '';
		}

		$price = '';
		foreach (array('_ttpr_page_price', '_ttpc_page_price') as $meta_key) {
			$price = sanitize_text_field((string) get_post_meta($post_id, $meta_key, true));
			if ('' !== $price) {
				break;
			}
		}

		if ('' === $price || preg_match('/฿|บาท/u', $price)) {
			return $price;
		}

		$normalized = str_replace(',', '', $price);
		if (is_numeric($normalized)) {
			$number = (float) $normalized;
			return '฿' . number_format_i18n($number, floor($number) === $number ? 0 : 2);
		}

		return '฿' . $price;
	}

	public static function get_url($post_id = 0)
	{
		$post_id = $post_id ? absint($post_id) : self::get_context_post_id();
		return $post_id ? get_permalink($post_id) : '';
	}
}

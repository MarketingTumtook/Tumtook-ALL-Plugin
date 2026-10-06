<?php
/**
 * Tag classes are loaded only while Elementor is registering dynamic tags.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Elementor_Tag_Product_Image extends \Elementor\Core\DynamicTags\Data_Tag
{
	public function get_name()
	{
		return 'tthpr-image';
	}

	public function get_title()
	{
		return __('Tumtook Product Image', 'tumtook-page-product-recommendations');
	}

	public function get_group()
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::GROUP;
	}

	public function get_categories()
	{
		return array(
			\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY,
		);
	}

	public function get_value(array $options = array())
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::get_image();
	}
}

final class Tumtook_Elementor_Tag_Product_Title extends \Elementor\Core\DynamicTags\Tag
{
	public function get_name()
	{
		return 'tthpr-product-title';
	}

	public function get_title()
	{
		return __('Tumtook Product Title', 'tumtook-page-product-recommendations');
	}

	public function get_group()
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::GROUP;
	}

	public function get_categories()
	{
		return array(\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY);
	}

	public function render()
	{
		echo esc_html(Tumtook_Elementor_Loop_Dynamic_Tags::get_title());
	}
}

final class Tumtook_Elementor_Tag_Product_Price extends \Elementor\Core\DynamicTags\Tag
{
	public function get_name()
	{
		return 'tthpr-price';
	}

	public function get_title()
	{
		return __('Tumtook Product Price', 'tumtook-page-product-recommendations');
	}

	public function get_group()
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::GROUP;
	}

	public function get_categories()
	{
		return array(\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY);
	}

	public function render()
	{
		echo esc_html(Tumtook_Elementor_Loop_Dynamic_Tags::get_price());
	}
}

final class Tumtook_Elementor_Tag_Product_Url extends \Elementor\Core\DynamicTags\Data_Tag
{
	public function get_name()
	{
		return 'tthpr-url';
	}

	public function get_title()
	{
		return __('Tumtook Product URL', 'tumtook-page-product-recommendations');
	}

	public function get_group()
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::GROUP;
	}

	public function get_categories()
	{
		return array(\Elementor\Modules\DynamicTags\Module::URL_CATEGORY);
	}

	public function get_value(array $options = array())
	{
		return Tumtook_Elementor_Loop_Dynamic_Tags::get_url();
	}
}

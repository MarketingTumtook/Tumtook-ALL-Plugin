<?php
/**
 * Elementor Loop Grid query controls for Tumtook product cards.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Tumtook_Elementor_Loop_Query
{
	const ORDERBY_CONTROL = 'post_query_orderby';
	const SELECTED_ORDER = 'post__in';

	public function __construct()
	{
		add_action(
			'elementor/element/loop-grid/section_query/before_section_end',
			array($this, 'add_selected_order_option'),
			20,
			2
		);
	}

	public function add_selected_order_option($element, $args = array())
	{
		if (!is_object($element) || !method_exists($element, 'get_controls') || !method_exists($element, 'update_control')) {
			return;
		}

		$control = $element->get_controls(self::ORDERBY_CONTROL);
		if (!is_array($control) || empty($control['options']) || !is_array($control['options'])) {
			return;
		}

		$options = array();
		foreach ($control['options'] as $value => $label) {
			$options[$value] = $label;
			if ('menu_order' === $value) {
				$options[self::SELECTED_ORDER] = __('Selected Order', 'tumtook-page-product-recommendations');
			}
		}

		if (!isset($options[self::SELECTED_ORDER])) {
			$options[self::SELECTED_ORDER] = __('Selected Order', 'tumtook-page-product-recommendations');
		}

		$element->update_control(self::ORDERBY_CONTROL, array('options' => $options));
	}
}

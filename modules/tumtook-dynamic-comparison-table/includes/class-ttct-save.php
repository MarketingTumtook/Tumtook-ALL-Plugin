<?php
/**
 * Data defaults and persistence.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTCT_Save {
	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'save_post_page', array( $this, 'save_page_meta' ), 10, 2 );
	}

	/**
	 * Default table structure.
	 */
	public static function default_data(): array {
		$economy_id  = 'column_' . wp_generate_uuid4();
		$standard_id = 'column_' . wp_generate_uuid4();
		$premium_id  = 'column_' . wp_generate_uuid4();

		return array(
			'enabled'     => true,
			'title'       => '',
			'description' => '',
			'settings'    => array(
				'mobile_mode' => 'scroll',
				'show_icons'  => true,
			),
			'columns'     => array(
				array(
					'id'               => $economy_id,
					'title'            => __( 'รุ่นประหยัด', 'tumtook-dynamic-comparison-table' ),
					'subtitle'         => __( 'เหมาะกับงานทั่วไป', 'tumtook-dynamic-comparison-table' ),
					'header_color'     => '#333333',
					'background_color' => '#ffffff',
					'text_color'       => '#222222',
					'featured'         => false,
					'badge'            => '',
					'badge_position'   => 'bottom',
					'button_text'      => __( 'สอบถามราคา', 'tumtook-dynamic-comparison-table' ),
					'button_url'       => '#',
					'button_new_tab'   => false,
					'visible'          => true,
				),
				array(
					'id'               => $standard_id,
					'title'            => __( 'รุ่นมาตรฐาน', 'tumtook-dynamic-comparison-table' ),
					'subtitle'         => __( 'สมดุลระหว่างราคาและคุณภาพ', 'tumtook-dynamic-comparison-table' ),
					'header_color'     => '#d90000',
					'background_color' => '#fff8f8',
					'text_color'       => '#222222',
					'featured'         => true,
					'badge'            => __( 'ยอดนิยม', 'tumtook-dynamic-comparison-table' ),
					'badge_position'   => 'top',
					'button_text'      => __( 'เลือกแพ็กเกจนี้', 'tumtook-dynamic-comparison-table' ),
					'button_url'       => '#',
					'button_new_tab'   => false,
					'visible'          => true,
				),
				array(
					'id'               => $premium_id,
					'title'            => __( 'รุ่นพรีเมียม', 'tumtook-dynamic-comparison-table' ),
					'subtitle'         => __( 'สำหรับงานที่ต้องการความทนทานสูง', 'tumtook-dynamic-comparison-table' ),
					'header_color'     => '#222222',
					'background_color' => '#ffffff',
					'text_color'       => '#222222',
					'featured'         => false,
					'badge'            => '',
					'badge_position'   => 'bottom',
					'button_text'      => __( 'ดูรายละเอียด', 'tumtook-dynamic-comparison-table' ),
					'button_url'       => '#',
					'button_new_tab'   => false,
					'visible'          => true,
				),
			),
			'rows'        => array(
				array(
					'id'         => 'row_' . wp_generate_uuid4(),
					'label'      => __( 'วัสดุ', 'tumtook-dynamic-comparison-table' ),
					'icon_type'  => 'dashicon',
					'icon_value' => 'dashicons-admin-tools',
					'icon_alt'   => '',
					'type'       => 'text',
					'visible'    => true,
					'values'     => array(
						$economy_id  => array( 'content' => __( 'วัสดุมาตรฐาน', 'tumtook-dynamic-comparison-table' ) ),
						$standard_id => array( 'content' => __( 'วัสดุเกรดดี', 'tumtook-dynamic-comparison-table' ) ),
						$premium_id  => array( 'content' => __( 'วัสดุเกรดพรีเมียม', 'tumtook-dynamic-comparison-table' ) ),
					),
				),
				array(
					'id'         => 'row_' . wp_generate_uuid4(),
					'label'      => __( 'ความทนทาน', 'tumtook-dynamic-comparison-table' ),
					'icon_type'  => 'dashicon',
					'icon_value' => 'dashicons-shield',
					'icon_alt'   => '',
					'type'       => 'text',
					'visible'    => true,
					'values'     => array(
						$economy_id  => array( 'content' => __( 'ใช้งานระยะสั้น', 'tumtook-dynamic-comparison-table' ) ),
						$standard_id => array( 'content' => __( 'ใช้งานได้ต่อเนื่อง', 'tumtook-dynamic-comparison-table' ) ),
						$premium_id  => array( 'content' => __( 'ทนทานเป็นพิเศษ', 'tumtook-dynamic-comparison-table' ) ),
					),
				),
				array(
					'id'         => 'row_' . wp_generate_uuid4(),
					'label'      => __( 'ราคาเริ่มต้น', 'tumtook-dynamic-comparison-table' ),
					'icon_type'  => 'dashicon',
					'icon_value' => 'dashicons-tag',
					'icon_alt'   => '',
					'type'       => 'price',
					'visible'    => true,
					'values'     => array(
						$economy_id  => array( 'content' => __( '฿990', 'tumtook-dynamic-comparison-table' ) ),
						$standard_id => array( 'content' => __( '฿1,490', 'tumtook-dynamic-comparison-table' ) ),
						$premium_id  => array( 'content' => __( '฿2,490', 'tumtook-dynamic-comparison-table' ) ),
					),
				),
				array(
					'id'         => 'row_' . wp_generate_uuid4(),
					'label'      => __( 'เหมาะสำหรับ', 'tumtook-dynamic-comparison-table' ),
					'icon_type'  => 'dashicon',
					'icon_value' => 'dashicons-groups',
					'icon_alt'   => '',
					'type'       => 'multiline',
					'visible'    => true,
					'values'     => array(
						$economy_id  => array( 'content' => __( "งานทดลอง\nงบจำกัด", 'tumtook-dynamic-comparison-table' ) ),
						$standard_id => array( 'content' => __( "งานขายหน้าร้าน\nอีเวนต์ทั่วไป", 'tumtook-dynamic-comparison-table' ) ),
						$premium_id  => array( 'content' => __( "งานแบรนด์\nใช้งานบ่อย", 'tumtook-dynamic-comparison-table' ) ),
					),
				),
				array(
					'id'         => 'row_' . wp_generate_uuid4(),
					'label'      => __( 'บริการออกแบบ', 'tumtook-dynamic-comparison-table' ),
					'icon_type'  => 'dashicon',
					'icon_value' => 'dashicons-art',
					'icon_alt'   => '',
					'type'       => 'yesno',
					'visible'    => true,
					'values'     => array(
						$economy_id  => array( 'content' => '0' ),
						$standard_id => array( 'content' => '1' ),
						$premium_id  => array( 'content' => '1' ),
					),
				),
			),
		);
	}

	/**
	 * Get table meta with defaults and normalized cell mapping.
	 */
	public static function get_data( int $post_id ): array {
		$raw = get_post_meta( $post_id, TTCT_META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return self::default_data();
		}

		return self::normalize_data( $raw );
	}

	/**
	 * Save page meta.
	 */
	public function save_page_meta( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['ttct_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ttct_nonce'] ) ), 'ttct_save_table' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || 'page' !== $post->post_type ) {
			return;
		}

		$payload = isset( $_POST['ttct_table_data'] ) ? wp_unslash( $_POST['ttct_table_data'] ) : '';
		if ( '' === $payload ) {
			delete_post_meta( $post_id, TTCT_META_KEY );
			return;
		}

		$decoded = json_decode( $payload, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		update_post_meta( $post_id, TTCT_META_KEY, self::sanitize_data( $decoded ) );
	}

	/**
	 * Sanitize a complete table payload.
	 */
	public static function sanitize_data( array $data ): array {
		$columns = array();
		foreach ( self::array_value( $data, 'columns' ) as $column ) {
			if ( ! is_array( $column ) ) {
				continue;
			}

			$id = self::sanitize_id( $column['id'] ?? '', 'column' );
			if ( '' === $id ) {
				$id = 'column_' . wp_generate_uuid4();
			}

			$columns[] = array(
				'id'               => $id,
				'title'            => sanitize_text_field( $column['title'] ?? '' ),
				'subtitle'         => sanitize_text_field( $column['subtitle'] ?? '' ),
				'header_color'     => self::sanitize_color( $column['header_color'] ?? '#333333', '#333333' ),
				'background_color' => self::sanitize_color( $column['background_color'] ?? '#ffffff', '#ffffff' ),
				'text_color'       => self::sanitize_color( $column['text_color'] ?? '#222222', '#222222' ),
				'featured'         => ! empty( $column['featured'] ),
				'badge'            => sanitize_text_field( $column['badge'] ?? '' ),
				'badge_position'   => in_array( $column['badge_position'] ?? 'bottom', array( 'top', 'bottom' ), true ) ? $column['badge_position'] : 'bottom',
				'button_text'      => sanitize_text_field( $column['button_text'] ?? '' ),
				'button_url'       => esc_url_raw( $column['button_url'] ?? '' ),
				'button_new_tab'   => ! empty( $column['button_new_tab'] ),
				'visible'          => array_key_exists( 'visible', $column ) ? ! empty( $column['visible'] ) : true,
			);
		}

		if ( empty( $columns ) ) {
			$columns = self::default_data()['columns'];
		}

		$column_ids = wp_list_pluck( $columns, 'id' );
		$rows       = array();

		foreach ( self::array_value( $data, 'rows' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row_id = self::sanitize_id( $row['id'] ?? '', 'row' );
			if ( '' === $row_id ) {
				$row_id = 'row_' . wp_generate_uuid4();
			}

			$type   = self::sanitize_type( $row['type'] ?? 'text' );
			$values = array();
			foreach ( $column_ids as $column_id ) {
				$cell_values          = isset( $row['values'][ $column_id ] ) && is_array( $row['values'][ $column_id ] ) ? $row['values'][ $column_id ] : array();
				$values[ $column_id ] = self::sanitize_cell( $cell_values, $type );
			}

			$rows[] = array(
				'id'         => $row_id,
				'label'      => sanitize_text_field( $row['label'] ?? '' ),
				'icon_type'  => in_array( $row['icon_type'] ?? 'none', array( 'none', 'dashicon', 'image' ), true ) ? $row['icon_type'] : 'none',
				'icon_value' => sanitize_text_field( $row['icon_value'] ?? '' ),
				'icon_alt'   => sanitize_text_field( $row['icon_alt'] ?? '' ),
				'type'       => $type,
				'visible'    => array_key_exists( 'visible', $row ) ? ! empty( $row['visible'] ) : true,
				'values'     => $values,
			);
		}

		if ( empty( $rows ) ) {
			$default_row = self::default_data()['rows'][0];
			$values      = array();
			foreach ( $column_ids as $column_id ) {
				$values[ $column_id ] = array( 'content' => '' );
			}
			$default_row['id']     = 'row_' . wp_generate_uuid4();
			$default_row['values'] = $values;
			$rows[]                = $default_row;
		}

		return self::normalize_data(
			array(
				'enabled'     => ! empty( $data['enabled'] ),
				'title'       => '',
				'description' => '',
				'settings'    => array(
					'mobile_mode' => 'scroll',
					'show_icons'  => true,
				),
				'columns'     => $columns,
				'rows'        => $rows,
			)
		);
	}

	/**
	 * Normalize defaults and ensure every row has one value per column ID.
	 */
	public static function normalize_data( array $data ): array {
		$default = self::default_data();
		$data    = wp_parse_args( $data, $default );

		$data['settings'] = wp_parse_args( is_array( $data['settings'] ) ? $data['settings'] : array(), $default['settings'] );
		unset( $data['settings']['table_class'] );
		$data['columns']  = is_array( $data['columns'] ) ? array_values( $data['columns'] ) : array();
		$data['rows']     = is_array( $data['rows'] ) ? array_values( $data['rows'] ) : array();

		if ( empty( $data['columns'] ) ) {
			$data['columns'] = $default['columns'];
		}

		if ( empty( $data['rows'] ) ) {
			$data['rows'] = $default['rows'];
		}

		$seen       = array();
		$column_ids = array();
		foreach ( $data['columns'] as $index => $column ) {
			if ( ! is_array( $column ) ) {
				unset( $data['columns'][ $index ] );
				continue;
			}
			unset( $column['css_class'] );
			$id = self::sanitize_id( $column['id'] ?? '', 'column' );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'column_' . wp_generate_uuid4();
			}
			$seen[ $id ]                 = true;
			$data['columns'][ $index ]   = wp_parse_args( array( 'id' => $id ) + $column, $default['columns'][0] );
			$column_ids[]                = $id;
		}
		$data['columns'] = array_values( $data['columns'] );

		foreach ( $data['rows'] as $index => $row ) {
			if ( ! is_array( $row ) ) {
				unset( $data['rows'][ $index ] );
				continue;
			}
			unset( $row['css_class'] );
			$row_id = self::sanitize_id( $row['id'] ?? '', 'row' );
			if ( '' === $row_id ) {
				$row_id = 'row_' . wp_generate_uuid4();
			}
			$type   = self::sanitize_type( $row['type'] ?? 'text' );
			$values = is_array( $row['values'] ?? null ) ? $row['values'] : array();
			$mapped = array();
			foreach ( $column_ids as $column_id ) {
				$mapped[ $column_id ] = isset( $values[ $column_id ] ) && is_array( $values[ $column_id ] ) ? $values[ $column_id ] : array( 'content' => '' );
			}
			$data['rows'][ $index ] = wp_parse_args(
				array(
					'id'     => $row_id,
					'type'   => $type,
					'values' => $mapped,
				) + $row,
				$default['rows'][0]
			);
		}
		$data['rows'] = array_values( $data['rows'] );

		return $data;
	}

	/**
	 * Sanitizes cell data by row type.
	 */
	private static function sanitize_cell( array $cell, string $type ): array {
		$content = $cell['content'] ?? '';

		switch ( $type ) {
			case 'multiline':
				return array( 'content' => sanitize_textarea_field( $content ) );
			case 'html':
				return array( 'content' => wp_kses_post( $content ) );
			case 'button':
				return array(
					'content' => sanitize_text_field( $content ),
					'url'     => esc_url_raw( $cell['url'] ?? '' ),
					'new_tab' => ! empty( $cell['new_tab'] ),
				);
			case 'image':
				return array(
					'content' => absint( $content ),
					'alt'     => sanitize_text_field( $cell['alt'] ?? '' ),
				);
			case 'check':
			case 'cross':
			case 'yesno':
				return array( 'content' => ! empty( $content ) ? '1' : '0' );
			default:
				return array( 'content' => sanitize_text_field( $content ) );
		}
	}

	/**
	 * Sanitize supported row type.
	 */
	private static function sanitize_type( string $type ): string {
		$allowed = array( 'text', 'multiline', 'price', 'check', 'cross', 'yesno', 'button', 'image', 'html', 'custom' );
		return in_array( $type, $allowed, true ) ? $type : 'text';
	}

	/**
	 * Sanitize generated IDs.
	 */
	private static function sanitize_id( string $id, string $prefix ): string {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
		if ( ! is_string( $id ) || ! str_starts_with( $id, $prefix . '_' ) ) {
			return '';
		}

		return $id;
	}

	/**
	 * Sanitize hex color with fallback.
	 */
	private static function sanitize_color( string $color, string $fallback ): string {
		$sanitized = sanitize_hex_color( $color );
		return $sanitized ? $sanitized : $fallback;
	}

	/**
	 * Read an array value.
	 */
	private static function array_value( array $data, string $key ): array {
		return is_array( $data[ $key ] ?? null ) ? $data[ $key ] : array();
	}
}

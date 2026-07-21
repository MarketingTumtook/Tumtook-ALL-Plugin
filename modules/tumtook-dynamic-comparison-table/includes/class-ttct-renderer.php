<?php
/**
 * Frontend renderer.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTCT_Renderer {
	/**
	 * Render a comparison table.
	 */
	public function render( int $post_id, array $args = array() ): string {
		$data = TTCT_Save::get_data( $post_id );
		if ( empty( $data['enabled'] ) ) {
			return '';
		}

		$columns = array_values( array_filter( $data['columns'], static fn( $column ) => ! empty( $column['visible'] ) ) );
		$rows    = array_values( array_filter( $data['rows'], static fn( $row ) => ! empty( $row['visible'] ) ) );
		if ( empty( $columns ) || empty( $rows ) ) {
			return '';
		}

		$args         = wp_parse_args(
			$args,
			array(
				'mobile_mode' => 'scroll',
			)
		);
		$instance_id  = 'ttct-' . wp_generate_uuid4();

		ob_start();
		?>
		<section id="<?php echo esc_attr( $instance_id ); ?>" class="tumtook-comparison tumtook-comparison--scroll" data-ttct-mobile-mode="scroll">
			<div class="tumtook-comparison__scroll">
				<table class="tumtook-comparison__table">
					<thead>
						<tr>
							<th scope="col" class="tumtook-comparison__feature-heading"><?php echo esc_html__( 'หัวข้อเปรียบเทียบ', 'tumtook-dynamic-comparison-table' ); ?></th>
							<?php foreach ( $columns as $column ) : ?>
								<th scope="col" class="<?php echo esc_attr( $this->column_classes( $column ) ); ?>" style="<?php echo esc_attr( $this->column_style( $column ) ); ?>">
									<?php $this->render_column_header( $column ); ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<th scope="row" class="tumtook-comparison__row-heading">
									<?php $this->render_row_label( $row, true ); ?>
								</th>
								<?php foreach ( $columns as $column ) : ?>
									<td class="<?php echo esc_attr( $this->cell_classes( $column ) ); ?>" data-ttct-column="<?php echo esc_attr( $column['id'] ); ?>">
										<?php $this->render_cell( $row['values'][ $column['id'] ] ?? array(), $row['type'] ?? 'text' ); ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render column header.
	 */
	private function render_column_header( array $column ): void {
		if ( ! empty( $column['badge'] ) && 'top' === ( $column['badge_position'] ?? 'bottom' ) ) {
			echo '<span class="tumtook-comparison__badge">' . esc_html( $column['badge'] ) . '</span>';
		}
		echo '<span class="tumtook-comparison__product-title">' . esc_html( $column['title'] ) . '</span>';
		if ( ! empty( $column['subtitle'] ) ) {
			echo '<span class="tumtook-comparison__product-subtitle">' . esc_html( $column['subtitle'] ) . '</span>';
		}
		if ( ! empty( $column['badge'] ) && 'bottom' === ( $column['badge_position'] ?? 'bottom' ) ) {
			echo '<span class="tumtook-comparison__badge">' . esc_html( $column['badge'] ) . '</span>';
		}
		$this->render_column_cta( $column );
	}

	/**
	 * Render row label with optional icon.
	 */
	private function render_row_label( array $row, bool $show_icons ): void {
		if ( $show_icons && ! empty( $row['icon_value'] ) ) {
			if ( 'dashicon' === ( $row['icon_type'] ?? '' ) ) {
				echo '<span class="dashicons ' . esc_attr( sanitize_html_class( $row['icon_value'] ) ) . '" aria-hidden="true"></span>';
			} elseif ( 'image' === ( $row['icon_type'] ?? '' ) ) {
				echo wp_get_attachment_image( absint( $row['icon_value'] ), 'thumbnail', false, array( 'alt' => esc_attr( $row['icon_alt'] ?? '' ) ) );
			}
		}
		echo '<span>' . esc_html( $row['label'] ?? '' ) . '</span>';
	}

	/**
	 * Render cell.
	 */
	private function render_cell( array $cell, string $type ): void {
		$content = $cell['content'] ?? '';
		if ( '' === (string) $content && ! in_array( $type, array( 'check', 'cross', 'yesno', 'image' ), true ) ) {
			echo '<span class="tumtook-comparison__empty">-</span>';
			return;
		}

		switch ( $type ) {
			case 'multiline':
				echo wp_kses_post( nl2br( esc_html( (string) $content ) ) );
				break;
			case 'price':
				echo '<strong class="tumtook-comparison__price">' . esc_html( (string) $content ) . '</strong>';
				break;
			case 'check':
				echo ! empty( $content ) ? '<span class="tumtook-comparison__mark tumtook-comparison__mark--check" aria-label="' . esc_attr__( 'ใช่', 'tumtook-dynamic-comparison-table' ) . '">✓</span>' : '<span class="tumtook-comparison__empty">-</span>';
				break;
			case 'cross':
				echo ! empty( $content ) ? '<span class="tumtook-comparison__mark tumtook-comparison__mark--cross" aria-label="' . esc_attr__( 'ไม่ใช่', 'tumtook-dynamic-comparison-table' ) . '">×</span>' : '<span class="tumtook-comparison__empty">-</span>';
				break;
			case 'yesno':
				echo ! empty( $content ) ? esc_html__( 'ใช่', 'tumtook-dynamic-comparison-table' ) : esc_html__( 'ไม่ใช่', 'tumtook-dynamic-comparison-table' );
				break;
			case 'button':
				if ( empty( $cell['url'] ) ) {
					echo esc_html( (string) $content );
					break;
				}
				printf(
					'<a class="tumtook-comparison__button" href="%1$s" %2$s aria-label="%3$s">%4$s</a>',
					esc_url( $cell['url'] ),
					! empty( $cell['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : '',
					esc_attr( (string) $content ),
					esc_html( (string) $content )
				);
				break;
			case 'image':
				echo wp_get_attachment_image( absint( $content ), 'medium', false, array( 'alt' => esc_attr( $cell['alt'] ?? '' ) ) ) ?: '<span class="tumtook-comparison__empty">-</span>';
				break;
			case 'html':
				echo wp_kses_post( (string) $content );
				break;
			default:
				echo esc_html( (string) $content );
		}
	}

	/**
	 * Render column CTA.
	 */
	private function render_column_cta( array $column ): void {
		if ( empty( $column['button_text'] ) || empty( $column['button_url'] ) ) {
			return;
		}
		printf(
			'<a class="tumtook-comparison__cta" href="%1$s" %2$s aria-label="%3$s">%4$s</a>',
			esc_url( $column['button_url'] ),
			! empty( $column['button_new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : '',
			esc_attr( $column['button_text'] ),
			esc_html( $column['button_text'] )
		);
	}

	/**
	 * Column classes.
	 */
	private function column_classes( array $column ): string {
		$classes = array( 'tumtook-comparison__product' );
		if ( ! empty( $column['featured'] ) ) {
			$classes[] = 'is-featured';
		}
		return implode( ' ', $classes );
	}

	/**
	 * Cell classes.
	 */
	private function cell_classes( array $column ): string {
		$classes = array( 'tumtook-comparison__cell' );
		if ( ! empty( $column['featured'] ) ) {
			$classes[] = 'is-featured';
		}
		return implode( ' ', $classes );
	}

	/**
	 * Limited column styles from sanitized colors.
	 */
	private function column_style( array $column ): string {
		$header = sanitize_hex_color( $column['header_color'] ?? '' ) ?: '#333333';
		$bg     = sanitize_hex_color( $column['background_color'] ?? '' ) ?: '#ffffff';
		$text   = sanitize_hex_color( $column['text_color'] ?? '' ) ?: '#222222';
		return "--tt-column-header: {$header}; --tt-column-bg: {$bg}; --tt-column-text: {$text};";
	}
}

<?php
/**
 * Admin UI.
 *
 * @package TumtookDynamicComparisonTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTCT_Admin {
	/**
	 * Renderer.
	 *
	 * @var TTCT_Renderer
	 */
	private TTCT_Renderer $renderer;

	/**
	 * Constructor.
	 */
	public function __construct( TTCT_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_page', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'ttct_comparison_table',
			__( 'ตารางเปรียบเทียบสินค้า', 'tumtook-dynamic-comparison-table' ),
			array( $this, 'render_meta_box' ),
			'page',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue admin assets only on Page editor.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'ttct-admin', TTCT_URL . 'assets/css/admin.css', array( 'dashicons' ), TTCT_VERSION );
		wp_enqueue_script( 'ttct-admin', TTCT_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), TTCT_VERSION, true );
		wp_localize_script(
			'ttct-admin',
			'TTCTAdmin',
			array(
				'confirmDeleteRow'    => __( 'ต้องการลบแถวนี้หรือไม่?', 'tumtook-dynamic-comparison-table' ),
				'confirmDeleteColumn' => __( 'ต้องการลบคอลัมน์นี้หรือไม่? ข้อมูลในคอลัมน์นี้จะถูกลบด้วย', 'tumtook-dynamic-comparison-table' ),
				'confirmReset'        => __( 'ต้องการ Reset ตารางทั้งหมดหรือไม่?', 'tumtook-dynamic-comparison-table' ),
				'mediaTitle'          => __( 'เลือกไอคอนหรือรูปภาพ', 'tumtook-dynamic-comparison-table' ),
				'mediaButton'         => __( 'ใช้รูปนี้', 'tumtook-dynamic-comparison-table' ),
				'defaultData'         => TTCT_Save::default_data(),
			)
		);
	}

	/**
	 * Render meta box.
	 */
	public function render_meta_box( WP_Post $post ): void {
		$data    = TTCT_Save::get_data( (int) $post->ID );
		$preview = $this->renderer->render( (int) $post->ID );
		wp_nonce_field( 'ttct_save_table', 'ttct_nonce' );
		?>
		<div class="ttct-builder" data-ttct-builder>
			<input type="hidden" name="ttct_table_data" data-ttct-json value="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">

			<div class="ttct-toolbar">
				<label class="ttct-switch">
					<input type="checkbox" data-ttct-path="enabled" <?php checked( ! empty( $data['enabled'] ) ); ?>>
					<span><?php esc_html_e( 'เปิดแสดงตาราง', 'tumtook-dynamic-comparison-table' ); ?></span>
				</label>
				<button type="button" class="button ttct-btn ttct-btn--primary" data-ttct-add-row><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'เพิ่มแถว', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn ttct-btn--primary" data-ttct-add-column><span class="dashicons dashicons-columns" aria-hidden="true"></span><?php esc_html_e( 'เพิ่มคอลัมน์', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn" data-ttct-preview-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'Preview', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn" data-ttct-collapse><span class="dashicons dashicons-editor-contract" aria-hidden="true"></span><?php esc_html_e( 'ย่อ/ขยาย', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn" data-ttct-duplicate><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Duplicate ตาราง', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn ttct-btn--soft" data-ttct-load-sample><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><?php esc_html_e( 'โหลดข้อมูลตัวอย่าง', 'tumtook-dynamic-comparison-table' ); ?></button>
				<button type="button" class="button ttct-btn ttct-btn--danger" data-ttct-reset><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Reset ตาราง', 'tumtook-dynamic-comparison-table' ); ?></button>
			</div>
			<p class="ttct-help ttct-help--top"><?php esc_html_e( 'เริ่มจากเพิ่มคอลัมน์เป็นรุ่นสินค้า แล้วเพิ่มแถวเป็นหัวข้อเปรียบเทียบ ระบบจะผูกข้อมูลแต่ละช่องด้วย Column ID จึงลากเรียงได้โดยข้อมูลไม่สลับกัน', 'tumtook-dynamic-comparison-table' ); ?></p>

			<div class="ttct-workspace" data-ttct-workspace></div>

			<div class="ttct-preview" data-ttct-preview hidden>
				<h3><?php esc_html_e( 'Preview ล่าสุดที่บันทึกแล้ว', 'tumtook-dynamic-comparison-table' ); ?></h3>
				<?php echo $preview ? wp_kses_post( $preview ) : '<p>' . esc_html__( 'ยังไม่มีข้อมูลสำหรับ Preview', 'tumtook-dynamic-comparison-table' ) . '</p>'; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Tumtook Comparison', 'tumtook-dynamic-comparison-table' ),
			__( 'Tumtook Comparison', 'tumtook-dynamic-comparison-table' ),
			'manage_options',
			'ttct-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'ttct_settings',
			TTCT_DELETE_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $value ) => ! empty( $value ),
				'default'           => false,
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tumtook Dynamic Comparison Table', 'tumtook-dynamic-comparison-table' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ttct_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'tumtook-dynamic-comparison-table' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( TTCT_DELETE_OPTION ); ?>" value="1" <?php checked( (bool) get_option( TTCT_DELETE_OPTION, false ) ); ?>>
								<?php esc_html_e( 'ลบข้อมูลทั้งหมดเมื่อถอนการติดตั้ง', 'tumtook-dynamic-comparison-table' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

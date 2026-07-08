<?php
/**
 * Plugin Name: Tumtook Page Product Cards
 * Description: Adds a page-based product card slider with manual page selection and a no-price layout for Tumtook landing pages.
 * Version: 1.0.2
 * Author: Tumtook
 * Text Domain: tumtook-page-product-cards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tumtook_Page_Product_Cards {
	const VERSION = '1.0.2';
	const META_KEY            = '_tt_page_product_cards';
	const PAGE_IMAGE_META     = '_ttpc_page_image_id';
	const PAGE_TITLE_META     = '_ttpc_page_card_title';
	const CACHE_VERSION_OPTION = '_ttpc_cache_version';
	const SHORTCODE           = 'tumtook_product_cards';
	const FONT_HANDLE         = 'tumtook-kanit-font';

	private $rendered_posts = array();

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	public function register_meta_box() {
		add_meta_box(
			'tt-page-product-cards',
			__( 'Product Cards', 'tumtook-page-product-cards' ),
			array( $this, 'render_meta_box' ),
			'page',
			'normal',
			'default'
		);
	}

	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( ( 'post.php' !== $hook && 'post-new.php' !== $hook ) || 'page' !== $post_type ) {
			return;
		}

		$this->enqueue_kanit_font();

		wp_enqueue_media();
		wp_enqueue_script(
			'ttpc-admin',
			plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->get_asset_version( 'assets/js/admin.js' ),
			true
		);
	}

	public function register_front_assets() {
		$this->register_kanit_font();

		wp_register_style(
			'tt-page-product-cards',
			plugin_dir_url( __FILE__ ) . 'assets/css/front.css',
			array( self::FONT_HANDLE ),
			$this->get_asset_version( 'assets/css/front.css' )
		);

		wp_register_script(
			'tt-page-product-cards',
			plugin_dir_url( __FILE__ ) . 'assets/js/front.js',
			array(),
			$this->get_asset_version( 'assets/js/front.js' ),
			true
		);
	}

	private function get_default_settings() {
		return array(
			'enabled'          => '1',
			'auto_display'     => '0',
			'title'            => __( 'สินค้าเพิ่มเติม', 'tumtook-page-product-cards' ),
			'view_all_label'   => __( 'สินค้าสร้างรายได้', 'tumtook-page-product-cards' ),
			'view_all_url'     => '',
			'button_label'     => __( 'ดูสินค้า', 'tumtook-page-product-cards' ),
			'limit'            => '0',
			'related_page_ids' => '',
		);
	}

	private function enqueue_kanit_font() {
		$this->register_kanit_font();
		wp_enqueue_style( self::FONT_HANDLE );
	}

	private function register_kanit_font() {
		if ( function_exists( 'tumtook_aio_register_kanit_font' ) ) {
			tumtook_aio_register_kanit_font( self::FONT_HANDLE );
			return;
		}

		wp_register_style(
			self::FONT_HANDLE,
			'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap',
			array(),
			null
		);
	}

	private function get_asset_version( $relative_path ) {
		if ( function_exists( 'tumtook_aio_asset_version' ) ) {
			return tumtook_aio_asset_version( __FILE__, $relative_path );
		}

		return '1.0.0';
	}

	private function get_settings( $post_id ) {
		$saved    = get_post_meta( $post_id, self::META_KEY, true );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_default_settings() );

		$settings['enabled']          = ! empty( $settings['enabled'] ) ? '1' : '0';
		$settings['auto_display']     = '0';
		$settings['title']            = sanitize_text_field( $settings['title'] );
		$settings['view_all_label']   = sanitize_text_field( $settings['view_all_label'] );
		$settings['view_all_url']     = esc_url_raw( $settings['view_all_url'] );
		$settings['button_label']     = sanitize_text_field( $settings['button_label'] );
		$settings['limit']            = (string) max( 0, min( 99, absint( $settings['limit'] ) ) );
		$settings['related_page_ids'] = sanitize_text_field( $settings['related_page_ids'] );

		return $settings;
	}

	private function has_saved_settings( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $saved ) && ! empty( $saved );
	}

	private function get_page_card_meta( $post_id ) {
		$image_id = absint( get_post_meta( $post_id, self::PAGE_IMAGE_META, true ) );

		return array(
			'image_id' => $image_id,
			'image'    => $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '',
			'title'    => sanitize_text_field( (string) get_post_meta( $post_id, self::PAGE_TITLE_META, true ) ),
		);
	}

	public function render_meta_box( $post ) {
		$settings       = $this->get_settings( $post->ID );
		$page_meta      = $this->get_page_card_meta( $post->ID );
		$selected_ids   = $this->parse_id_list( $settings['related_page_ids'] );
		$available_pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
				'exclude'     => array( $post->ID ),
			)
		);

		wp_nonce_field( 'tt_page_product_cards_save', 'tt_page_product_cards_nonce' );
		?>
			<style>
				.ttpc-admin-wrap,.ttpc-admin-wrap button,.ttpc-admin-wrap input,.ttpc-admin-wrap select,.ttpc-admin-wrap textarea{font-family:"Kanit",sans-serif}
				.ttpc-admin-wrap{display:grid;gap:18px}
				.ttpc-admin-note{margin:0;color:#50575e}
				.ttpc-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));column-gap:16px;row-gap:14px;align-items:start}
				.ttpc-admin-field{display:grid;gap:6px}
				.ttpc-admin-field--full{grid-column:1/-1}
				.ttpc-admin-field label{font-weight:600}
				.ttpc-admin-field input,.ttpc-admin-field select,.ttpc-admin-field textarea{width:100%;max-width:none;box-sizing:border-box}
				.ttpc-admin-field input[type="number"]{-moz-appearance:textfield}
				.ttpc-admin-checklist{display:grid;gap:8px}
				.ttpc-admin-hint{margin:0;min-height:32px;color:#646970;font-size:12px;line-height:1.35}
				.ttpc-admin-panel{border:1px solid #dcdcde;border-radius:16px;padding:16px;background:#fff}
				.ttpc-admin-panel h3{margin:0 0 12px;font-size:15px}
				.ttpc-admin-image-preview img{max-width:160px;height:auto;border-radius:16px;display:block}
				.ttpc-admin-button{width:max-content}
				.ttpc-selected-pages{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
				.ttpc-page-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #dcdcde;border-radius:999px;background:#f6f7f7}
				.ttpc-page-chip__label{font-size:13px;line-height:1.2}
				.ttpc-page-chip__remove{border:0;background:transparent;color:#6b7280;font-size:18px;line-height:1;cursor:pointer;padding:0}
				@media (max-width:782px){.ttpc-admin-grid{grid-template-columns:1fr}.ttpc-admin-hint{min-height:0}}
			</style>
		<div class="ttpc-admin-wrap">
			<div class="ttpc-admin-panel">
				<h3><?php esc_html_e( 'ข้อมูลการ์ดของ Page นี้', 'tumtook-page-product-cards' ); ?></h3>
				<p class="ttpc-admin-note"><?php esc_html_e( 'ข้อมูลส่วนนี้จะถูกใช้เมื่อ page นี้ถูกเลือกไปแสดงใน section สินค้าเพิ่มเติมของหน้าอื่น', 'tumtook-page-product-cards' ); ?></p>
				<div class="ttpc-admin-grid" style="margin-top:16px">
					<div class="ttpc-admin-field ttpc-admin-field--full">
						<label for="ttpc-page-card-title"><?php esc_html_e( 'ชื่อที่แสดงบนการ์ด', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-page-card-title" type="text" name="ttpc_page_meta[title]" value="<?php echo esc_attr( $page_meta['title'] ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
						<p class="ttpc-admin-hint"><?php esc_html_e( 'ถ้าเว้นว่างไว้ ปลั๊กอินจะใช้ Title ของ page นี้แทน', 'tumtook-page-product-cards' ); ?></p>
					</div>
					<div class="ttpc-admin-field ttpc-admin-field--full">
						<label><?php esc_html_e( 'รูปการ์ดของ page นี้', 'tumtook-page-product-cards' ); ?></label>
						<input type="hidden" id="ttpc-page-image-id" name="ttpc_page_meta[image_id]" value="<?php echo esc_attr( $page_meta['image_id'] ); ?>" />
						<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
							<button type="button" class="button button-secondary ttpc-admin-button" id="ttpc-page-image-select"><?php esc_html_e( 'เลือกภาพ', 'tumtook-page-product-cards' ); ?></button>
							<button type="button" class="button button-link-delete ttpc-admin-button" id="ttpc-page-image-clear"><?php esc_html_e( 'ลบภาพ', 'tumtook-page-product-cards' ); ?></button>
						</div>
						<div class="ttpc-admin-image-preview" style="margin-top:10px">
							<img id="ttpc-page-image-preview" src="<?php echo esc_url( $page_meta['image'] ); ?>" alt="" <?php echo empty( $page_meta['image'] ) ? 'style="display:none"' : ''; ?> />
						</div>
						<p class="ttpc-admin-hint"><?php esc_html_e( 'ถ้าไม่ได้เลือกรูป ปลั๊กอินจะใช้ Featured Image ของ page นี้แทน', 'tumtook-page-product-cards' ); ?></p>
					</div>
				</div>
			</div>

			<div class="ttpc-admin-panel">
				<h3><?php esc_html_e( 'Section สินค้าเพิ่มเติมของหน้านี้', 'tumtook-page-product-cards' ); ?></h3>
				<p class="ttpc-admin-note"><?php esc_html_e( 'เลือกว่าจะให้หน้านี้แสดงสินค้าเพิ่มเติมจาก page ไหนบ้าง โดยใส่ ID ของ page ที่ต้องการแสดง', 'tumtook-page-product-cards' ); ?></p>

				<div class="ttpc-admin-checklist" style="margin-top:16px">
					<label><input type="checkbox" name="ttpc_settings[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?> /> <?php esc_html_e( 'เปิดใช้งาน section นี้', 'tumtook-page-product-cards' ); ?></label>
				</div>

				<div class="ttpc-admin-grid" style="margin-top:16px">
					<div class="ttpc-admin-field">
						<label for="ttpc-title"><?php esc_html_e( 'หัวข้อ', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-title" type="text" name="ttpc_settings[title]" value="<?php echo esc_attr( $settings['title'] ); ?>" />
					</div>
					<div class="ttpc-admin-field">
						<label for="ttpc-limit"><?php esc_html_e( 'จำนวนการ์ด', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-limit" type="number" min="0" max="99" name="ttpc_settings[limit]" value="<?php echo esc_attr( $settings['limit'] ); ?>" />
						<p class="ttpc-admin-hint"><?php esc_html_e( 'ใส่ 0 เพื่อดึงทุกการ์ดที่เลือกไว้ทั้งหมด หากไม่ต้องการให้แสดง section นี้ ให้เอาติ๊ก "เปิดใช้งาน section นี้" ออกแทน', 'tumtook-page-product-cards' ); ?></p>
					</div>
					<div class="ttpc-admin-field">
						<label for="ttpc-view-all-label"><?php esc_html_e( 'ข้อความลิงก์ทั้งหมด', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-view-all-label" type="text" name="ttpc_settings[view_all_label]" value="<?php echo esc_attr( $settings['view_all_label'] ); ?>" />
					</div>
					<div class="ttpc-admin-field">
						<label for="ttpc-view-all-url"><?php esc_html_e( 'ลิงก์ทั้งหมด', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-view-all-url" type="url" name="ttpc_settings[view_all_url]" value="<?php echo esc_attr( $settings['view_all_url'] ); ?>" />
					</div>
					<div class="ttpc-admin-field">
						<label for="ttpc-button-label"><?php esc_html_e( 'ข้อความปุ่ม', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-button-label" type="text" name="ttpc_settings[button_label]" value="<?php echo esc_attr( $settings['button_label'] ); ?>" />
					</div>
					<div class="ttpc-admin-field">
						<label for="ttpc-section-state"><?php esc_html_e( 'การเปิดใช้งาน', 'tumtook-page-product-cards' ); ?></label>
						<input id="ttpc-section-state" type="text" value="<?php echo '1' === $settings['enabled'] ? esc_attr__( 'เปิดใช้งานอยู่', 'tumtook-page-product-cards' ) : esc_attr__( 'ปิดการใช้งานอยู่', 'tumtook-page-product-cards' ); ?>" readonly />
						<p class="ttpc-admin-hint"><?php esc_html_e( 'หากไม่ต้องการให้แสดง section นี้ ให้เอาติ๊ก "เปิดใช้งาน section นี้" ออกได้เลย', 'tumtook-page-product-cards' ); ?></p>
					</div>
					<div class="ttpc-admin-field ttpc-admin-field--full">
						<label for="ttpc-related-page-picker"><?php esc_html_e( 'Page ที่ต้องการแสดง', 'tumtook-page-product-cards' ); ?></label>
						<input type="hidden" id="ttpc-related-page-ids" name="ttpc_settings[related_page_ids]" value="<?php echo esc_attr( $settings['related_page_ids'] ); ?>" />
						<select id="ttpc-related-page-picker">
							<option value=""><?php esc_html_e( 'เลือก page ที่ต้องการเพิ่ม', 'tumtook-page-product-cards' ); ?></option>
							<?php foreach ( $available_pages as $available_page ) : ?>
								<option value="<?php echo esc_attr( $available_page->ID ); ?>"><?php echo esc_html( get_the_title( $available_page->ID ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="ttpc-selected-pages" id="ttpc-selected-pages">
							<?php foreach ( $selected_ids as $selected_id ) : ?>
								<?php $selected_title = get_the_title( $selected_id ); ?>
								<?php if ( ! $selected_title ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<span class="ttpc-page-chip" data-page-id="<?php echo esc_attr( $selected_id ); ?>">
									<span class="ttpc-page-chip__label"><?php echo esc_html( $selected_title ); ?></span>
									<button type="button" class="ttpc-page-chip__remove" aria-label="<?php esc_attr_e( 'Remove page', 'tumtook-page-product-cards' ); ?>">×</button>
								</span>
							<?php endforeach; ?>
						</div>
						<p class="ttpc-admin-hint"><?php esc_html_e( 'เลือกชื่อ page จาก dropdown ได้เลย และกด x เพื่อลบออกจากรายการ', 'tumtook-page-product-cards' ); ?></p>
					</div>
				</div>

				<p class="ttpc-admin-note"><?php esc_html_e( 'Shortcode ที่ใช้ได้: [tumtook_product_cards]', 'tumtook-page-product-cards' ); ?></p>
			</div>
		</div>

		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['tt_page_product_cards_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tt_page_product_cards_nonce'] ) ), 'tt_page_product_cards_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['ttpc_settings'] ) ? wp_unslash( $_POST['ttpc_settings'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$settings = $this->get_default_settings();
		$settings['enabled']          = ! empty( $raw['enabled'] ) ? '1' : '0';
		$settings['auto_display']     = '0';
		$settings['title']            = isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : $settings['title'];
		$settings['view_all_label']   = isset( $raw['view_all_label'] ) ? sanitize_text_field( $raw['view_all_label'] ) : $settings['view_all_label'];
		$settings['view_all_url']     = isset( $raw['view_all_url'] ) ? esc_url_raw( $raw['view_all_url'] ) : '';
		$settings['button_label']     = isset( $raw['button_label'] ) ? sanitize_text_field( $raw['button_label'] ) : $settings['button_label'];
		$settings['limit']            = (string) max( 0, min( 99, absint( isset( $raw['limit'] ) ? $raw['limit'] : 0 ) ) );
		$settings['related_page_ids'] = isset( $raw['related_page_ids'] ) ? sanitize_text_field( $raw['related_page_ids'] ) : '';
		update_post_meta( $post_id, self::META_KEY, $settings );

		$page_meta = isset( $_POST['ttpc_page_meta'] ) ? wp_unslash( $_POST['ttpc_page_meta'] ) : array();
		if ( ! is_array( $page_meta ) ) {
			$page_meta = array();
		}

		update_post_meta( $post_id, self::PAGE_IMAGE_META, isset( $page_meta['image_id'] ) ? absint( $page_meta['image_id'] ) : 0 );
		update_post_meta( $post_id, self::PAGE_TITLE_META, isset( $page_meta['title'] ) ? sanitize_text_field( $page_meta['title'] ) : '' );
		$this->bump_cache_version();
	}

	public function append_to_content( $content ) {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id  = get_the_ID();
		$settings = $this->get_settings( $post_id );

		if ( ! $this->has_saved_settings( $post_id ) || '1' !== $settings['enabled'] || '1' !== $settings['auto_display'] || in_array( $post_id, $this->rendered_posts, true ) ) {
			return $content;
		}

		$section = $this->render_section( $post_id, $settings );
		return $section ? $content . $section : $content;
	}

	public function render_shortcode( $atts = array(), $content = '', $tag = '' ) {
		$this->register_front_assets();

		$is_editor_preview = $this->is_editor_preview_context();

		$atts = shortcode_atts(
			array(
				'page_id' => 0,
			),
			$atts,
			$tag ? $tag : self::SHORTCODE
		);

		$post_id = absint( $atts['page_id'] );

		if ( ! $post_id ) {
			$post_id = get_queried_object_id();
		}

		if ( ! $post_id && is_singular() ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return $is_editor_preview ? $this->render_section( 0, $this->get_default_settings(), true ) : '';
		}

		$settings = $this->get_settings( $post_id );
		$has_saved_settings = $this->has_saved_settings( $post_id );

		if ( ! $has_saved_settings ) {
			return $is_editor_preview ? $this->render_section( $post_id, $settings, true ) : '';
		}

		if ( '1' !== $settings['enabled'] ) {
			return $is_editor_preview ? $this->render_section( $post_id, $settings, true ) : '';
		}

		return $this->render_section( $post_id, $settings, $is_editor_preview );
	}

	private function render_section( $post_id, $settings, $force_placeholder = false ) {
		$this->register_front_assets();

		$items               = $this->get_recommended_pages( $settings );
		$using_placeholders  = false;

			if ( empty( $items ) ) {
				$items = $this->get_placeholder_items( $settings );
				$using_placeholders = true;
			}

		$this->rendered_posts[] = $post_id;

		wp_enqueue_style( 'tt-page-product-cards' );
		wp_enqueue_script( 'tt-page-product-cards' );

		$instance_id = 'ttpc-' . ( $post_id ? $post_id : 'preview' ) . '-' . wp_rand( 100, 999 );
		$show_controls = count( $items ) > 3;

		ob_start();
		?>
		<section class="ttpc-section" data-ttpc-slider id="<?php echo esc_attr( $instance_id ); ?>">
			<div class="ttpc-shell">
				<?php if ( ! empty( $settings['title'] ) || ! empty( $settings['view_all_url'] ) ) : ?>
					<div class="ttpc-header">
						<?php if ( ! empty( $settings['title'] ) ) : ?>
							<h2 class="ttpc-title"><?php echo esc_html( $settings['title'] ); ?></h2>
						<?php endif; ?>
						<?php if ( ! empty( $settings['view_all_url'] ) ) : ?>
							<a class="ttpc-view-all" href="<?php echo esc_url( $settings['view_all_url'] ); ?>">
								<?php echo esc_html( $settings['view_all_label'] ); ?>
								<span class="ttpc-view-all-icon" aria-hidden="true">&rsaquo;</span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $using_placeholders && $this->is_editor_preview_context() ) : ?>
					<div class="ttpc-preview-note"><?php esc_html_e( 'ตอนนี้ยังไม่มี page ที่ถูกเลือกไว้ จึงแสดงตัวอย่าง layout ให้ก่อน', 'tumtook-page-product-cards' ); ?></div>
				<?php endif; ?>

				<div class="ttpc-track-wrap">
					<div class="ttpc-track" data-ttpc-track>
						<?php foreach ( $items as $item ) : ?>
							<article class="ttpc-card">
								<div class="ttpc-image-link<?php echo empty( $item['image'] ) ? ' ttpc-image-link--missing' : ''; ?>">
									<?php if ( ! empty( $item['image'] ) ) : ?>
										<img class="ttpc-image" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy" onerror="this.style.display='none';this.parentNode.classList.add('ttpc-image-link--missing');" />
									<?php endif; ?>
									<div class="ttpc-image ttpc-image--placeholder" aria-hidden="true">
										<div class="ttpc-image-fallback">
											<span class="ttpc-image-fallback-badge">NO IMAGE</span>
											<div class="ttpc-image-fallback-box"></div>
											<div class="ttpc-image-fallback-lines">
												<span></span>
												<span></span>
												<span></span>
											</div>
										</div>
									</div>
								</div>
								<div class="ttpc-content">
									<h3 class="ttpc-product-title"><?php echo esc_html( $item['title'] ); ?></h3>
									<div class="ttpc-footer">
										<a class="ttpc-button" href="<?php echo esc_url( $item['url'] ); ?>">
											<span class="ttpc-button-arrow" aria-hidden="true"></span>
											<span class="ttpc-button-label"><?php echo esc_html( $settings['button_label'] ); ?></span>
										</a>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ( $show_controls ) : ?>
					<div class="ttpc-controls">
						<div class="ttpc-pagination" data-ttpc-pagination></div>
						<div class="ttpc-arrows">
							<button type="button" class="ttpc-arrow ttpc-arrow--prev" data-ttpc-prev aria-label="<?php esc_attr_e( 'Previous cards', 'tumtook-page-product-cards' ); ?>">
								<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
									<path d="M14.5 6.5L9 12l5.5 5.5" />
								</svg>
							</button>
							<button type="button" class="ttpc-arrow ttpc-arrow--next" data-ttpc-next aria-label="<?php esc_attr_e( 'Next cards', 'tumtook-page-product-cards' ); ?>">
								<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
									<path d="M9.5 6.5L15 12l-5.5 5.5" />
								</svg>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	private function get_recommended_pages( $settings ) {
		$page_ids = $this->parse_id_list( $settings['related_page_ids'] );
		$limit    = isset( $settings['limit'] ) ? absint( $settings['limit'] ) : 0;
		$cache_key = 'ttpc_pages_' . md5( wp_json_encode( $settings ) . '|' . $limit . '|' . self::VERSION . '|' . $this->get_cache_version() );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		if ( $limit > 0 ) {
			$page_ids = array_slice( $page_ids, 0, $limit );
		}

		if ( empty( $page_ids ) ) {
			return array();
		}

		$this->prime_page_caches( $page_ids );

		$items = array();

		foreach ( $page_ids as $page_id ) {
			$page = get_post( $page_id );

			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				continue;
			}

			$meta  = $this->get_page_card_meta( $page_id );
			$image = $meta['image'] ? $meta['image'] : get_the_post_thumbnail_url( $page_id, 'large' );
			$title = '' !== $meta['title'] ? $meta['title'] : get_the_title( $page_id );

			$items[] = array(
				'title' => $title,
				'url'   => get_permalink( $page_id ),
				'image' => $image ? $image : '',
			);
		}

		set_transient( $cache_key, $items, 10 * MINUTE_IN_SECONDS );

		return $items;
	}

	private function get_placeholder_items( $settings ) {
		$limit = max( 2, min( 6, (int) $settings['limit'] ) );
		$items = array();

		for ( $i = 1; $i <= $limit; $i++ ) {
			$items[] = array(
				'title' => sprintf( __( 'ตัวอย่างสินค้า %d', 'tumtook-page-product-cards' ), $i ),
				'url'   => '#',
				'image' => '',
			);
		}

		return $items;
	}

	private function parse_id_list( $raw_ids ) {
		$ids = preg_split( '/[\s,]+/', (string) $raw_ids );
		$ids = array_filter( array_map( 'absint', $ids ) );
		return array_values( array_unique( $ids ) );
	}

	private function prime_page_caches( $page_ids ) {
		$page_ids = array_values( array_filter( array_map( 'absint', (array) $page_ids ) ) );
		if ( empty( $page_ids ) ) {
			return;
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $page_ids, false, true );
			return;
		}

		update_meta_cache( 'post', $page_ids );
	}

	private function is_editor_preview_context() {
		if ( is_admin() ) {
			return true;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && isset( $elementor->editor, $elementor->preview ) && ( $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode() ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_cache_version() {
		return (string) max( 1, absint( get_option( self::CACHE_VERSION_OPTION, 1 ) ) );
	}

	private function bump_cache_version() {
		$version = absint( get_option( self::CACHE_VERSION_OPTION, 1 ) );
		$version = $version > 0 ? $version + 1 : 2;

		update_option( self::CACHE_VERSION_OPTION, $version, false );
	}
}

new Tumtook_Page_Product_Cards();

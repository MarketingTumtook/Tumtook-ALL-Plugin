<?php
/** Independent code-driven gallery. Shortcode: [tumtook_gallery_code code="VN"]. */
if (!defined('ABSPATH')) exit;

final class Tumtook_Gallery_Code
{
    const VERSION = '1.0.3';
    const OPTION = 'tumtook_gallery_code_settings';
    const SHORTCODE = 'tumtook_gallery_code';
    const LIMIT = 50;

    public function __construct()
    {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_diagnostics_box'), 10, 2);
        add_action('wp_ajax_ttgc_diagnostics', array($this, 'ajax_diagnostics'));
        add_action('wp_ajax_ttgc_test_api', array($this, 'ajax_test_api'));
    }

    private function settings()
    {
        $stored = get_option(self::OPTION, array());
        return wp_parse_args(is_array($stored) ? $stored : array(), array('api_url' => '', 'cache_minutes' => 30));
    }

    public function admin_menu()
    {
        add_options_page('Tumtook Gallery Code', 'Tumtook Gallery Code', 'manage_options', 'tumtook-gallery-code', array($this, 'settings_page'));
    }

    public function register_settings()
    {
        register_setting(self::OPTION, self::OPTION, array('sanitize_callback' => array($this, 'sanitize_settings')));
    }

    public function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : array();
        return array(
            'api_url' => esc_url_raw(isset($input['api_url']) && is_string($input['api_url']) ? $input['api_url'] : '', array('https', 'http')),
            'cache_minutes' => min(1440, max(1, absint(isset($input['cache_minutes']) ? $input['cache_minutes'] : 30))),
        );
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) return;
        $settings = $this->settings();
        wp_enqueue_script('tumtook-gallery-code-settings', plugin_dir_url(__FILE__) . 'assets/gallery-code-settings.js', array(), self::VERSION, true);
        ?>
        <div class="wrap ttgc-settings-test" data-nonce="<?php echo esc_attr(wp_create_nonce('ttgc_test_api')); ?>" data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <h1>Tumtook Gallery Code</h1>
            <p>ตั้งค่า API ครั้งเดียว จากนั้นใช้ <code>[tumtook_gallery_code]</code> เพื่ออ่าน field <code>tt_gallery_code</code> ของโพสต์ หรือระบุค่าเองด้วย <code>[tumtook_gallery_code code="VN"]</code> โมดูลนี้แยกจาก Tumtook Gallery เดิม</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="ttgc-api-url">API URL</label></th><td>
                        <input id="ttgc-api-url" type="url" class="large-text" name="<?php echo esc_attr(self::OPTION); ?>[api_url]" value="<?php echo esc_attr($settings['api_url']); ?>" autocomplete="off">
                        <p class="description">ใช้ API Gallery เดิมที่ส่ง items, code และ images.fileUrl ข้อมูลการเชื่อมต่อจะเก็บไว้ฝั่งเซิร์ฟเวอร์</p>
                        <p><label for="ttgc-test-code">Code สำหรับทดสอบ (ไม่บังคับ)</label><br><input id="ttgc-test-code" type="text" placeholder="เช่น KH" maxlength="64" autocomplete="off"></p>
                        <button type="button" class="button button-secondary" data-ttgc-test-api>ทดสอบ URL และดูข้อมูล Gallery</button>
                        <p class="description">ใช้ URL ที่กรอกได้ทันทีโดยไม่ต้องบันทึก เว้น code ว่างเพื่อดูภาพรวม หรือกรอก code เพื่อทดสอบเฉพาะชุด</p>
                    </td></tr>
                    <tr><th scope="row"><label for="ttgc-cache">เวลาแคช (นาที)</label></th><td>
                        <input id="ttgc-cache" type="number" min="1" max="1440" name="<?php echo esc_attr(self::OPTION); ?>[cache_minutes]" value="<?php echo esc_attr($settings['cache_minutes']); ?>">
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <div data-ttgc-api-result>
                <p data-ttgc-api-status role="status" aria-live="polite">ยังไม่ได้ทดสอบ URL</p>
                <dl data-ttgc-api-details></dl>
                <div data-ttgc-api-codes></div>
                <div data-ttgc-api-images style="display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:12px;max-width:960px"></div>
            </div>
        </div>
        <?php
    }

    private function normalize_code($value)
    {
        if (!is_string($value)) return '';
        $value = trim($value);
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/', $value) ? strtoupper($value) : '';
    }

    public function ajax_test_api()
    {
        check_ajax_referer('ttgc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่ทดสอบ API URL ได้'), 403);
            return;
        }
        $raw_url = isset($_POST['api_url']) && is_string($_POST['api_url']) ? wp_unslash($_POST['api_url']) : '';
        $url = esc_url_raw($raw_url, array('https', 'http'));
        if (!$url) {
            wp_send_json_error(array('message' => 'กรุณากรอก API URL แบบ http หรือ https ให้ถูกต้อง'), 400);
            return;
        }
        $raw_code = isset($_POST['code']) && is_string($_POST['code']) ? trim(wp_unslash($_POST['code'])) : '';
        $code = $this->normalize_code($raw_code);
        if ('' !== $raw_code && !$code) {
            wp_send_json_error(array('message' => 'กรุณาระบุ code เดี่ยว เช่น KH หรือเว้นว่างเพื่อดูภาพรวม'), 400);
            return;
        }
        $settings = $this->settings();
        $settings['api_url'] = $url;
        // A manual test uses unsaved input and fresh data, without changing the stored options.
        $result = $this->fetch_records(true, $settings);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'ttgc_invalid_response' === $result->get_error_code()
                ? 'ติดต่อ API ได้ แต่ข้อมูลไม่ใช่ JSON ที่มี items[] ตามรูปแบบ Gallery'
                : 'ทดสอบ API ไม่สำเร็จ ตรวจ URL และสิทธิ์การเข้าถึง API อีกครั้ง'), 502);
            return;
        }
        $codes = array();
        $image_count = 0;
        $preview = array();
        $preview_urls = array();
        foreach ($result['records'] as $record) {
            if (!is_array($record)) continue;
            $item_code = $this->normalize_code(isset($record['code']) ? $record['code'] : '');
            if (!$item_code) continue;
            if (!isset($codes[$item_code])) $codes[$item_code] = array('code' => $item_code, 'records' => 0, 'images' => 0);
            $codes[$item_code]['records']++;
            $images = isset($record['images']) && is_array($record['images']) ? $record['images'] : array();
            foreach ($images as $image) {
                if (!is_array($image) || empty($image['fileUrl']) || !is_string($image['fileUrl'])) continue;
                $codes[$item_code]['images']++;
                $image_count++;
            }
            // Build a small overview sample while scanning, without rescanning all records per code.
            if (!$code && count($preview) < 8) {
                foreach ($this->extract_items(array($record), $item_code, $url) as $image) {
                    if (isset($preview_urls[$image['image']])) continue;
                    $preview_urls[$image['image']] = true;
                    $preview[] = $image;
                    if (count($preview) >= 8) break;
                }
            }
        }
        $display_count = null;
        if ($code) {
            $items = $this->extract_items($result['records'], $code, $url);
            $preview = array_slice($items, 0, 8);
            $display_count = count($items);
        }
        wp_send_json_success(array(
            'http_status' => $result['http_status'],
            'record_count' => count($result['records']),
            'code_count' => count($codes),
            'image_count' => $image_count,
            'codes' => array_slice(array_values($codes), 0, 100),
            'requested_code' => $code,
            'matched_records' => $code && isset($codes[$code]) ? $codes[$code]['records'] : 0,
            'display_image_count' => $display_count,
            'preview' => $preview,
        ));
    }

    private function post_field($post, $field)
    {
        if (function_exists('jet_engine')) {
            $engine = jet_engine();
            $data = isset($engine->listings->data) ? $engine->listings->data : null;
            if (is_object($data) && method_exists($data, 'get_meta')) return $data->get_meta($field, $post, 'post');
        }
        return get_post_meta($post->ID, $field, true);
    }

    public function add_diagnostics_box($post_type, $post)
    {
        if (!$post instanceof WP_Post || !current_user_can('edit_post', $post->ID)) return;
        if (!metadata_exists('post', $post->ID, 'tt_gallery_code') && '' === (string) $this->normalize_code($this->post_field($post, 'tt_gallery_code'))) return;
        add_meta_box('ttgc-diagnostics', 'Tumtook Gallery Code — ตรวจสอบข้อมูล', array($this, 'diagnostics_box'), $post_type, 'normal', 'low');
    }

    public function diagnostics_box($post)
    {
        if (!current_user_can('edit_post', $post->ID)) return;
        $raw = $this->post_field($post, 'tt_gallery_code');
        wp_enqueue_script('tumtook-gallery-code-admin', plugin_dir_url(__FILE__) . 'assets/gallery-code-admin.js', array(), self::VERSION, true);
        ?>
        <div class="ttgc-diagnostics" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('ttgc_diagnostics_' . $post->ID)); ?>" data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <p>อ่าน field <code>tt_gallery_code</code> จากโพสต์ ID <strong><?php echo esc_html($post->ID); ?></strong> — ค่าที่บันทึกไว้: <strong><?php echo esc_html(is_scalar($raw) ? (string) $raw : '(รูปแบบข้อมูลไม่ถูกต้อง)'); ?></strong></p>
            <p>หากเพิ่งแก้ code ให้บันทึกหรืออัปเดตโพสต์ก่อนตรวจ เพื่อให้ตรงกับข้อมูลที่หน้าเว็บใช้</p>
            <p><label><input type="checkbox" data-ttgc-fresh> อ่าน API ล่าสุดโดยข้ามแคช (เรียกเมื่อกดตรวจเท่านั้น)</label></p>
            <button type="button" class="button button-secondary" data-ttgc-check>ตรวจข้อมูลและดูตัวอย่าง</button>
            <p data-ttgc-status role="status" aria-live="polite">ยังไม่ได้ตรวจ API</p>
            <dl data-ttgc-details></dl>
            <div data-ttgc-images style="display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:12px;max-width:960px"></div>
            <p class="description">กล่องนี้ใช้ตรวจเฉพาะหลังบ้าน ไม่แสดงบนหน้าเว็บ และไม่เปิดเผย API URL หรือ API key</p>
        </div>
        <?php
    }

    public function ajax_diagnostics()
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        check_ajax_referer('ttgc_diagnostics_' . $post_id, 'nonce');
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'ไม่มีสิทธิ์ตรวจข้อมูลของโพสต์นี้'), 403);
            return;
        }
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            wp_send_json_error(array('message' => 'ไม่พบโพสต์ที่ต้องการตรวจ'), 404);
            return;
        }
        $code = $this->normalize_code($this->post_field($post, 'tt_gallery_code'));
        if (!$code) {
            wp_send_json_error(array('message' => 'field tt_gallery_code ว่างหรือรูปแบบไม่ถูกต้อง ให้บันทึก code เดี่ยว เช่น KH ก่อนตรวจ'), 400);
            return;
        }
        $fresh = isset($_POST['fresh']) && '1' === $_POST['fresh'];
        $result = $this->fetch_records($fresh);
        if (is_wp_error($result)) {
            $messages = array(
                'ttgc_missing_api' => 'ยังไม่ได้ตั้งค่า API ที่ Settings → Tumtook Gallery Code',
                'ttgc_api_unavailable' => 'อ่าน API ไม่สำเร็จ อาจเกิดจากการเชื่อมต่อ การยืนยันสิทธิ์ หรือ API ตอบข้อผิดพลาด',
                'ttgc_invalid_response' => 'API ตอบข้อมูลที่ไม่ตรงรูปแบบ items[] ที่โมดูลรองรับ',
            );
            $error_code = $result->get_error_code();
            wp_send_json_error(array('message' => isset($messages[$error_code]) ? $messages[$error_code] : 'ไม่สามารถตรวจข้อมูลได้ในขณะนี้'), 502);
            return;
        }
        $matched = 0;
        $image_count = 0;
        foreach ($result['records'] as $record) {
            if (!is_array($record) || $this->normalize_code(isset($record['code']) ? $record['code'] : '') !== $code) continue;
            $matched++;
            $images = isset($record['images']) && is_array($record['images']) ? $record['images'] : array();
            foreach ($images as $image) {
                if (is_array($image) && !empty($image['fileUrl']) && is_string($image['fileUrl'])) $image_count++;
            }
        }
        $items = $this->extract_items($result['records'], $code, $this->settings()['api_url']);
        wp_send_json_success(array(
            'post_id' => $post_id,
            'code' => $code,
            'http_status' => $result['http_status'],
            'source' => $fresh ? 'ตรวจจาก API ล่าสุด' : 'ใช้ข้อมูลแคชได้ตามค่า ' . $result['cache_minutes'] . ' นาที',
            'matched_records' => $matched,
            'api_image_count' => $image_count,
            'display_image_count' => count($items),
            'preview' => array_slice($items, 0, 8),
        ));
    }

    private function code_from_field($field)
    {
        if (!is_string($field) || !preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $field)) return '';
        // Listing items can differ from the surrounding page or Elementor template.
        // JetEngine's meta API also honors its custom post-meta storage filters.
        if (function_exists('jet_engine')) {
            $engine = jet_engine();
            $data = isset($engine->listings->data) ? $engine->listings->data : null;
            if (is_object($data) && method_exists($data, 'get_current_object') && method_exists($data, 'get_meta')) {
                $object = $data->get_current_object();
                if ($object instanceof WP_Post) return $this->normalize_code($this->post_field($object, $field));
                // Never substitute the parent page when the current Listing item is empty or of another type.
                if (null !== $object && false !== $object) return '';
            }
        }
        $post_id = get_the_ID();
        return $post_id ? $this->normalize_code(get_post_meta($post_id, $field, true)) : '';
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array('code' => '', 'field' => 'tt_gallery_code'), $atts, self::SHORTCODE);
        $code = is_string($atts['code']) && '' === trim($atts['code'])
            ? $this->code_from_field($atts['field']) : $this->normalize_code($atts['code']);
        $settings = $this->settings();
        if (!$code || !$settings['api_url']) {
            return current_user_can('manage_options')
                ? '<p class="ttgc-admin-notice">กรุณาตั้งค่า API ใน Settings → Tumtook Gallery Code และกรอก field tt_gallery_code ของโพสต์ หรือระบุ code ใน shortcode</p>' : '';
        }
        wp_enqueue_style('tumtook-gallery-code', plugin_dir_url(__FILE__) . 'assets/gallery-code.css', array(), self::VERSION);
        wp_enqueue_script('tumtook-gallery-code', plugin_dir_url(__FILE__) . 'assets/gallery-code.js', array(), self::VERSION, true);
        return '<div class="ttgc" data-ttgc-code="' . esc_attr($code) . '" data-ttgc-signature="' . esc_attr(wp_hash('ttgc|' . $code, 'auth')) . '" data-ttgc-url="' . esc_url(rest_url('tumtook-gallery-code/v1/items')) . '" aria-busy="true"><div class="ttgc-items" role="list" aria-label="แกลเลอรีรูปภาพ"></div><div class="ttgc-placeholder" aria-hidden="true"></div></div>';
    }

    public function register_routes()
    {
        register_rest_route('tumtook-gallery-code/v1', '/items', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => array($this, 'can_view'),
            'callback' => array($this, 'rest_items'),
        ));
    }

    public function can_view($request)
    {
        $code = $this->normalize_code($request->get_param('code'));
        $signature = $request->get_param('signature');
        return $code && is_string($signature) && hash_equals(wp_hash('ttgc|' . $code, 'auth'), $signature);
    }

    public function rest_items($request)
    {
        $code = $this->normalize_code($request->get_param('code'));
        if (!$code) return new WP_Error('ttgc_invalid_code', 'Invalid gallery code.', array('status' => 400));
        $result = $this->fetch_records();
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(array('items' => $this->extract_items($result['records'], $code, $this->settings()['api_url'])));
    }

    private function fetch_records($fresh = false, $settings = null)
    {
        $settings = is_array($settings) ? $settings : $this->settings();
        if (!$settings['api_url']) return new WP_Error('ttgc_missing_api', 'Gallery API is not configured.', array('status' => 503));
        $ttl = min(1440, max(1, absint($settings['cache_minutes']))) * MINUTE_IN_SECONDS;
        // Share only the existing HTTP cache, never Gallery page settings or frontend code.
        $response = Tumtook_AIO_API_Cache::get($settings['api_url'], $ttl, true, $fresh);
        if (is_wp_error($response)) return new WP_Error('ttgc_api_unavailable', 'Gallery is temporarily unavailable.', array('status' => 503));
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return new WP_Error('ttgc_invalid_response', 'Invalid gallery response.', array('status' => 502));
        }
        return array('records' => $data['items'], 'http_status' => wp_remote_retrieve_response_code($response), 'cache_minutes' => (int) ($ttl / MINUTE_IN_SECONDS));
    }

    private function extract_items($records, $code, $endpoint)
    {
        $items = array();
        $seen = array();
        foreach ($records as $record) {
            if (!is_array($record) || $this->normalize_code(isset($record['code']) ? $record['code'] : '') !== $code) continue;
            $images = isset($record['images']) && is_array($record['images']) ? $record['images'] : array();
            foreach ($images as $image) {
                if (!is_array($image) || empty($image['fileUrl']) || !is_string($image['fileUrl'])) continue;
                $url = esc_url_raw(WP_Http::make_absolute_url($image['fileUrl'], $endpoint), array('https', 'http'));
                if (!$url || isset($seen[$url])) continue;
                $seen[$url] = true;
                $alt = isset($image['altText']) && is_string($image['altText']) ? wp_strip_all_tags($image['altText']) : '';
                $items[] = array(
                    'image' => $url,
                    'alt' => $alt,
                    'width' => isset($image['width']) && is_numeric($image['width']) ? min(100000, max(0, (int) $image['width'])) : 0,
                    'height' => isset($image['height']) && is_numeric($image['height']) ? min(100000, max(0, (int) $image['height'])) : 0,
                );
                if (count($items) >= self::LIMIT) return $items;
            }
        }
        return $items;
    }
}

new Tumtook_Gallery_Code();

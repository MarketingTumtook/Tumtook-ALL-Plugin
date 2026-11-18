<?php
/** Independent code-driven gallery. Shortcode: [tumtook_gallery_code code="VN"]. */
if (!defined('ABSPATH')) exit;

final class Tumtook_Gallery_Code
{
    const VERSION = '1.0.0';
    const OPTION = 'tumtook_gallery_code_settings';
    const SHORTCODE = 'tumtook_gallery_code';
    const LIMIT = 50;

    public function __construct()
    {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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
        ?>
        <div class="wrap">
            <h1>Tumtook Gallery Code</h1>
            <p>ตั้งค่า API ครั้งเดียว จากนั้นใช้ <code>[tumtook_gallery_code code="VN"]</code> ในหน้าที่ต้องการ โมดูลนี้แยกจาก Tumtook Gallery เดิม</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="ttgc-api-url">API URL</label></th><td>
                        <input id="ttgc-api-url" type="url" class="large-text" name="<?php echo esc_attr(self::OPTION); ?>[api_url]" value="<?php echo esc_attr($settings['api_url']); ?>" autocomplete="off">
                        <p class="description">ใช้ API Gallery เดิมที่ส่ง items, code และ images.fileUrl ข้อมูลการเชื่อมต่อจะเก็บไว้ฝั่งเซิร์ฟเวอร์</p>
                    </td></tr>
                    <tr><th scope="row"><label for="ttgc-cache">เวลาแคช (นาที)</label></th><td>
                        <input id="ttgc-cache" type="number" min="1" max="1440" name="<?php echo esc_attr(self::OPTION); ?>[cache_minutes]" value="<?php echo esc_attr($settings['cache_minutes']); ?>">
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function normalize_code($value)
    {
        if (!is_string($value)) return '';
        $value = trim($value);
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/', $value) ? strtoupper($value) : '';
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array('code' => ''), $atts, self::SHORTCODE);
        $code = $this->normalize_code($atts['code']);
        $settings = $this->settings();
        if (!$code || !$settings['api_url']) {
            return current_user_can('manage_options')
                ? '<p class="ttgc-admin-notice">กรุณาตั้งค่า API ใน Settings → Tumtook Gallery Code และระบุ code ใน shortcode</p>' : '';
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
        $settings = $this->settings();
        if (!$settings['api_url']) return new WP_Error('ttgc_missing_api', 'Gallery API is not configured.', array('status' => 503));
        $ttl = min(1440, max(1, absint($settings['cache_minutes']))) * MINUTE_IN_SECONDS;
        // Share only the existing HTTP cache, never Gallery page settings or frontend code.
        $response = Tumtook_AIO_API_Cache::get($settings['api_url'], $ttl, true);
        if (is_wp_error($response)) return new WP_Error('ttgc_api_unavailable', 'Gallery is temporarily unavailable.', array('status' => 503));
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return new WP_Error('ttgc_invalid_response', 'Invalid gallery response.', array('status' => 502));
        }
        return rest_ensure_response(array('items' => $this->extract_items($data['items'], $code, $settings['api_url'])));
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

<?php
/**
 * Tumtook Contact Map module. Shortcode: [tumtook_contact_map]
 */
if (!defined('ABSPATH')) {
    exit;
}

final class Tumtook_Contact_Map
{
    const VERSION = '1.0.5';
    const OPTION = 'tumtook_contact_map_settings';
    const PAGE = 'tumtook-contact-map';
    private static $instance;

    public static function instance()
    {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        if (function_exists('ttcm_default_token')) {
            add_action('admin_notices', array($this, 'conflict_notice'));
            return;
        }
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('elementor/widgets/register', array($this, 'register_widget'));
        add_shortcode('tumtook_contact_map', array($this, 'render_shortcode'));
    }

    public function conflict_notice()
    {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-warning"><p>Contact Map: กรุณาปิดปลั๊กอิน Tumtook Contact Map ตัวแยกก่อนใช้โมดูลใน Tumtook All-in-One</p></div>';
        }
    }

    public function default_branches()
    {
        $data = json_decode(file_get_contents(__DIR__ . '/assets/branches.json'), true);
        return is_array($data) ? $data : array();
    }

    public function settings()
    {
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        $legacy = get_option('ttcm_token', '');
        return array_merge(array('token' => is_string($legacy) ? $legacy : '', 'branches' => array()), $saved);
    }

    public function register_settings()
    {
        register_setting(self::OPTION, self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }

    public function sanitize_settings($input)
    {
        $previous = $this->settings();
        if (!is_array($input)) return $previous;
        $output = array('token' => $previous['token'], 'branches' => array());
        $token = isset($input['token']) && is_string($input['token']) ? trim($input['token']) : $previous['token'];
        if ($token !== '' && !preg_match('/^pk\.[A-Za-z0-9._-]+$/D', $token)) {
            add_settings_error(self::OPTION, 'invalid_token', 'กรุณาใช้ Mapbox Public token ที่ขึ้นต้น pk. เท่านั้น ค่าเดิมยังถูกเก็บไว้');
        } else {
            $output['token'] = $token;
        }
        $limits = array('lng' => array(-180, 180), 'lat' => array(-90, 90), 'rotation' => array(-360, 360));
        foreach ($this->default_branches() as $branch) {
            $id = $branch['id'];
            $old = isset($previous['branches'][$id]) && is_array($previous['branches'][$id]) ? $previous['branches'][$id] : array();
            $row = isset($input['branches'][$id]) && is_array($input['branches'][$id]) ? $input['branches'][$id] : array();
            foreach ($limits as $field => $range) {
                if (!isset($row[$field]) || $row[$field] === '') continue;
                if (!is_scalar($row[$field]) || !is_numeric($row[$field]) || !is_finite((float)$row[$field]) || (float)$row[$field] < $range[0] || (float)$row[$field] > $range[1]) {
                    add_settings_error(self::OPTION, 'invalid_' . $id . '_' . $field, $branch['name'] . ': ค่า ' . $field . ' ไม่ถูกต้อง');
                    if (isset($old[$field])) $output['branches'][$id][$field] = $old[$field];
                    continue;
                }
                $output['branches'][$id][$field] = (float)$row[$field];
            }
        }
        return $output;
    }

    public function admin_menu()
    {
        add_options_page('Tumtook Contact Map', 'Tumtook Contact Map', 'manage_options', self::PAGE, array($this, 'settings_page'));
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) return;
        $settings = $this->settings();
        ?>
        <div class="wrap">
            <h1>Tumtook Contact Map</h1>
            <p>โมดูลแผนที่สาขาใน Tumtook All-in-One พร้อมโมเดล 3D ทั้ง 3 แห่ง</p>
            <?php settings_errors(self::OPTION); ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION); ?>
                <table class="form-table" role="presentation"><tr>
                    <th><label for="ttcm-public-token">Mapbox Public token</label></th>
                    <td><input type="text" id="ttcm-public-token" class="large-text code" autocomplete="off" spellcheck="false" name="<?php echo esc_attr(self::OPTION); ?>[token]" value="<?php echo esc_attr($settings['token']); ?>" placeholder="pk.…">
                    <p class="description">ใส่ Public token ที่ขึ้นต้น pk. และจำกัด Allowed URLs ให้ตรงโดเมนเว็บไซต์ใน Mapbox ช่องนี้แสดงเฉพาะผู้ดูแลในหลังบ้าน แต่ Public token จะอยู่ในข้อมูลแผนที่บนหน้าเว็บตามปกติของ Mapbox</p></td>
                </tr></table>
                <h2>ตำแหน่งโมเดลและทิศอาคาร</h2>
                <p>เว้นว่างเพื่อใช้ค่าที่จัดวางไว้แล้ว การปรับนี้เปลี่ยนโมเดลและหมุดบนแผนที่ โดยลิงก์ Google Maps ใช้จุดนำทางเดิม</p>
                <table class="widefat striped" style="max-width:1000px"><thead><tr><th>สาขา</th><th>Longitude</th><th>Latitude</th><th>หมุนอาคาร (องศา)</th></tr></thead><tbody>
                <?php foreach ($this->default_branches() as $branch):
                    $point = isset($branch['modelCoordinates']) ? $branch['modelCoordinates'] : $branch['coordinates'];
                    $defaults = array('lng' => $point[0], 'lat' => $point[1], 'rotation' => isset($branch['modelRotation']) ? $branch['modelRotation'] : 0);
                    ?>
                    <tr><th><?php echo esc_html($branch['name']); ?></th>
                    <?php foreach ($defaults as $field => $default):
                        $value = isset($settings['branches'][$branch['id']][$field]) ? $settings['branches'][$branch['id']][$field] : '';
                        $label = $branch['name'] . ' ' . $field;
                        ?>
                        <td><input type="number" step="any" style="max-width:180px;width:100%" aria-label="<?php echo esc_attr($label); ?>" name="<?php echo esc_attr(self::OPTION . '[branches][' . $branch['id'] . '][' . $field . ']'); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($default); ?>"></td>
                    <?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php submit_button('บันทึกการตั้งค่า'); ?>
            </form>
            <h2>ใส่ใน Elementor</h2>
            <p>เลือกวิดเจ็ต <strong>Tumtook Contact Map</strong> หรือวาง Shortcode widget แล้วใส่ <code>[tumtook_contact_map]</code></p>
            <p>หน้าเว็บแสดงเฉพาะแผนที่และข้อมูลสาขา ไม่มีช่องกรอก token หรือกล่องตั้งค่าพรีวิว</p>
        </div>
        <?php
    }

    public function register_assets()
    {
        $url = plugin_dir_url(__FILE__) . 'assets/';
        wp_register_style('ttcm-mapbox', $url . 'mapbox-gl.css', array(), '3.33.0');
        wp_register_style('ttcm', $url . 'contact-map.css', array('ttcm-mapbox'), self::VERSION);
        wp_register_script('ttcm-mapbox', $url . 'mapbox-gl.js', array(), '3.33.0', true);
        wp_register_script('ttcm', $url . 'contact-map.js', array('ttcm-mapbox'), self::VERSION, true);
    }

    public function register_widget($manager)
    {
        if (!class_exists('Elementor\\Widget_Base')) return;
        require_once __DIR__ . '/elementor-widget.php';
        $manager->register(new Tumtook_Contact_Map_Elementor_Widget());
    }

    public function render_shortcode($atts = array())
    {
        $this->register_assets();
        wp_enqueue_style('ttcm');
        wp_enqueue_script('ttcm');
        $settings = $this->settings();
        $branches = $this->default_branches();
        $urls = array(); $rotations = array();
        foreach ($branches as &$branch) {
            $id = $branch['id'];
            $urls[$id] = plugin_dir_url(__FILE__) . 'assets/' . $id . '.glb';
            $override = isset($settings['branches'][$id]) && is_array($settings['branches'][$id]) ? $settings['branches'][$id] : array();
            $point = isset($branch['modelCoordinates']) ? $branch['modelCoordinates'] : $branch['coordinates'];
            $branch['modelCoordinates'] = array(isset($override['lng']) ? $override['lng'] : $point[0], isset($override['lat']) ? $override['lat'] : $point[1]);
            $rotations[$id] = isset($override['rotation']) ? $override['rotation'] : (isset($branch['modelRotation']) ? $branch['modelRotation'] : 0);
        }
        unset($branch);
        $editor = false;
        if (class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->editor)) {
            $editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        }
        $config = array('token' => $settings['token'], 'branches' => $branches, 'modelUrls' => $urls, 'modelRotations' => $rotations, 'autoLoad' => !$editor);
        return '<section class="tt-contact-map" data-tt-config="' . esc_attr(wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"></section>';
    }
}
Tumtook_Contact_Map::instance();

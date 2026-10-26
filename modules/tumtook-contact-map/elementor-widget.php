<?php
if (!defined('ABSPATH')) exit;

class Tumtook_Contact_Map_Elementor_Widget extends \Elementor\Widget_Base
{
    public function get_name() { return 'tumtook-contact-map'; }
    public function get_title() { return 'Tumtook Contact Map'; }
    public function get_icon() { return 'eicon-google-maps'; }
    public function get_categories() { return array('general'); }
    public function get_keywords() { return array('tumtook', 'map', 'contact', '3d', 'แผนที่'); }
    public function get_script_depends() { return array('ttcm'); }
    public function get_style_depends() { return array('ttcm'); }
    protected function register_controls()
    {
        $this->start_controls_section('contact_map_info', array('label' => 'แผนที่สาขา'));
        $this->add_control('instructions', array(
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw' => 'ตั้งค่า Public token และตำแหน่งโมเดลที่ Settings → Tumtook Contact Map แผนที่บนหน้าจริงจะเริ่มจากภาพรวมทุกสาขา ใน editor กดเปิดแผนที่เพื่อดู 3D',
            'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
        ));
        $this->end_controls_section();
    }
    protected function render()
    {
        echo Tumtook_Contact_Map::instance()->render_shortcode(); // Escaped within the module renderer.
    }
}

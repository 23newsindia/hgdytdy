<?php
/**
 * Main CSS Optimizer class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSSOptimizer {
    private $options;
    private $cache_dir;
    private $css_processor;
    private $settings;
    private $custom_css;
    private $cache;
    
    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/css-optimizer/';
        $this->init_options();
        
        // Initialize cache first since processor depends on it
        $this->cache = new CSSCache();
        $this->css_processor = new CSSProcessor($this->options);
        $this->settings = new CSSSettings($this->options);
        $this->custom_css = new CustomCSSManager();
        
        add_action('wp_enqueue_scripts', [$this, 'start_optimization'], 999);
        add_action('wp_head', [$this->custom_css, 'output_custom_css'], 999);
        register_activation_hook(CSS_OPTIMIZER_PLUGIN_FILE, [$this, 'activate']);
        
        // Add cache cleanup on deactivation
        register_deactivation_hook(CSS_OPTIMIZER_PLUGIN_FILE, [$this, 'deactivate']);
    }

    private function init_options() {
        $default_options = [
            'enabled' => true,
            'excluded_urls' => [],
            'preserve_media_queries' => true,
            'exclude_font_awesome' => true,
            'excluded_classes' => [],
            'custom_css' => '',
            'cache_duration' => 604800 // 1 week in seconds
        ];
        
        $saved_options = get_option('css_optimizer_options', []);
        $this->options = wp_parse_args($saved_options, $default_options);
        update_option('css_optimizer_options', $this->options);
    }

    public function activate() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
        $this->init_options();
        
        // Schedule cache cleanup
        if (!wp_next_scheduled('css_optimizer_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'css_optimizer_cache_cleanup');
        }
    }
    
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('css_optimizer_cache_cleanup');
        
        // Clean up cache directory
        $this->cleanup_cache_directory();
    }
    
    private function cleanup_cache_directory() {
        $cache_dir = $this->cache->get_cache_dir();
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($cache_dir);
        }
    }

    public function start_optimization() {
        if (!$this->options['enabled'] || is_admin()) {
            return;
        }
        $this->css_processor->process_styles();
    }
}
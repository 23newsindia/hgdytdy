<?php
/**
 * Main CSS Optimizer class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSSOptimizer {
    private $options;
    private $cache;
    private $css_processor;
    private $settings;
    private $custom_css;
    
    public function __construct($options = []) {
        $this->options = $options;
        
        // Initialize components
        $this->cache = new CSSCache();
        $this->css_processor = new CSSProcessor($this->options);
        $this->settings = new CSSSettings($this->options);
        $this->custom_css = new CustomCSSManager();
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', [$this, 'start_optimization'], 999);
        add_action('wp_head', [$this->custom_css, 'output_custom_css'], 999);
        
        // Register activation and deactivation hooks
        register_activation_hook(CSS_OPTIMIZER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CSS_OPTIMIZER_PLUGIN_FILE, [$this, 'deactivate']);
    }

    public function activate() {
        // Create cache directory
        $cache_dir = $this->cache->get_cache_dir();
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
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
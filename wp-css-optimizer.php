<?php
/**
 * Plugin Name: CSS Optimizer
 * Description: Optimizes CSS by removing unused rules and improving performance
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CSS_OPTIMIZER_PLUGIN_FILE', __FILE__);
define('CSS_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Make sure all required files exist before including them
$required_files = [
    'includes/class-css-cache.php',
    'includes/class-css-processor.php',
    'includes/class-css-settings.php',
    'includes/class-custom-css-manager.php'
];

foreach ($required_files as $file) {
    $file_path = CSS_OPTIMIZER_PLUGIN_DIR . $file;
    if (!file_exists($file_path)) {
        wp_die(sprintf('Required file "%s" is missing. Please reinstall the CSS Optimizer plugin.', $file));
    }
    require_once $file_path;
}

function css_optimizer_init() {
    if (class_exists('CSSOptimizer')) {
        $options = get_option('css_optimizer_options', [
            'enabled' => true,
            'excluded_urls' => [],
            'preserve_media_queries' => true,
            'exclude_font_awesome' => true,
            'excluded_classes' => [],
            'custom_css' => '',
            'cache_duration' => 604800 // 1 week in seconds
        ]);
        
        new CSSOptimizer($options);
    } else {
        wp_die('CSSOptimizer class not found. Please reinstall the CSS Optimizer plugin.');
    }
}
add_action('plugins_loaded', 'css_optimizer_init');
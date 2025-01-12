<?php
/**
 * CSS Caching functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSSCache {
    private $cache_dir;
    private $cache_url;
    private $cache_time = 604800; // 1 week

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/css-optimizer-cache';
        $this->cache_url = $upload_dir['baseurl'] . '/css-optimizer-cache';

        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        // Clean old cache files periodically
        add_action('wp_scheduled_delete', [$this, 'clean_old_cache']);
    }

    public function get($key) {
        $file = $this->cache_dir . '/' . $key . '.css';
        if (file_exists($file) && (time() - filemtime($file)) < $this->cache_time) {
            return file_get_contents($file);
        }
        return false;
    }

    public function set($key, $data) {
        $file = $this->cache_dir . '/' . $key . '.css';
        file_put_contents($file, $data);
    }

    public function clean_old_cache() {
        if ($handle = opendir($this->cache_dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    $file_path = $this->cache_dir . '/' . $file;
                    if ((time() - filemtime($file_path)) > $this->cache_time) {
                        unlink($file_path);
                    }
                }
            }
            closedir($handle);
        }
    }

    public function get_cache_dir() {
        return $this->cache_dir;
    }

    public function get_cache_url() {
        return $this->cache_url;
    }
}
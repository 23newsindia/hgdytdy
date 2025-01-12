<?php
/**
 * CSS Processing functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSSProcessor {
    private $options;
    private $cache;
    private $critical_css = [];
    
    public function __construct($options) {
        $this->options = $options;
        $this->cache = new CSSCache();
    }

    public function process_styles() {
        global $wp_styles;
        if (!is_object($wp_styles)) {
            return;
        }

        $original_queue = $wp_styles->queue;
        $inline_styles = '';

        foreach ($original_queue as $handle) {
            if (!$this->should_process_style($handle, $wp_styles)) {
                continue;
            }

            // Try to get from cache first
            $cache_key = $this->get_cache_key($handle, $wp_styles);
            $optimized_css = $this->cache->get($cache_key);

            if ($optimized_css === false) {
                $css_content = $this->get_css_content($handle, $wp_styles);
                if (!$css_content) {
                    continue;
                }

                $optimized_css = $this->optimize_css($css_content);
                $this->cache->set($cache_key, $optimized_css);
            }

            // Extract critical CSS
            $critical_css = $this->extract_critical_css($optimized_css);
            if ($critical_css) {
                $this->critical_css[] = $critical_css;
            }

            $inline_styles .= $optimized_css;
        }

        // Output critical CSS inline in head
        if (!empty($this->critical_css)) {
            add_action('wp_head', function() {
                echo "<style id='critical-css'>\n";
                echo implode("\n", $this->critical_css);
                echo "\n</style>";
            }, 1);
        }

        // Combine all non-critical CSS and load asynchronously
        if (!empty($inline_styles)) {
            $this->load_css_async($inline_styles);
        }
    }

    private function get_cache_key($handle, $wp_styles) {
        $style = $wp_styles->registered[$handle];
        return md5($handle . $style->src . (isset($style->ver) ? $style->ver : ''));
    }

    private function extract_critical_css($css) {
        // Extract CSS rules that affect above-the-fold content
        $critical_selectors = [
            'body', 'header', '#masthead', '.site-header',
            '.main-navigation', '.hero', '#hero',
            '[class*="wp-block-"]', '.entry-content'
        ];

        $critical = '';
        foreach ($critical_selectors as $selector) {
            if (preg_match_all('/' . preg_quote($selector) . '[^{]*\{[^}]+\}/s', $css, $matches)) {
                $critical .= implode("\n", $matches[0]);
            }
        }

        return $critical;
    }

    private function load_css_async($css) {
        // Generate a unique filename for the combined CSS
        $filename = md5($css) . '.css';
        $css_path = $this->cache->get_cache_dir() . '/' . $filename;
        $css_url = $this->cache->get_cache_url() . '/' . $filename;

        // Save combined CSS to file
        if (!file_exists($css_path)) {
            file_put_contents($css_path, $css);
        }

        // Add preload hint
        add_action('wp_head', function() use ($css_url) {
            echo "<link rel='preload' href='{$css_url}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\">\n";
            echo "<noscript><link rel='stylesheet' href='{$css_url}'></noscript>\n";
        }, 2);
    }

    private function optimize_css($css) {
        // Remove comments and whitespace
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);

        // Combine multiple selectors
        $css = preg_replace('/\s*([,:])\s*/', '$1', $css);
        
        // Remove unnecessary semicolons and spaces
        $css = str_replace([';}', ' {', '{ ', ' }', '} '], ['}', '{', '{', '}', '}'], $css);
        
        // Optimize hex colors
        $css = preg_replace('/\#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css);
        
        // Convert rgb to hex where possible
        $css = preg_replace_callback('/rgb\((\d+),(\d+),(\d+)\)/i', function($matches) {
            return sprintf("#%02x%02x%02x", $matches[1], $matches[2], $matches[3]);
        }, $css);

        // Optimize values
        $css = preg_replace('/\s*(0)(?:px|em|%|in|cm|mm|pc|pt|ex|deg|g?rad|m?s|k?hz)\s*/i', '$1', $css);
        $css = preg_replace('/\s*0 0 0 0\s*/', '0', $css);
        
        // Combine similar properties
        $css = preg_replace('/margin-(top|right|bottom|left):\s*0\s*;/i', '', $css);
        $css = preg_replace('/padding-(top|right|bottom|left):\s*0\s*;/i', '', $css);

        return trim($css);
    }

    private function should_process_style($handle, $wp_styles) {
        if (!isset($wp_styles->registered[$handle]) || empty($wp_styles->registered[$handle]->src)) {
            return false;
        }

        if (strpos($handle, 'code-block-pro') !== false || 
            strpos($handle, 'kevinbatdorf') !== false || 
            strpos($handle, 'shiki') !== false) {
            return false;
        }

        return !$this->should_skip($handle);
    }

    private function should_skip($handle) {
        $skip_handles = [
            'admin-bar', 
            'dashicons',
            'code-block-pro',
            'wp-block-kevinbatdorf-code-block-pro',
            'shiki'
        ];
        
        if ($this->options['exclude_font_awesome']) {
            $font_awesome_handles = ['font-awesome', 'fontawesome', 'fa', 'font-awesome-official'];
            $skip_handles = array_merge($skip_handles, $font_awesome_handles);
        }
        
        foreach ($skip_handles as $skip_handle) {
            if (strpos($handle, $skip_handle) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
     private function get_css_content($handle, $wp_styles) {
        $style = $wp_styles->registered[$handle];
        $src = $this->normalize_url($style->src);
        
        $css_file = $this->get_local_css_path($src);
        if ($css_file && is_file($css_file)) {
            return @file_get_contents($css_file);
        }
        
        return $this->fetch_remote_css($src);
    }


    
    private function normalize_url($src) {
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        } elseif (strpos($src, '/') === 0) {
            return site_url($src);
        }
        return $src;
    }

     private function get_local_css_path($src) {
        $parsed_url = parse_url($src);
        $path = isset($parsed_url['path']) ? ltrim($parsed_url['path'], '/') : '';
        
        $possible_paths = [
            ABSPATH . $path,
            WP_CONTENT_DIR . '/' . str_replace('wp-content/', '', $path),
            get_stylesheet_directory() . '/' . basename($path)
        ];
        
        foreach ($possible_paths as $test_path) {
            $test_path = wp_normalize_path($test_path);
            if (file_exists($test_path) && is_file($test_path)) {
                return $test_path;
            }
        }
        
        return false;
    }

    private function fetch_remote_css($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $response = wp_remote_get($url);
        return !is_wp_error($response) ? wp_remote_retrieve_body($response) : false;
    }

    private function process_and_enqueue_style($handle, $css_content, $wp_styles) {
        $optimized_css = $this->optimize_css($css_content);
        $optimized_css = $this->fix_font_paths($optimized_css, dirname($wp_styles->registered[$handle]->src));

        wp_deregister_style($handle);
        wp_register_style($handle . '-optimized', false);
        wp_enqueue_style($handle . '-optimized');
        wp_add_inline_style($handle . '-optimized', $optimized_css);
    }

      private function optimize_css($css) {
        if ($this->options['preserve_media_queries']) {
            preg_match_all('/@media[^{]+\{([^}]+)\}/s', $css, $media_queries);
            $media_blocks = isset($media_queries[0]) ? $media_queries[0] : [];
        }

        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches);
        
        $optimized = '';
        if (!empty($matches[0])) {
            foreach ($matches[0] as $i => $rule) {
                $selectors = $matches[1][$i];
                
                // Skip optimization for Code Block Pro related selectors
                if (strpos($selectors, 'code-block-pro') !== false ||
                    strpos($selectors, 'wp-block-kevinbatdorf') !== false ||
                    strpos($selectors, 'shiki') !== false ||
                    strpos($selectors, 'cbp-') !== false) {
                    $optimized .= $rule;
                    continue;
                }

                if (strpos($selectors, '@media') === 0) continue;
                
                $optimized_properties = $this->optimize_properties($matches[2][$i]);
                if (!empty($optimized_properties)) {
                    $optimized .= trim($selectors) . '{' . $optimized_properties . '}';
                }
            }
        }

        if ($this->options['preserve_media_queries'] && !empty($media_blocks)) {
            $optimized .= "\n" . implode("\n", $media_blocks);
        }

        return $this->minify_css($optimized);
    }

    private function optimize_properties($properties) {
        $props = array_filter(array_map('trim', explode(';', $properties)));
        $unique_props = [];
        
        foreach ($props as $prop) {
            if (empty($prop)) continue;
            
            $parts = explode(':', $prop, 2);
            if (count($parts) !== 2) continue;
            
            $unique_props[trim($parts[0])] = $prop;
        }

        return implode(';', $unique_props) . ';';
    }

    private function minify_css($css) {
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace([': ', "\r\n", "\r", "\n", "\t", '{ ', ' {', '} ', ' }', ';}'], [':', '', '', '', '', '{', '{', '}', '}', '}'], $css);
        return trim(preg_replace('/\s+/', ' ', $css));
    }

    private function fix_font_paths($css, $base_url) {
        return preg_replace_callback(
            '/url\([\'"]?(?!data:)([^\'")]+)[\'"]?\)/i',
            function($matches) use ($base_url) {
                $url = $matches[1];
                if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                    $url = trailingslashit($base_url) . ltrim($url, '/');
                }
                return 'url("' . $url . '")';
            },
            $css
        );
    }
}
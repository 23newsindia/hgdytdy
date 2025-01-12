<?php
/**
 * Settings page functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSSSettings {
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        add_options_page(
            'CSS Optimizer Settings',
            'CSS Optimizer',
            'manage_options',
            'css-optimizer',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('css_optimizer_options', 'css_optimizer_options');
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['submit']) && check_admin_referer('css_optimizer_settings')) {
            $this->save_settings();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('css_optimizer_options');
                do_settings_sections('css_optimizer');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Optimization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="css_optimizer_options[enabled]" 
                                    <?php checked($this->options['enabled']); ?>>
                                Enable CSS optimization
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Font Awesome</th>
                        <td>
                            <label>
                                <input type="checkbox" name="css_optimizer_options[exclude_font_awesome]" 
                                    <?php checked($this->options['exclude_font_awesome']); ?>>
                                Exclude Font Awesome from optimization
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Preserve Media Queries</th>
                        <td>
                            <label>
                                <input type="checkbox" name="css_optimizer_options[preserve_media_queries]" 
                                    <?php checked($this->options['preserve_media_queries']); ?>>
                                Keep responsive design rules
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Custom CSS</th>
                        <td>
                            <textarea name="css_optimizer_options[custom_css]" rows="10" class="large-text code"><?php 
                                echo esc_textarea($this->options['custom_css']); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Excluded Classes</th>
                        <td>
                            <textarea name="css_optimizer_options[excluded_classes]" rows="5" class="large-text code"><?php 
                                echo esc_textarea(implode("\n", $this->options['excluded_classes'])); 
                            ?></textarea>
                            <p class="description">Enter one CSS class per line to exclude from optimization.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = [];
        $options['enabled'] = isset($_POST['css_optimizer_options']['enabled']);
        $options['exclude_font_awesome'] = isset($_POST['css_optimizer_options']['exclude_font_awesome']);
        $options['preserve_media_queries'] = isset($_POST['css_optimizer_options']['preserve_media_queries']);
        $options['custom_css'] = wp_strip_all_tags($_POST['css_optimizer_options']['custom_css']);
        $options['excluded_classes'] = array_filter(array_map('trim', 
            explode("\n", $_POST['css_optimizer_options']['excluded_classes'])
        ));

        update_option('css_optimizer_options', $options);
        add_settings_error('css_optimizer_messages', 'css_optimizer_message', 
            'Settings saved successfully!', 'updated');
    }
}
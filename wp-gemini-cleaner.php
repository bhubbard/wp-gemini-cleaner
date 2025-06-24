<?php
/**
 * Plugin Name: WP Gemini Cleaner
 * Plugin URI:  https://example.com/
 * Description: Scans wp_options and postmeta tables using Gemini API to identify irrelevant data.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-gemini-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class for WP Gemini Cleaner.
 */
class WPGeminiCleaner {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // AJAX handlers for scanning and deletion.
        add_action( 'wp_ajax_wpgc_scan_options', array( $this, 'ajax_scan_options' ) );
        add_action( 'wp_ajax_wpgc_scan_postmeta', array( $this, 'ajax_scan_postmeta' ) );
        add_action( 'wp_ajax_wpgc_delete_option', array( $this, 'ajax_delete_option' ) );
        add_action( 'wp_ajax_wpgc_delete_postmeta', array( $this, 'ajax_delete_postmeta' ) );
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'WP Gemini Cleaner', 'wp-gemini-cleaner' ),
            __( 'Gemini Cleaner', 'wp-gemini-cleaner' ),
            'manage_options',
            'wp-gemini-cleaner',
            array( $this, 'admin_page_content' ),
            'dashicons-superhero',
            99
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'wpgc_settings_group', // Option group.
            'wpgc_gemini_api_key', // Option name.
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        add_settings_section(
            'wpgc_general_section', // ID.
            __( 'Gemini API Settings', 'wp-gemini-cleaner' ), // Title.
            array( $this, 'general_settings_section_callback' ), // Callback.
            'wp-gemini-cleaner' // Page.
        );

        add_settings_field(
            'wpgc_gemini_api_key', // ID.
            __( 'Gemini API Key', 'wp-gemini-cleaner' ), // Title.
            array( $this, 'gemini_api_key_callback' ), // Callback.
            'wp-gemini-cleaner', // Page.
            'wpgc_general_section' // Section.
        );
    }

    /**
     * Callback for general settings section.
     */
    public function general_settings_section_callback() {
        echo '<p>' . esc_html__( 'Enter your Gemini API key to enable database scanning. You can obtain one from the Google AI Studio.', 'wp-gemini-cleaner' ) . '</p>';
    }

    /**
     * Callback for Gemini API Key field.
     */
    public function gemini_api_key_callback() {
        $api_key = get_option( 'wpgc_gemini_api_key' );
        echo '<input type="text" id="wpgc_gemini_api_key" name="wpgc_gemini_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Your Gemini API Key (keep it confidential!).', 'wp-gemini-cleaner' ) . '</p>';
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_wp-gemini-cleaner' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wpgc-admin-style', plugin_dir_url( __FILE__ ) . 'admin-style.css', array(), '1.0.0' );
        wp_enqueue_script( 'wpgc-admin-script', plugin_dir_url( __FILE__ ) . 'admin-script.js', array( 'jquery' ), '1.0.0', true );

        wp_localize_script( 'wpgc-admin-script', 'wpgc_ajax_object', array(
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'scan_options_nonce'  => wp_create_nonce( 'wpgc_scan_options_nonce' ),
            'scan_postmeta_nonce' => wp_create_nonce( 'wpgc_scan_postmeta_nonce' ),
            'delete_option_nonce' => wp_create_nonce( 'wpgc_delete_option_nonce' ),
            'delete_postmeta_nonce' => wp_create_nonce( 'wpgc_delete_postmeta_nonce' ),
            'confirm_delete'      => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'wp-gemini-cleaner' ),
            'api_key_missing'     => __( 'Please enter your Gemini API Key in the settings tab before scanning.', 'wp-gemini-cleaner' ),
        ) );
    }

    /**
     * Admin page content.
     */
    public function admin_page_content() {
        ?>
        <div class="wrap wpgc-admin-wrap">
            <h1><?php esc_html_e( 'WP Gemini Cleaner', 'wp-gemini-cleaner' ); ?></h1>

            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-gemini-cleaner&tab=dashboard" class="nav-tab <?php echo ( ! isset( $_GET['tab'] ) || $_GET['tab'] === 'dashboard' ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'wp-gemini-cleaner' ); ?></a>
                <a href="?page=wp-gemini-cleaner&tab=settings" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'wp-gemini-cleaner' ); ?></a>
            </h2>

            <?php
            $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'dashboard';

            if ( 'dashboard' === $current_tab ) {
                $this->display_dashboard_tab();
            } elseif ( 'settings' === $current_tab ) {
                $this->display_settings_tab();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Display the dashboard tab content.
     */
    private function display_dashboard_tab() {
        ?>
        <div class="wpgc-dashboard-tab tab-content">
            <div class="wpgc-card-grid">
                <div class="wpgc-card">
                    <h3><?php esc_html_e( 'Installed Themes', 'wp-gemini-cleaner' ); ?></h3>
                    <div class="wpgc-scroll-list">
                        <?php $this->display_themes_list(); ?>
                    </div>
                </div>

                <div class="wpgc-card">
                    <h3><?php esc_html_e( 'Installed Plugins', 'wp-gemini-cleaner' ); ?></h3>
                    <div class="wpgc-scroll-list">
                        <?php $this->display_plugins_list(); ?>
                    </div>
                </div>
            </div>

            <div class="wpgc-scan-section wpgc-card">
                <h3><?php esc_html_e( 'Scan Database', 'wp-gemini-cleaner' ); ?></h3>
                <p><?php esc_html_e( 'Use the Gemini API to intelligently scan your database for orphaned or irrelevant data.', 'wp-gemini-cleaner' ); ?></p>
                <div class="wpgc-buttons">
                    <button id="wpgc-scan-options" class="button button-primary">
                        <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Scan WP Options', 'wp-gemini-cleaner' ); ?>
                    </button>
                    <button id="wpgc-scan-postmeta" class="button button-secondary">
                        <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Scan Post Meta', 'wp-gemini-cleaner' ); ?>
                    </button>
                </div>

                <div id="wpgc-scan-results" class="wpgc-scan-results" style="display:none;">
                    <h4><?php esc_html_e( 'Scan Results', 'wp-gemini-cleaner' ); ?></h4>
                    <div id="wpgc-loading-spinner" class="wpgc-loading-spinner" style="display:none;">
                        <div class="wpgc-loader"></div>
                        <p><?php esc_html_e( 'Analyzing data with Gemini API...', 'wp-gemini-cleaner' ); ?></p>
                    </div>
                    <div id="wpgc-results-content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display the settings tab content.
     */
    private function display_settings_tab() {
        ?>
        <div class="wpgc-settings-tab tab-content">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpgc_settings_group' );
                do_settings_sections( 'wp-gemini-cleaner' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display a list of installed themes.
     */
    private function display_themes_list() {
        $themes = wp_get_themes();
        if ( ! empty( $themes ) ) {
            echo '<ul>';
            foreach ( $themes as $theme_slug => $theme ) {
                $status = ( $theme->get_stylesheet() === get_stylesheet() ) ? '<span class="status active">(' . esc_html__( 'Active', 'wp-gemini-cleaner' ) . ')</span>' : '<span class="status inactive">(' . esc_html__( 'Inactive', 'wp-gemini-cleaner' ) . ')</span>';
                echo '<li><strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong> ' . $status . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No themes found.', 'wp-gemini-cleaner' ) . '</p>';
        }
    }

    /**
     * Display a list of installed plugins.
     */
    private function display_plugins_list() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins        = get_plugins();
        $active_plugins = get_option( 'active_plugins' );

        if ( ! empty( $plugins ) ) {
            echo '<ul>';
            foreach ( $plugins as $plugin_file => $plugin_data ) {
                $status = ( in_array( $plugin_file, (array) $active_plugins, true ) ) ? '<span class="status active">(' . esc_html__( 'Active', 'wp-gemini-cleaner' ) . ')</span>' : '<span class="status inactive">(' . esc_html__( 'Inactive', 'wp-gemini-cleaner' ) . ')</span>';
                echo '<li><strong>' . esc_html( $plugin_data['Name'] ) . '</strong> ' . $status . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No plugins found.', 'wp-gemini-cleaner' ) . '</p>';
        }
    }

    /**
     * Helper function to get a list of installed plugin slugs and theme slugs.
     *
     * @return array An array containing 'plugin_slugs' and 'theme_slugs'.
     */
    private function get_installed_component_slugs() {
        $plugin_slugs = array();
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        foreach ( $plugins as $plugin_file => $plugin_data ) {
            // Use plugin file as a robust slug.
            $plugin_slugs[] = dirname( $plugin_file );
            $plugin_slugs[] = sanitize_title( $plugin_data['Name'] ); // Add sanitized name too.
        }
        $plugin_slugs = array_filter( array_unique( $plugin_slugs ) );


        $theme_slugs = array();
        $themes      = wp_get_themes();
        foreach ( $themes as $theme_slug => $theme ) {
            $theme_slugs[] = $theme_slug;
            $theme_slugs[] = sanitize_title( $theme->get( 'Name' ) ); // Add sanitized name too.
        }
        $theme_slugs = array_filter( array_unique( $theme_slugs ) );


        return array(
            'plugin_slugs' => $plugin_slugs,
            'theme_slugs'  => $theme_slugs,
        );
    }

    /**
     * AJAX handler to scan wp_options using Gemini API.
     */
    public function ajax_scan_options() {
        check_ajax_referer( 'wpgc_scan_options_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions.', 'wp-gemini-cleaner' ) ) );
        }

        $api_key = get_option( 'wpgc_gemini_api_key' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Gemini API Key is not set. Please go to Settings.', 'wp-gemini-cleaner' ) ) );
        }

        global $wpdb;

        // Fetch all option names.
        $option_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" );
        if ( empty( $option_names ) ) {
            wp_send_json_success( array( 'message' => __( 'No options found in the database.', 'wp-gemini-cleaner' ), 'results' => array() ) );
        }

        $component_slugs = $this->get_installed_component_slugs();
        $known_slugs     = array_merge( $component_slugs['plugin_slugs'], $component_slugs['theme_slugs'] );

        // Construct the prompt for Gemini.
        // Provide context: known slugs/names to help Gemini filter.
        $prompt = 'Given the following list of WordPress option names and the known slugs/names of installed plugins and themes, identify which option names are highly likely to be orphaned, temporary, or not directly related to any currently installed (active or inactive) themes or plugins. Focus on options that look like remnants or transient data. Provide the output as a JSON array of strings, where each string is an option name that you suggest could be removed. If no suggestions, return an empty array.';
        $prompt .= "\n\nInstalled Component Slugs/Names: " . json_encode( $known_slugs );
        $prompt .= "\n\nAll WP Option Names: " . json_encode( $option_names );

        $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

        $payload = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array( 'text' => $prompt ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseMimeType' => 'application/json',
                'responseSchema' => array(
                    'type' => 'ARRAY',
                    'items' => array( 'type' => 'STRING' ),
                ),
            ),
        );

        $args = array(
            'body'        => wp_json_encode( $payload ),
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'method'      => 'POST',
            'timeout'     => 60, // Increased timeout for API call.
            'sslverify'   => false, // Adjust as needed for production.
        );

        $response = wp_remote_post( $gemini_url, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __( 'Gemini API request failed: ', 'wp-gemini-cleaner' ) . $response->get_error_message() ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            // Log the full response for debugging.
            error_log( 'WP Gemini Cleaner: Unexpected Gemini API response for options: ' . print_r( $data, true ) );
            wp_send_json_error( array( 'message' => __( 'Failed to parse Gemini API response. Please check API key and try again.', 'wp-gemini-cleaner' ) ) );
        }

        $suggested_options_json = $data['candidates'][0]['content']['parts'][0]['text'];
        $suggested_options = json_decode( $suggested_options_json, true );

        if ( ! is_array( $suggested_options ) ) {
            error_log( 'WP Gemini Cleaner: Gemini API returned non-array for options: ' . $suggested_options_json );
            wp_send_json_error( array( 'message' => __( 'Gemini API returned an invalid format for options. Expected JSON array.', 'wp-gemini-cleaner' ) ) );
        }

        // Filter out options that are critical or too generic.
        $critical_options = array(
            'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 'users_can_register',
            'default_comment_status', 'default_ping_status', 'default_pingback_flag', 'current_theme',
            'template', 'stylesheet', 'active_plugins', 'site_icon', 'timezone_string', 'wp_user_roles',
            // Add more critical or core options here.
        );
        $suggested_options = array_diff( $suggested_options, $critical_options );
        
        // Final check: ensure suggested options actually exist in the database.
        $final_suggestions = [];
        foreach ($suggested_options as $option_name) {
            if (in_array($option_name, $option_names)) {
                $final_suggestions[] = $option_name;
            }
        }


        wp_send_json_success( array(
            'message' => __( 'WP Options scan completed.', 'wp-gemini-cleaner' ),
            'results' => $final_suggestions,
            'type'    => 'options',
        ) );
    }

    /**
     * AJAX handler to scan wp_postmeta using Gemini API.
     */
    public function ajax_scan_postmeta() {
        check_ajax_referer( 'wpgc_scan_postmeta_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions.', 'wp-gemini-cleaner' ) ) );
        }

        $api_key = get_option( 'wpgc_gemini_api_key' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Gemini API Key is not set. Please go to Settings.', 'wp-gemini-cleaner' ) ) );
        }

        global $wpdb;

        // Fetch unique meta keys from wp_postmeta.
        // Limit to prevent excessively large requests to Gemini.
        $meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} LIMIT 500" );
        if ( empty( $meta_keys ) ) {
            wp_send_json_success( array( 'message' => __( 'No post meta keys found in the database.', 'wp-gemini-cleaner' ), 'results' => array() ) );
        }

        $component_slugs = $this->get_installed_component_slugs();
        $known_slugs     = array_merge( $component_slugs['plugin_slugs'], $component_slugs['theme_slugs'] );

        // Construct the prompt for Gemini.
        $prompt = 'Given the following list of WordPress post meta keys and the known slugs/names of installed plugins and themes, identify which meta keys are highly likely to be orphaned, temporary, or not directly related to any currently installed (active or inactive) themes or plugins, and thus safe to remove. Focus on meta keys that look like remnants, transients, or non-essential data. Provide the output as a JSON array of strings, where each string is a meta key that you suggest could be removed. If no suggestions, return an empty array.';
        $prompt .= "\n\nInstalled Component Slugs/Names: " . json_encode( $known_slugs );
        $prompt .= "\n\nAll WP Post Meta Keys: " . json_encode( $meta_keys );

        $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

        $payload = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array( 'text' => $prompt ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseMimeType' => 'application/json',
                'responseSchema' => array(
                    'type' => 'ARRAY',
                    'items' => array( 'type' => 'STRING' ),
                ),
            ),
        );

        $args = array(
            'body'        => wp_json_encode( $payload ),
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'method'      => 'POST',
            'timeout'     => 60, // Increased timeout for API call.
            'sslverify'   => false, // Adjust as needed for production.
        );

        $response = wp_remote_post( $gemini_url, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __( 'Gemini API request failed: ', 'wp-gemini-cleaner' ) . $response->get_error_message() ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            // Log the full response for debugging.
            error_log( 'WP Gemini Cleaner: Unexpected Gemini API response for postmeta: ' . print_r( $data, true ) );
            wp_send_json_error( array( 'message' => __( 'Failed to parse Gemini API response. Please check API key and try again.', 'wp-gemini-cleaner' ) ) );
        }

        $suggested_meta_keys_json = $data['candidates'][0]['content']['parts'][0]['text'];
        $suggested_meta_keys = json_decode( $suggested_meta_keys_json, true );

        if ( ! is_array( $suggested_meta_keys ) ) {
            error_log( 'WP Gemini Cleaner: Gemini API returned non-array for postmeta: ' . $suggested_meta_keys_json );
            wp_send_json_error( array( 'message' => __( 'Gemini API returned an invalid format for post meta. Expected JSON array.', 'wp-gemini-cleaner' ) ) );
        }

        // Filter out critical or well-known system meta keys.
        $critical_meta_keys = array(
            '_wp_page_template', '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata',
            // Add more critical or core meta keys here.
        );
        $suggested_meta_keys = array_diff( $suggested_meta_keys, $critical_meta_keys );

        // Final check: ensure suggested meta keys actually exist in the database.
        $final_suggestions = [];
        foreach ($suggested_meta_keys as $meta_key) {
            if (in_array($meta_key, $meta_keys)) {
                $final_suggestions[] = $meta_key;
            }
        }

        wp_send_json_success( array(
            'message' => __( 'Post Meta scan completed.', 'wp-gemini-cleaner' ),
            'results' => $final_suggestions,
            'type'    => 'postmeta',
        ) );
    }

    /**
     * AJAX handler to delete a single option.
     */
    public function ajax_delete_option() {
        check_ajax_referer( 'wpgc_delete_option_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions.', 'wp-gemini-cleaner' ) ) );
        }

        $option_name = sanitize_text_field( $_POST['option_name'] );

        if ( empty( $option_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Option name is missing.', 'wp-gemini-cleaner' ) ) );
        }

        if ( delete_option( $option_name ) ) {
            wp_send_json_success( array( 'message' => sprintf( __( 'Option "%s" deleted successfully.', 'wp-gemini-cleaner' ), $option_name ) ) );
        } else {
            wp_send_json_error( array( 'message' => sprintf( __( 'Failed to delete option "%s". It might not exist or be a protected option.', 'wp-gemini-cleaner' ), $option_name ) ) );
        }
    }

    /**
     * AJAX handler to delete post meta entries by key.
     */
    public function ajax_delete_postmeta() {
        check_ajax_referer( 'wpgc_delete_postmeta_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions.', 'wp-gemini-cleaner' ) ) );
        }

        $meta_key = sanitize_text_field( $_POST['meta_key'] );

        if ( empty( $meta_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Meta key is missing.', 'wp-gemini-cleaner' ) ) );
        }

        global $wpdb;
        $deleted_count = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key ) );

        if ( false !== $deleted_count ) {
            wp_send_json_success( array( 'message' => sprintf( __( 'Deleted %d entries for meta key "%s" successfully.', 'wp-gemini-cleaner' ), $deleted_count, $meta_key ) ) );
        } else {
            wp_send_json_error( array( 'message' => sprintf( __( 'Failed to delete post meta entries for key "%s".', 'wp-gemini-cleaner' ), $meta_key ) ) );
        }
    }
}

// Initialize the plugin.
new WPGeminiCleaner();
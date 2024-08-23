<?php
/**
 * Insights
 *
 * @package Insights
 * @since   1.0.0
 */
namespace Optemiz\PluginTracker;

/**
 * Optemiz Insights
 *
 * This is a tracker class to track plugin usage based on if the customer has opted in.
 * No personal information is being tracked by this class, only general settings, active plugins, environment details
 * and admin email.
 */
class Insights extends \Appsero\Insights
{

    public $client;

    /**
     * @var bool
     */
    private $plugin_data = false;

    /**
     * Initialize the class
     *
     * @param null $name
     * @param null $file
     */
    public function __construct()
    {
        
    }

    /**
     * Get the tracking data points
     *
     * @return array
     */
    public function get_tracking_data()
    {
        $all_plugins = $this->get_all_plugins();

        $users = get_users(
            [
                'role'    => 'administrator',
                'orderby' => 'ID',
                'order'   => 'ASC',
                'number'  => 1,
                'paged'   => 1,
            ]
        );

        $admin_user = (is_array($users) && !empty($users)) ? $users[0] : false;
        $first_name = '';
        $last_name  = '';

        if ($admin_user) {
            $first_name = $admin_user->first_name ? $admin_user->first_name : $admin_user->display_name;
            $last_name  = $admin_user->last_name;
        }

        $data = [
            'url'              => esc_url(home_url()),
            'site'             => $this->get_site_name(),
            'admin_email'      => get_option('admin_email'),
            'first_name'       => $first_name,
            'last_name'        => $last_name,
            'hash'             => $this->client->hash,
            'server'           => $this->get_server_info(),
            'wp'               => $this->get_wp_info(),
            'users'            => $this->get_user_counts(),
            'active_plugins'   => count($all_plugins['active_plugins']),
            'inactive_plugins' => count($all_plugins['inactive_plugins']),
            'ip_address'       => $this->get_user_ip_address(),
            'project_version'  => $this->client->project_version,
            'tracking_skipped' => false,
            'is_local'         => $this->is_local_server(),
        ];

        // Add Plugins
        if ($this->plugin_data) {
            $plugins_data = [];

            foreach ($all_plugins['active_plugins'] as $slug => $plugin) {
                $slug = strstr($slug, '/', true);

                if (!$slug) {
                    continue;
                }

                $plugins_data[$slug] = [
                    'name'      => isset($plugin['name']) ? $plugin['name'] : '',
                    'version'   => isset($plugin['version']) ? $plugin['version'] : '',
                ];
            }

            if (array_key_exists($this->client->slug, $plugins_data)) {
                unset($plugins_data[$this->client->slug]);
            }

            $data['plugins'] = $plugins_data;
        }

        // Add Metadata
        $extra = $this->get_extra_data();

        if ($extra) {
            $data['extra'] = $extra;
        }

        // Check this has previously skipped tracking
        $skipped = get_option($this->client->slug . '_tracking_skipped');

        if ($skipped === 'yes') {
            delete_option($this->client->slug . '_tracking_skipped');

            $data['tracking_skipped'] = true;
        }

        return apply_filters('opt_' . $this->client->slug . '_tracker_data', $data);
    }

    /**
     * Get the list of active and inactive plugins
     *
     * @return array
     */
    private function get_all_plugins()
    {
        // Ensure get_plugins function is loaded
        if (!function_exists('get_plugins')) {
            include ABSPATH . '/wp-admin/includes/plugin.php';
        }

        $plugins             = get_plugins();
        $active_plugins_keys = get_option('active_plugins', []);
        $active_plugins      = [];

        foreach ($plugins as $k => $v) {
            // Take care of formatting the data how we want it.
            $formatted         = [];
            $formatted['name'] = wp_strip_all_tags($v['Name']);

            if (isset($v['Version'])) {
                $formatted['version'] = wp_strip_all_tags($v['Version']);
            }

            if (isset($v['Author'])) {
                $formatted['author'] = wp_strip_all_tags($v['Author']);
            }

            if (isset($v['Network'])) {
                $formatted['network'] = wp_strip_all_tags($v['Network']);
            }

            if (isset($v['PluginURI'])) {
                $formatted['plugin_uri'] = wp_strip_all_tags($v['PluginURI']);
            }

            if (in_array($k, $active_plugins_keys, true)) {
                // Remove active plugins from list so we can show active and inactive separately
                unset($plugins[$k]);
                $active_plugins[$k] = $formatted;
            } else {
                $plugins[$k] = $formatted;
            }
        }

        return [
            'active_plugins'    => $active_plugins,
            'inactive_plugins'  => $plugins,
        ];
    }

    /**
     * Get site name
     */
    private function get_site_name()
    {
        $site_name = get_bloginfo('name');

        if (empty($site_name)) {
            $site_name = get_bloginfo('description');
            $site_name = wp_trim_words($site_name, 3, '');
        }

        if (empty($site_name)) {
            $site_name = esc_url(home_url());
        }

        return $site_name;
    }

    /**
     * Get server related info.
     *
     * @return array
     */
    private static function get_server_info()
    {
        global $wpdb;

        $server_data = [];

        if (isset($_SERVER['SERVER_SOFTWARE']) && !empty($_SERVER['SERVER_SOFTWARE'])) {
            // phpcs:ignore
            $server_data['software'] = $_SERVER['SERVER_SOFTWARE'];
        }

        if (function_exists('phpversion')) {
            $server_data['php_version'] = phpversion();
        }

        $server_data['mysql_version'] = $wpdb->db_version();

        $server_data['php_max_upload_size']  = size_format(wp_max_upload_size());
        $server_data['php_default_timezone'] = date_default_timezone_get();
        $server_data['php_soap']             = class_exists('SoapClient') ? 'Yes' : 'No';
        $server_data['php_fsockopen']        = function_exists('fsockopen') ? 'Yes' : 'No';
        $server_data['php_curl']             = function_exists('curl_init') ? 'Yes' : 'No';

        return $server_data;
    }

    /**
     * Get WordPress related data.
     *
     * @return array
     */
    private function get_wp_info()
    {
        $wp_data = [];

        $wp_data['memory_limit'] = WP_MEMORY_LIMIT;
        $wp_data['debug_mode']   = (defined('WP_DEBUG') && WP_DEBUG) ? 'Yes' : 'No';
        $wp_data['locale']       = get_locale();
        $wp_data['version']      = get_bloginfo('version');
        $wp_data['multisite']    = is_multisite() ? 'Yes' : 'No';
        $wp_data['theme_slug']   = get_stylesheet();

        $theme = wp_get_theme($wp_data['theme_slug']);

        $wp_data['theme_name']    = $theme->get('Name');
        $wp_data['theme_version'] = $theme->get('Version');
        $wp_data['theme_uri']     = $theme->get('ThemeURI');
        $wp_data['theme_author']  = $theme->get('Author');

        return $wp_data;
    }

    /**
     * Get user IP Address
     */
    private function get_user_ip_address()
    {
        $response = wp_remote_get('https://icanhazip.com/');

        if (is_wp_error($response)) {
            return '';
        }

        $ip = trim(wp_remote_retrieve_body($response));

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        return $ip;
    }

    /**
     * Check if the current server is localhost
     *
     * @return bool
     */
    private function is_local_server()
    {
        $host       = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost';
        $ip         = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : '127.0.0.1';
        $is_local   = false;

        if (
            in_array($ip, ['127.0.0.1', '::1'], true)
            || !strpos($host, '.')
            || in_array(strrchr($host, '.'), ['.test', '.testing', '.local', '.localhost', '.localdomain'], true)
        ) {
            $is_local = true;
        }

        return apply_filters('opt_tracker_is_local', $is_local);
    }
}

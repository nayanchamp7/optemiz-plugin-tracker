<?php
/**
 * Tracker setup
 *
 * @package Tracker
 * @since   1.0.0
 */
namespace Optemiz\PluginTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Main Tracker Class.
 *
 * @class OptIn
 */

if ( ! class_exists( 'Tracker', false ) ) :
    /**
     * Main Tracker Class.
     *
     * @class Tracker
     */
    class Tracker {

        public $slug;

        public $plugin_base_path;

        public $insights;

        function __construct() {

            add_action( 'upgrader_process_complete', array($this, 'plugin_updated'), 10, 2 );

            add_action($this->slug . '_tracker_optin', array($this, 'tracker_optin'));
            add_action($this->slug . '_uninstall_reason_submitted', array($this, 'uninstall_reason_submitted'));
        }

        function tracker_optin($data) {
            $new_data = [];
            $new_data['plugin_name'] = $this->slug;

            if(!empty($data) && is_array($data)) {
                $new_data['user_nicename'] = $data['first_name'] . ' ' . $data['last_name'];

                unset($data['first_name']);
                unset($data['last_name']);

                $formated_keys = ['tracking_skipped', 'ip_address', 'hash', 'server', 'wp', 'users', 'active_plugins', 'inactive_plugins'];
                foreach($data as $key => $value) {
                    $new_key = $key;

                    if($key === 'admin_email') {
                        $new_key = 'user_email';
                    }elseif($key === 'site') {
                        $new_key = 'site_name';
                    }elseif($key === 'url') {
                        $new_key = 'site_url';
                    }elseif($key === 'project_version') {
                        $new_key = 'plugin_version';
                    }

                    if(in_array($key, $formated_keys)) {
                        $new_data['info'][$new_key] = $value;
                    }else {
                        $new_data[$new_key] = $value;
                    }

                }
            }

            $new_data['is_multi_site'] = is_multisite();
            $new_data['status'] = 'activated';
            $new_data['last_updated_date'] = time();

            //generate token.
            $token_data['site_url']     = $data['url'];
            $token_data['plugin_name']  = $this->slug;
            $response = $this->send_request($token_data, home_url() . '/wp-json/optemiz/v1/email_tracker/generate_token', true);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
            } else {
                // Get the body of the response
                $response_body  = wp_remote_retrieve_body($response);
                $data           = json_decode($response_body, true);

                $token_option_name = $this->get_token_option_name();
                $token_exist = get_option($token_option_name);

                if(false === $token_exist && isset($data['token'])) {
                    update_option($token_option_name, $data['token']);

                    $new_data['token'] = $data['token'];

                    $this->send_request($new_data, home_url() . '/wp-json/optemiz/v1/email_tracker/optin');
                }
            }
        }
        
        function uninstall_reason_submitted($data) {
            global $wpdb;

            if(!isset($data['url'])) {
                return;
            }
            
            if(!isset($data['admin_email'])) {
                return;
            }

            $new_data['user_email']     = $data['admin_email'];
            $new_data['site_url']       = $data['url'];
            $new_data['reason_id']      = $data['reason_id'];
            $new_data['reason_info']    = $data['reason_info'];
            $new_data['status']         = 'deactivated';

            error_log('-- new_data: uninstall --');
            error_log(print_r($new_data, true));

            // return;

            $this->send_request($new_data, home_url() . '/wp-json/optemiz/v1/email_tracker/deactivate');
        }

        public function send_request( $params, $url, $blocking = false ) {
            $params = json_encode($params);
	
			$headers = [
				'user-agent'      => 'OPT_Email_Tracker/' . md5( esc_url( home_url() ) ) . ';',
				'Accept'          => 'application/json',
				'Content-Type'    => 'application/json',
			];
	
			$response = wp_remote_post(
				$url,
				[
					'method'      => 'POST',
					'timeout'     => 30,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => $blocking,
					'headers'     => $headers,
					'body'        => $params,
					'cookies'     => [],
				]
			);
	
			return $response;
		}

        /**
         * Track after plugin is updated;
         * 
         * @param $upgrader_object Array
         * @param $options Array
         */
        function plugin_updated( $upgrader_object, $options ) {

            error_log('plugin updated on: ' . $this->plugin_base_path);
            
            // when plugin is updated.
            if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
                foreach( $options['plugins'] as $plugin ) {
                    
                    if( $plugin == $this->plugin_base_path ) {
                        $token_option_name  = $this->get_token_option_name();
                        $already_tracked    = get_option($token_option_name);

                        //when no token exists, send data.
                        if (false === $already_tracked) {
                            $allow_tracking = get_option("{$this->slug}_allow_tracking");

                            if("yes" === $allow_tracking) {
                                $tracking_data = $this->insights->get_tracking_data();

                                error_log('tracking data.');
                                error_log(print_r($tracking_data, true));

                                $this->tracker_optin($tracking_data);
                            }
                        }

                    }
                }
            }
        }

        /**
         * Get token option name.
         */
        function get_token_option_name() {
            $site_url 		= home_url();
            $plugin_name 	= $this->slug;

            $site_url 		= base64_encode($site_url);
            $plugin_name 	= base64_encode($plugin_name);

            $token_option_name = "opt_tracked_{$site_url}_{$plugin_name}_token";

            return apply_filters("opt_filter_token_option_name", $token_option_name);
        }

    }

endif;
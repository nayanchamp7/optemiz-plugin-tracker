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

        function __construct() {
            $this->slug = 'current-template-name'; //@TODO need to be dynamic.

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


            error_log("response message");
            error_log(print_r($response, true));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                error_log("error message");
                error_log(print_r($error_message, true));
            } else {
                // Get the body of the response
                $response_body = wp_remote_retrieve_body($response);

                error_log("response_body: ");
            
                // Print the response
                error_log(print_r($response_body, true));
            }

            //get token.

            //$this->send_request($new_data, home_url() . '/wp-json/optemiz/v1/email_tracker/optin');
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

            error_log('-- params --');
            error_log(print_r($params, true));

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
            // plugin root file path.
            $target_plugin = 'current-template-name/current-template-name.php'; //@TODO need to be dynamic.

            error_log('plugin updated on: ' . $target_plugin);
            
            // when plugin is updated.
            if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
                foreach( $options['plugins'] as $plugin ) {
                    error_log($plugin . 'is updated.');
                    
                    if( $plugin == $target_plugin ) {

                        // Set a transient to record that our plugin has just been updated.
                        //set_transient( 'wp_upe_updated', 1 );

                        //@TODO do the after update things.

                        error_log($plugin . 'is updated.');

                        $already_tracked = get_option("opt_tracked_{$this->slug}");

                        if ($already_tracked === false) {
                            $appsero_insight_obj = ''; //@TODO need to be dynamic.
                            // $tracking_data = $appsero_insight_obj->get_tracking_data();
                        }


                    }
                }
            }
        }
    }

endif;
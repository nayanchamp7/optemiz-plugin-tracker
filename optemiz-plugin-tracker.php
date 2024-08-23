<?php
/**
 * Plugin Name: Optemiz Plugin Tracker
 * Description: Get current template file info on adminbar. It also shows Included file names of the template and wordpress current version and the current theme name.It just says to show current template, which template file you are still in.
 * Version: 1.0.0
 * Author: Optemiz
 * Author URI: https://optemiz.com
 * Text Domain: current-template-name
 * Tested up to: 6.3
 */

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Bootstrap the plugin.
 */
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function testing_ctn_appsero_init_tracker() {

	$client = new Appsero\Client( 'd7f959d5-133f-4228-8355-5d67093eaf6e', 'TempTool', CTN_FILE );

	// Active insights
	$client->insights()->init();

    $opt_tracker             = new Optemiz\PluginTracker\Tracker();
    $opt_tracker->api_url    = home_url(); //@TODO need to be dynamic
    $opt_tracker->slug       = 'current-template-name';
    $opt_tracker->plugin_base_path = 'current-template-name/current-template-name.php';
    
    $opt_tracker->insights   = new Optemiz\PluginTracker\Insights();
    $opt_tracker->insights->client   = $client;
    $opt_tracker->execute();
}
testing_ctn_appsero_init_tracker();
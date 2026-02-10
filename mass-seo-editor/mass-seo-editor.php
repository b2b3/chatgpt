<?php
/**
 * Plugin Name: Mass SEO Editor
 * Description: Admin tool for bulk editing SEO-related fields across posts and pages.
 * Version: 1.0.0
 * Author: Codex Assistant
 * Text Domain: mass-seo-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MSE_PLUGIN_VERSION', '1.0.0' );
define( 'MSE_PLUGIN_FILE', __FILE__ );
define( 'MSE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MSE_PLUGIN_PATH . 'includes/class-mse-plugin.php';

MSE_Plugin::get_instance();

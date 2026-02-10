<?php
/**
 * Main plugin bootstrap.
 */
class MSE_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MSE_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin controller.
	 *
	 * @var MSE_Admin|null
	 */
	private $admin = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MSE_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		require_once MSE_PLUGIN_PATH . 'includes/class-mse-admin.php';
		$this->admin = new MSE_Admin();
	}
}

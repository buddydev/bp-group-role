<?php
/**
 * Plugin Name: BuddyPress Group Role
 * Description: It allows group admin to select among WordPress roles as a group role and This role will be added to user roles on joining the group.
 * Version:     1.0.0
 * Author:      BuddyDev Team
 * Author URI:  https://buddydev.com/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bp-group-role
 * Domain Path: /languages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BP Group Role Loader class
 */
class BP_Group_Role_Loader {

	/**
	 * Class instance
	 *
	 * @var BP_Group_Role_Loader
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Retrieves class singleton instance
	 *
	 * @return BP_Group_Role_Loader
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks to actions
	 */
	private function setup() {
		add_action( 'bp_loaded', array( $this, 'load' ) );
	}

	/**
	 * Load plugin other file.
	 */
	public function load() {

		if ( bp_is_active( 'groups' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-bp-group-role-handler.php';

			BP_Group_Role_Handler::boot();
		}
	}
}

BP_Group_Role_Loader::get_instance();


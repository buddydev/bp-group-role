<?php
/**
 * Class handle ui screen and handle associating group role to the joining user.
 *
 * @package   BP_Group_Role
 * @copyright Copyright (c) 2022, BuddyDev.Com
 * @license   https://www.gnu.org/licenses/gpl.html GNU Public License
 * @author    Brajesh Singh, Ravi Sharma
 * @since     1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Group Role Handler
 */
class BP_Group_Role_Handler {
	/**
	 * Class instance
	 *
	 * @var BP_Group_Role_Handler
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
	 * @return BP_Group_Role_Handler
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sets up the bootstrapper
	 */
	public static function boot() {
		return self::get_instance();
	}

	/**
	 * Hooks to actions
	 */
	private function setup() {
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'bp_group_admin_edit_after', array( $this, 'update' ) );

		add_action( 'bp_after_group_settings_admin', array( $this, 'show_settings' ), 1000 );
		add_action( 'groups_group_settings_edited', array( $this, 'update' ) );
		add_action( 'groups_update_group', array( $this, 'update' ) );

		add_action( 'groups_join_group', array( $this, 'on_join' ), 10, 2 );
	}

	/**
	 * Add metabox on Group Edit page in dashboard
	 */
	public function add_metabox() {
		add_meta_box(
			'bp_group_role_page_metabox',
			__( 'Associated Role', 'bp-group-role' ),
			array( $this, 'show_settings' ),
			get_current_screen(),
			'side'
		);
	}

	/**
	 * Registers associated navigation menu
	 */
	public function show_settings( $item ) {
		$group_id = empty( $item ) ? bp_get_current_group_id() : $item->id;

		if ( ! $group_id || ! $this->can_user_modify_settings( $group_id ) ) {
			return;
		}

		$selected_role = groups_get_groupmeta( $group_id, '_bp_group_associated_role', true );
		$selected_role = $selected_role ? $selected_role : '';

		$label = is_admin() ? '' : __( 'Associated Role', 'bp-group-role' );
		?>
		<div class='bp-group-role-box'>
			<label>
				<?php echo esc_html( $label ); ?>
				<select name="bp-group-associated-role">
                    <option value=""><?php esc_html_e( 'Select Associated Role', 'bp-group-role' ); ?> </option>
					<?php foreach ( $this->get_roles() as $role => $role_detail ) : ?>
						<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $role, $selected_role ) ?>>
							<?php echo esc_html( $role_detail['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php wp_nonce_field( "bp-group-associated-role-save-{$group_id}", 'bp-group-role-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 *  Update association
	 *
	 * @param int $group_id Group id.
	 */
	public function update( $group_id ) {

		if ( ! $this->can_user_modify_settings( $group_id ) ) {
			return;
		}

		if ( ! isset( $_POST['bp-group-role-nonce'] ) || ! wp_verify_nonce( $_POST['bp-group-role-nonce'], "bp-group-associated-role-save-{$group_id}" ) ) {
			return;
		}

		$selected_role = empty( $_POST['bp-group-associated-role'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['bp-group-associated-role'] ) );

		if ( ! $selected_role ) {
			return groups_delete_groupmeta( $group_id, '_bp_group_associated_role' );
		}

		// Not a valid role.
		if ( ! $this->is_valid_role( $selected_role ) ) {
			bp_core_add_message( __( 'Please provide a valid group role.', 'bp-group-role' ), 'error' );

			return;
		}

		// Is restricted role.
		if ( in_array( $selected_role, $this->get_restricted_roles() ) ) {
			bp_core_add_message( __( 'Restricted role can not be save as group associated role.', 'bp-group-role' ), 'error' );

			return;
		}

		return groups_update_groupmeta( $group_id, '_bp_group_associated_role', $selected_role );
	}

	/**
	 * On join group
	 *
	 * @param int $group_id Group id.
	 * @param int $user_id  User id.
	 */
	public function on_join( $group_id, $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		$associated_role = groups_get_groupmeta( $group_id, '_bp_group_associated_role', true );

		if ( $associated_role && $this->is_valid_role( $associated_role ) ) {
			$user->add_role( $associated_role );
		}
	}

	/**
	 * Checks if user can modify settings.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return bool
	 */
	private function can_user_modify_settings( $group_id = false  ) {

		if ( ! $group_id ) {
			$group_id = bp_get_current_group_id();
		}

		if ( is_super_admin() || groups_is_user_admin( get_current_user_id(), $group_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieves roles
	 *
	 * @return array
	 */
	private function get_roles() {
		$all_roles = wp_roles()->roles;

		$editable_roles = (array) apply_filters( 'editable_roles', $all_roles );

		foreach ( $this->get_restricted_roles() as $role ) {
			unset( $editable_roles[ $role ] );
		}

		return $editable_roles;
	}

	/**
	 * Checks if given role is a valid role
	 *
	 * @param string $role Role.
	 *
	 * @return bool
	 */
	private function is_valid_role( $role ) {
		return array_key_exists( $role, $this->get_roles() );
	}

	/**
	 * Retrieves restricted roles
	 *
	 * @return string[]
	 */
	private function get_restricted_roles() {
		return array( 'administrator', 'editor' );
	}
}

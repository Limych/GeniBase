<?php
/**
 * GeniBase User API
 *
 * @package GeniBase
 * @subpackage Users
 *
 * @since	2.1.1
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

/**
 * GeniBase User class.
 *
 * @since 2.3.0
 * @package GeniBase
 * @subpackage User
 */
class GB_User {
	/**
	 * User data container.
	 *
	 * @since 2.3.0
	 * @access private
	 * @var array
	 */
	var $data;

	/**
	 * The user's ID.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var int
	 */
	var $ID = 0;

	/**
	 * The individual capabilities the user has been given.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	 */
	var $caps = array();

	/**
	 * User metadata option name.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	*/
	var $cap_key;

	/**
	 * The roles the user is part of.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	 */
	var $roles = array();

	/**
	 * All capabilities the user has, including individual and role based.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	*/
	var $allcaps = array();

	/**
	 * The filter context applied to user data fields.
	 *
	 * @since 2.3.0
	 * @access private
	 * @var string
	*/
	var $filter = null;

	/**
	 * Constructor
	 *
	 * Retrieves the userdata and passes it to {@link GB_User::init()}.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param int|string|stdClass|GB_User $id User's ID, a GB_User object, or a user object from the DB.
	 * @param string $name Optional. User's username
	 * @return GB_User
	 */
	function __construct( $id = 0, $name = '' ) {
		if ( is_a( $id, 'GB_User' ) ) {
			$this->init( $id->data );
			return;
		} elseif ( is_object( $id ) ) {
			$this->init( $id, $blog_id );
			return;
		}

		if ( !empty( $id ) && !is_numeric( $id ) ) {
			$name = $id;
			$id = 0;
		}

		if ( $id )
			$data = self::get_data_by( 'ID', $id );
		else
			$data = self::get_data_by( 'login', $name );

		if ( $data )
			$this->init( $data );
	}

	/**
	 * Sets up object properties, including capabilities.
	 *
	 * @param object $data User DB row object
	 */
	function init( $data ) {
		$this->data = $data;
		$this->ID = (int) $data->ID;
	}

	/**
	 * Get current user hash.
	 *
	 * @since	2.3.0
	 *
	 * @param boolean $renew_cookie	True to renew cookie with user hash.
	 * @return string	Current user hash.
	 */
	static function get_hash($renew_cookie = false){
		if( !empty($_COOKIE[GB_COOKIE_USERHASH]) ){
			$userhash = $_COOKIE[GB_COOKIE_USERHASH];
	
		}else{
			$salt = '';
			if( isset($_SERVER['HTTP_USER_AGENT']) )	$salt .= $_SERVER['HTTP_USER_AGENT'];
			if( isset($_SERVER['HTTP_REFERER']) )		$salt .= $_SERVER['HTTP_REFERER'];
			if( isset($_SERVER['REMOTE_ADDR']) )		$salt .= $_SERVER['REMOTE_ADDR'];
			$salt = md5($salt);
			$userhash = $salt . '-' . (12345678 + (int) date('Ymd')) . '-' . (123456 + (int) date('His'));
			$renew_cookie = true;
	
			/**
			 * Filter just generated user hash.
			 *
			 * @since	2.1.1
			 *
			 * @param string $userhash Just generated user hash
			 */
			$userhash = GB_Hooks::apply_filters('gb_userhash', $userhash);
		}
	
		if( $renew_cookie && $userhash ){
			// Set a cookie with new user ID
			$secure = ( 'https' === parse_url(site_url(), PHP_URL_SCHEME) && 'https' === parse_url(home_url(), PHP_URL_SCHEME) );
			@setcookie(GB_COOKIE_USERHASH, $userhash, time() + YEAR_IN_SECONDS, GB_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure);
		}
	
		return $userhash;
	}
	
	/**
	 * Return only the main user fields
	 *
	 * @since 2.3.0
	 *
	 * @param string $field The field to query against: 'id', 'slug', 'email' or 'login'
	 * @param string|int $value The field value
	 * @return object Raw user object
	 */
	static function get_data_by( $field, $value ) {
		if ( 'ID' == $field ) {
			// Make sure the value is numeric to avoid casting objects, for example,
			// to int 1.
			if ( !is_numeric( $value ) )
				return false;
			$value = absint( $value );
		} else {
			$value = trim( $value );
		}

		if ( !$value )
			return false;

		switch ( $field ) {
			case 'ID':
				$user_id = $value;
				$db_field = 'ID';
				break;
			case 'slug':
				$user_id = gb_cache_get($value, 'userslugs');
				$db_field = 'user_nicename';
				break;
			case 'email':
				$user_id = gb_cache_get($value, 'useremail');
				$db_field = 'user_email';
				break;
			default:
				return false;
		}

		if ( false !== $user_id ) {
			if ( $user = gb_cache_get( $user_id, 'users' ) )
				return $user;
		}

		if ( !$user = gbdb()->get_row( gbdb()->prepare_query(
				"SELECT * FROM ?_users WHERE ?#field = ?value", array(
						'field'	=> $db_field,
						'value'	=> $value,
				))))
			return false;

		update_user_caches( $user );

		return $user;
	}

	/**
	 * Magic method for checking the existence of a certain custom field
	 *
	 * @since 2.3.0
	 */
	function __isset( $key ) {
		if ( isset( $this->data->$key ) )
			return true;

		return metadata_exists( 'user', $this->ID, $key );
	}

	/**
	 * Magic method for accessing custom fields
	 *
	 * @since 2.3.0
	 */
	function __get( $key ) {
		if ( isset( $this->data->$key ) ) {
			$value = $this->data->$key;
		} else {
			$value = get_user_meta( $this->ID, $key, true );
		}

		if ( $this->filter ) {
			$value = sanitize_user_field( $key, $value, $this->ID, $this->filter );
		}

		return $value;
	}

	/**
	 * Magic method for setting custom fields
	 *
	 * @since 2.3.0
	 */
	function __set( $key, $value ) {
		$this->data->$key = $value;
	}

	/**
	 * Determine whether the user exists in the database.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @return bool True if user exists in the database, false if not.
	 */
	function exists() {
		return !empty( $this->ID );
	}

	/**
	 * Retrieve the value of a property or meta key.
	 *
	 * Retrieves from the users and usermeta table.
	 *
	 * @since 2.3.0
	 *
	 * @param string $key Property
	 */
	function get( $key ) {
		return $this->__get( $key );
	}

	/**
	 * Determine whether a property or meta key is set
	 *
	 * Consults the users and usermeta tables.
	 *
	 * @since 2.3.0
	 *
	 * @param string $key Property
	 */
	function has_prop( $key ) {
		return $this->__isset( $key );
	}

	/*
	 * Return an array representation.
	 *
	 * @since 2.3.0
	 *
	 * @return array Array representation.
	 */
	function to_array() {
		return get_object_vars( $this->data );
	}

	/**
	 * Set up capability object properties.
	 *
	 * Will set the value for the 'cap_key' property to current database table
	 * prefix, followed by 'capabilities'. Will then check to see if the
	 * property matching the 'cap_key' exists and is an array. If so, it will be
	 * used.
	 *
	 * @access protected
	 * @since 2.3.0
	 *
	 * @param string $cap_key Optional capability key
	 */
	function _init_caps( $cap_key = '' ) {
		if ( empty($cap_key) )
			$this->cap_key = gbdb()->prefix . 'capabilities';
		else
			$this->cap_key = $cap_key;

		$this->caps = get_user_meta( $this->ID, $this->cap_key, true );

		if ( !is_array( $this->caps ) )
			$this->caps = array();

		$this->get_role_caps();
	}

	/**
	 * Retrieve all of the role capabilities and merge with individual capabilities.
	 *
	 * All of the capabilities of the roles the user belongs to are merged with
	 * the users individual roles. This also means that the user can be denied
	 * specific roles that their role might have, but the specific user isn't
	 * granted permission to.
	 *
	 * @since 2.3.0
	 * @uses $gb_roles
	 * @access public
	 */
	function get_role_caps() {
		global $gb_user_roles;

		if ( !isset( $gb_user_roles ) )
			$gb_user_roles = new GB_User_Roles();

		//Filter out caps that are not role names and assign to $this->roles
		if ( is_array( $this->caps ) )
			$this->roles = array_filter( array_keys( $this->caps ), array( $gb_user_roles, 'is_role' ) );

		//Build $allcaps from role caps, overlay user's $caps
		$this->allcaps = array();
		foreach ( (array) $this->roles as $role ) {
			$the_role = $gb_user_roles->get_role( $role );
			$this->allcaps = array_merge( (array) $this->allcaps, (array) $the_role->capabilities );
		}
		$this->allcaps = array_merge( (array) $this->allcaps, (array) $this->caps );
	}

	/**
	 * Add role to user.
	 *
	 * Updates the user's meta data option with capabilities and roles.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 */
	function add_role( $role ) {
		$this->caps[$role] = true;
		self::update_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();

		/**
		 * Fires immediately after user role added.
		 *
		 * @since 2.3.0
		 *
		 * @param int    $user_id	User ID.
		 * @param string $role		Role name.
		 */
		GB_Hooks::do_action( 'added_user_role', $this->ID, $role );
	}

	/**
	 * Remove role from user.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 */
	function remove_role( $role ) {
		if ( !in_array($role, $this->roles) )
			return;
		unset( $this->caps[$role] );
		self::update_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();

		/**
		 * Fires immediately after user role removed.
		 *
		 * @since 2.3.0
		 *
		 * @param int    $user_id	User ID.
		 * @param string $role		Role name.
		 */
		GB_Hooks::do_action( 'removed_user_role', $this->ID, $role );
	}

	/**
	 * Set the role of the user.
	 *
	 * This will remove the previous roles of the user and assign the user the
	 * new one. You can set the role to an empty string and it will remove all
	 * of the roles from the user.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 */
	function set_role( $role ) {
		if ( 1 == count( $this->roles ) && $role == current( $this->roles ) )
			return;

		$old_roles = array();
		foreach ( (array) $this->roles as $oldrole ){
			if( isset($this->caps[$oldrole]) ){
				$old_roles[] = $oldrole;
				unset( $this->caps[$oldrole] );
			}
		}

		if ( !empty( $role ) ) {
			$this->caps[$role] = true;
			$this->roles = array( $role => true );
		} else {
			$this->roles = false;
		}
		self::update_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();

		foreach ($old_roles as $oldrole){
			/** This action is documented in gb/class.gb-user.php */
			GB_Hooks::do_action( 'removed_user_role', $this->ID, $oldrole );
		}
		
		/** This action is documented in gb/class.gb-user.php */
		GB_Hooks::do_action( 'added_user_role', $this->ID, $role );
	}

	/**
	 * Add capability and grant or deny access to capability.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @param bool $grant Whether to grant capability to user.
	 */
	function add_cap( $cap, $grant = true ) {
		$this->caps[$cap] = $grant;
		self::update_meta( $this->ID, $this->cap_key, $this->caps );
	}

	/**
	 * Remove capability from user.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 */
	function remove_cap( $cap ) {
		if ( ! isset( $this->caps[$cap] ) )
			return;
		unset( $this->caps[$cap] );
		self::update_meta( $this->ID, $this->cap_key, $this->caps );
	}

	/**
	 * Remove all of the capabilities of the user.
	 *
	 * @since 2.3.0
	 * @access public
	 */
	function remove_all_caps() {
		$this->caps = array();
		self::delete_meta( $this->ID, $this->cap_key );
		$this->get_role_caps();
	}

	/**
	 * Map meta capabilities to primitive capabilities.
	 *
	 * This does not actually compare whether the user ID has the actual capability,
	 * just what the capability or capabilities are. Meta capability list value can
	 * be 'delete_user', 'edit_user', 'remove_user', 'promote_user'.
	 *
	 * @since 2.3.0
	 *
	 * @param string $cap Capability name.
	 * @return array Actual capabilities for meta capability.
	 */
	static function map_cap( $cap ) {
		$args = array_slice( func_get_args(), 1 );
		$caps = array();
	
		switch ( $cap ) {
			case 'remove_user':
				$caps[] = 'remove_users';
				break;
			case 'promote_user':
				$caps[] = 'promote_users';
				break;
			case 'edit_user':
			case 'edit_users':
				// Allow user to edit itself
				if ( 'edit_user' == $cap && isset( $args[0] ) && $this->ID == $args[0] )
					break;

				$caps[] = 'edit_users'; // edit_user maps to edit_users.
				break;
			case 'edit_files':
			case 'edit_plugins':
			case 'edit_themes':
				// Disallow the file editors.
				if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ){
					$caps[] = 'do_not_allow';
				}elseif ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ){
					$caps[] = 'do_not_allow';
				}else{
					$caps[] = $cap;
				}
				break;
			case 'update_plugins':
			case 'delete_plugins':
			case 'install_plugins':
			case 'upload_plugins':
			case 'update_themes':
			case 'delete_themes':
			case 'install_themes':
			case 'upload_themes':
			case 'update_core':
				// Disallow anything that creates, deletes, or updates core, plugin, or theme files.
				// Files in uploads are excepted.
				if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
					$caps[] = 'do_not_allow';
				} elseif ( 'upload_themes' === $cap ) {
					$caps[] = 'install_themes';
				} elseif ( 'upload_plugins' === $cap ) {
					$caps[] = 'install_plugins';
				} else {
					$caps[] = $cap;
				}
				break;
			case 'activate_plugins':
				$caps[] = $cap;
				break;
			case 'delete_user':
			case 'delete_users':
				$caps[] = 'delete_users'; // delete_user maps to delete_users.
				break;
			case 'create_users':
				$caps[] = $cap;
				break;
			case 'customize' :
				$caps[] = 'edit_theme_options';
				break;
			default:
				// If no meta caps match, return the original cap.
				$caps[] = $cap;
		}
	
		/**
		 * Filter a user's capabilities depending on specific context and/or privilege.
		 *
		 * @since 2.3.0
		 *
		 * @param array  $caps    Returns the user's actual capabilities.
		 * @param string $cap     Capability name.
		 * @param int    $user_id The user ID.
		 * @param array  $args    Adds the context to the cap. Typically the object ID.
		 */
		return GB_Hooks::apply_filters( 'map_meta_cap', $caps, $cap, $this->ID, $args );
	}
	
	/**
	 * Whether user has capability or role name.
	 *
	 * This is useful for looking up whether the user has a specific role
	 * assigned to the user. The second optional parameter can also be used to
	 * check for capabilities against a specific object, such as a post or user.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability or role name to search.
	 * @return bool True, if user has capability; false, if user does not have capability.
	 */
	function has_cap( $cap ) {
		$args = array_slice( func_get_args(), 1 );
		$args = array_merge( array( $cap, $this->ID ), $args );
		$caps = call_user_func_array( 'GB_User::map_cap', $args );

		// Must have ALL requested caps
		$capabilities = GB_Hooks::apply_filters( 'user_has_cap', $this->allcaps, $caps, $args );
		$capabilities['exist'] = true; // Everyone is allowed to exist
		foreach ( (array) $caps as $cap ) {
			if ( empty( $capabilities[ $cap ] ) )
				return false;
		}

		return true;
	}
	
	/**
	 * Add meta data field to a user.
	 *
	 * Post meta data is called "Custom Fields" on the Administration Screens.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id User ID.
	 * @param string $meta_key Metadata name.
	 * @param mixed $meta_value Metadata value.
	 * @param bool $unique Optional, default is false. Whether the same key should not be added.
	 * @return int|bool Meta ID on success, false on failure.
	 */
	static function add_meta($user_id, $meta_key, $meta_value, $unique = false) {
		return GB_Meta::add('user', $user_id, $meta_key, $meta_value, $unique);
	}
	
	/**
	 * Remove metadata matching criteria from a user.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id user ID
	 * @param string $meta_key Metadata name.
	 * @param mixed $meta_value Optional. Metadata value.
	 * @return bool True on success, false on failure.
	 */
	static function delete_meta($user_id, $meta_key, $meta_value = '') {
		return GB_Meta::delete('user', $user_id, $meta_key, $meta_value);
	}
	
	/**
	 * Retrieve user meta field for a user.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id User ID.
	 * @param string $key Optional. The meta key to retrieve. By default, returns data for all keys.
	 * @param bool $single Whether to return a single value.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
	 *  is true.
	 */
	static function get_meta($user_id, $key = '', $single = false) {
		return GB_Meta::get('user', $user_id, $key, $single);
	}
	
	/**
	 * Update user meta field based on user ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and user ID.
	 *
	 * If the meta field for the user does not exist, it will be added.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id User ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed $meta_value Metadata value.
	 * @param mixed $prev_value Optional. Previous value to check before removing.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	static function update_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
		return GB_Meta::update('user', $user_id, $meta_key, $meta_value, $prev_value);
	}
}

/**
 * GeniBase User Roles.
 *
 * The role option is simple, the structure is organized by role name that store
 * the name in value of the 'name' key. The capabilities are stored as an array
 * in the value of the 'capability' key.
 *
 *     array (
 *    		'rolename' => array (
 *    			'name' => 'rolename',
 *    			'capabilities' => array()
 *    		)
 *     )
 *
 * @since 2.3.0
 * @package GeniBase
 * @subpackage User
 */
class GB_User_Roles {
	/**
	 * List of roles and capabilities.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	 */
	public $roles;

	/**
	 * List of the role objects.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	 */
	public $role_objects = array();

	/**
	 * List of role names.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	*/
	public $role_names = array();

	/**
	 * Option name for storing role list.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	*/
	public $role_key;

	/**
	 * Whether to use the database for retrieval and storage.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var bool
	 */
	public $use_db = true;

	/**
	 * Constructor
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->_init();
	}

	public static function getInstance(){
		static $_instance;

		if($_instance == NULL){
			$_instance = new GB_User_Roles();
		}
		return $_instance;
	}

	/**
	 * Set up the object properties.
	 *
	 * The role key is set to the current prefix for the $wpdb object with
	 * 'user_roles' appended. If the $gb_user_roles global is set, then it will
	 * be used and the role option will not be updated or used.
	 *
	 * @since 2.3.0
	 * @access protected
	 *
	 * @global array $gb_user_roles Used to set the 'roles' property value.
	 */
	protected function _init() {
		global $gb_user_roles;

		$this->role_key = gbdb()->get_blog_prefix() . 'user_roles';
		if ( !empty( $gb_user_roles ) ) {
			$this->roles = $gb_user_roles;
			$this->use_db = false;
		} else {
			$this->roles = get_option( $this->role_key );
		}

		if ( empty( $this->roles ) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ( array_keys( $this->roles ) as $role ) {
			$this->role_objects[$role] = new GB_User_Role( $role, $this->roles[$role]['capabilities'] );
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	/**
	 * Reinitialize the object
	 *
	 * Recreates the role objects. This is typically called only by switch_to_blog()
	 * after switching wpdb to a new blog ID.
	 *
	 * @since 2.3.0
	 * @access public
	 */
	public function reinit() {
		// There is no need to reinit if using the gb_user_roles global.
		if ( ! $this->use_db )
			return;

		// Duplicated from _init() to avoid an extra function call.
		$this->role_key = gbdb()->get_blog_prefix() . 'user_roles';
		$this->roles = get_option( $this->role_key );
		if ( empty( $this->roles ) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ( array_keys( $this->roles ) as $role ) {
			$this->role_objects[$role] = new GB_User_Role( $role, $this->roles[$role]['capabilities'] );
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	/**
	 * Add role name with capabilities to list.
	 *
	 * Updates the list of roles, if the role doesn't already exist.
	 *
	 * The capabilities are defined in the following format `array( 'read' => true );`
	 * To explicitly deny a role a capability you set the value for that capability to false.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param string $display_name Role display name.
	 * @param array $capabilities List of role capabilities in the above format.
	 * @return GB_Role|null GB_Role object if role is added, null if already exists.
	 */
	public function add_role( $role, $display_name, $capabilities = array() ) {
		if ( isset( $this->roles[$role] ) )
			return;

		$this->roles[$role] = array(
				'name' => $display_name,
				'capabilities' => $capabilities
		);
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
		$this->role_objects[$role] = new GB_User_Role( $role, $capabilities );
		$this->role_names[$role] = $display_name;
		return $this->role_objects[$role];
	}

	/**
	 * Remove role by name.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 */
	public function remove_role( $role ) {
		if ( ! isset( $this->role_objects[$role] ) )
			return;

		unset( $this->role_objects[$role] );
		unset( $this->role_names[$role] );
		unset( $this->roles[$role] );

		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );

		if ( get_option( 'default_role' ) == $role )
			update_option( 'default_role', 'subscriber' );
	}

	/**
	 * Add capability to role.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 * @param bool $grant Optional, default is true. Whether role is capable of performing capability.
	 */
	public function add_cap( $role, $cap, $grant = true ) {
		if ( ! isset( $this->roles[$role] ) )
			return;

		$this->roles[$role]['capabilities'][$cap] = $grant;
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
	}

	/**
	 * Remove capability from role.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $role, $cap ) {
		if ( ! isset( $this->roles[$role] ) )
			return;

		unset( $this->roles[$role]['capabilities'][$cap] );
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
	}

	/**
	 * Retrieve role object by name.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @return GB_Role|null GB_Role object if found, null if the role does not exist.
	 */
	public function get_role( $role ) {
		if ( isset( $this->role_objects[$role] ) )
			return $this->role_objects[$role];
		else
			return null;
	}

	/**
	 * Retrieve list of role names.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @return array List of role names.
	 */
	public function get_names() {
		return $this->role_names;
	}

	/**
	 * Whether role name is currently in the list of available roles.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name to look up.
	 * @return bool
	 */
	public function is_role( $role ) {
		return isset( $this->role_names[$role] );
	}
}

/**
 * GeniBase Role class.
 *
 * @since 2.3.0
 * @package GeniBase
 * @subpackage User
 */
class GB_User_Role {
	/**
	 * Role name.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	public $name;

	/**
	 * List of capabilities the role contains.
	 *
	 * @since 2.3.0
	 * @access public
	 * @var array
	 */
	public $capabilities;

	/**
	 * Constructor - Set up object properties.
	 *
	 * The list of capabilities, must have the key as the name of the capability
	 * and the value a boolean of whether it is granted to the role.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param array $capabilities List of capabilities.
	 */
	public function __construct( $role, $capabilities ) {
		$this->name = $role;
		$this->capabilities = $capabilities;
	}

	/**
	 * Assign role a capability.
	 *
	 * @see GB_Roles::add_cap() Method uses implementation for role.
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @param bool $grant Whether role has capability privilege.
	 */
	public function add_cap( $cap, $grant = true ) {
		global $gb_user_roles;

		if ( ! isset( $gb_user_roles ) )
			$gb_user_roles = new GB_User_Roles();

		$this->capabilities[$cap] = $grant;
		$gb_user_roles->add_cap( $this->name, $cap, $grant );
	}

	/**
	 * Remove capability from role.
	 *
	 * This is a container for {@link GB_Roles::remove_cap()} to remove the
	 * capability from the role. That is to say, that {@link
	 * GB_Roles::remove_cap()} implements the functionality, but it also makes
	 * sense to use this class, because you don't need to enter the role name.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $cap ) {
		global $gb_user_roles;

		if ( ! isset( $gb_user_roles ) )
			$gb_user_roles = new GB_User_Roles();

		unset( $this->capabilities[$cap] );
		$gb_user_roles->remove_cap( $this->name, $cap );
	}

	/**
	 * Whether role has capability.
	 *
	 * The capabilities is passed through the 'role_has_cap' filter. The first
	 * parameter for the hook is the list of capabilities the class has
	 * assigned. The second parameter is the capability name to look for. The
	 * third and final parameter for the hook is the role name.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @return bool True, if user has capability. False, if doesn't have capability.
	 */
	public function has_cap( $cap ) {
		/**
		 * Filter which capabilities a role has.
		 *
		 * @since 2.3.0
		 *
		 * @param array  $capabilities Array of role capabilities.
		 * @param string $cap          Capability name.
		 * @param string $name         Role name.
		 */
		$capabilities = apply_filters( 'role_has_cap', $this->capabilities, $cap, $this->name );
		if ( !empty( $capabilities[$cap] ) )
			return $capabilities[$cap];
		else
			return false;
	}
}

/**
 * Whether current user has capability or role.
 *
 * @since 2.3.0
 *
 * @param string $capability Capability or role name.
 * @return bool
 */
function current_user_can( $capability ) {
	$current_user = gb_get_current_user();

	if ( empty( $current_user ) )
		return false;

	$args = array_slice( func_get_args(), 1 );
	$args = array_merge( array( $capability ), $args );

	return call_user_func_array( array( $current_user, 'has_cap' ), $args );
}

/**
 * Whether author of supplied post has capability or role.
 *
 * @since 2.3.0
 *
 * @param int|object $post Post ID or post object.
 * @param string $capability Capability or role name.
 * @return bool
 */
function author_can( $post, $capability ) {
	if ( !$post = get_post($post) )
		return false;

	$author = get_userdata( $post->post_author );

	if ( ! $author )
		return false;

	$args = array_slice( func_get_args(), 2 );
	$args = array_merge( array( $capability ), $args );

	return call_user_func_array( array( $author, 'has_cap' ), $args );
}

/**
 * Whether a particular user has capability or role.
 *
 * @since 2.3.0
 *
 * @param int|object $user User ID or object.
 * @param string $capability Capability or role name.
 * @return bool
 */
function user_can( $user, $capability ) {
	if ( ! is_object( $user ) )
		$user = get_userdata( $user );

	if ( ! $user || ! $user->exists() )
		return false;

	$args = array_slice( func_get_args(), 2 );
	$args = array_merge( array( $capability ), $args );

	return call_user_func_array( array( $user, 'has_cap' ), $args );
}

/**
 * Retrieve role object.
 *
 * @see GB_Roles::get_role() Uses method to retrieve role object.
 * @since 2.3.0
 *
 * @param string $role Role name.
 * @return GB_Role|null GB_Role object if found, null if the role does not exist.
 */
function get_role( $role ) {
	global $gb_user_roles;

	if ( ! isset( $gb_user_roles ) )
		$gb_user_roles = new GB_User_Roles();

	return $gb_user_roles->get_role( $role );
}

/**
 * Add role, if it does not exist.
 *
 * @see GB_Roles::add_role() Uses method to add role.
 * @since 2.3.0
 *
 * @param string $role Role name.
 * @param string $display_name Display name for role.
 * @param array $capabilities List of capabilities, e.g. array( 'edit_posts' => true, 'delete_posts' => false );
 * @return GB_Role|null GB_Role object if role is added, null if already exists.
 */
function add_role( $role, $display_name, $capabilities = array() ) {
	global $gb_user_roles;

	if ( ! isset( $gb_user_roles ) )
		$gb_user_roles = new GB_User_Roles();

	return $gb_user_roles->add_role( $role, $display_name, $capabilities );
}

/**
 * Remove role, if it exists.
 *
 * @see GB_Roles::remove_role() Uses method to remove role.
 * @since 2.3.0
 *
 * @param string $role Role name.
 */
function remove_role( $role ) {
	global $gb_user_roles;

	if ( ! isset( $gb_user_roles ) )
		$gb_user_roles = new GB_User_Roles();

	$gb_user_roles->remove_role( $role );
}

/**
 * Retrieve a list of super admins.
 *
 * @since 2.3.0
 *
 * @uses $super_admins Super admins global variable, if set.
 *
 * @return array List of super admin logins
 */
function get_super_admins() {
	global $super_admins;

	if ( isset($super_admins) )
		return $super_admins;
	else
		return get_site_option( 'site_admins', array('admin') );
}

/**
 * Determine if user is a site admin.
 *
 * @since 2.3.0
 *
 * @param int $user_id (Optional) The ID of a user. Defaults to the current user.
 * @return bool True if the user is a site admin.
 */
function is_super_admin( $user_id = false ) {
	if ( ! $user_id || $user_id == get_current_user_id() )
		$user = gb_get_current_user();
	else
		$user = get_userdata( $user_id );

	if ( ! $user || ! $user->exists() )
		return false;

	if ( is_multisite() ) {
		$super_admins = get_super_admins();
		if ( is_array( $super_admins ) && in_array( $user->user_login, $super_admins ) )
			return true;
	} else {
		if ( $user->has_cap('delete_users') )
			return true;
	}

	return false;
}


/**
 * Update all user caches
 *
 * @since 2.3.0
 *
 * @param object $user User object to be cached
 */
function gb_update_user_caches($user) {
	gb_cache_add($user->ID, $user, 'users');
	gb_cache_add($user->user_email, $user->ID, 'user_emails');
}

/**
 * Clean all user caches
 *
 * @since 2.3.0
 *
 * @param GB_User|int $user User object or ID to be cleaned from the cache
 */
function gb_clean_user_cache( $user ) {
	if ( is_numeric( $user ) )
		$user = new GB_User( $user );

	if ( !$user->exists() )
		return;

	gb_cache_delete( $user->ID, 'users' );
	gb_cache_delete( $user->user_email, 'user_emails' );
}

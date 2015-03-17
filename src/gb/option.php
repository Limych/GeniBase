<?php
/**
 * Option API
 *
 * @package GeniBase
 * @subpackage Option
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

/**
 * Retrieve option value based on name of option.
 *
 * If the option does not exist or does not have a value, then the return value
 * will be false. This is useful to check whether you need to install an option
 * and is commonly used during installation of plugin options and to test
 * whether upgrading is required.
 *
 * If the option was serialized then it will be unserialized when it is returned.
 *
 * @since 2.0.0
 *
 * @param string $option Name of option to retrieve. Expected to not be SQL-escaped.
 * @param mixed $default Optional. Default value to return if the option does not exist.
 * @return mixed Value set for the option.
 */
function get_option( $option, $default = false ) {
	$option = trim( $option );
	if( empty( $option ) )
		return false;

	/**
	 * Filter the value of an existing option before it is retrieved.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * Passing a truthy value to the filter will short-circuit retrieving
	 * the option value, returning the passed value instead.
	 *
	 * @since 2.0.0
	 *
	 * @param bool|mixed $pre_option Value to return instead of the option value.
	 *                               Default false to skip it.
	 */
// 	$pre = apply_filters( 'pre_option_' . $option, false );
// 	if( false !== $pre )
// 		return $pre;

	if( defined('GB_SETUP_CONFIG'))
		return false;

	if( defined('GB_INSTALLING')){
		$suppress = gbdb()->suppress_errors();
		$row = gbdb()->get_row('SELECT option_value FROM ?_options WHERE option_name = ?key LIMIT 1',
				array('key' => $option));
		gbdb()->suppress_errors($suppress);
		if( !empty($row))
			$value = $row['option_value'];
		else{
			/** This filter is documented in gb/option.php */
// 			return apply_filters( 'default_option_' . $option, $default );
			return $default;
		}

	}else{
		// prevent non-existent options from triggering multiple queries
		$notoptions = gb_cache_get( 'notoptions', 'options' );
		if( false !== $notoptions && isset($notoptions[$option])){
			/**
			 * Filter the default value for an option.
			 *
			 * The dynamic portion of the hook name, `$option`, refers to the option name.
			 *
			 * @since 2.0.0
			 *
			 * @param mixed $default The default value to return if the option does not exist
			 *                       in the database.
			 */
// 			return apply_filters( 'default_option_' . $option, $default );
			return $default;
		}

		$alloptions = gb_load_alloptions();

		if( isset($alloptions[$option]))
			$value = $alloptions[$option];
		else{
			$value = gb_cache_get($option, 'options');
			if( false === $value){
				$row = gbdb()->get_row('SELECT option_value FROM ?_options' .
						' WHERE option_name = ?key LIMIT 1', array('key' => $option));
				if( !empty($row)){
					$value = $row['option_value'];
					gb_cache_add($option, $value, 'options');

				}else{ // option does not exist, so we must cache its non-existence
					$notoptions[$option] = true;
					gb_cache_set('notoptions', $notoptions, 'options');

					/** This filter is documented in gb/option.php */
// 					return apply_filters( 'default_option_' . $option, $default );
					return $default;
				}
			}
		}
	} // if !GB_INSTALLING

	// If home is not set use siteurl.
	if( 'home' == $option && '' == $value)
		return get_option('siteurl');

// 	if( in_array( $option, array('siteurl', 'home') ) )
// 		$value = untrailingslashit( $value );

	/**
	 * Filter the value of an existing option.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Value of the option. If stored serialized, it will be
	 *                     unserialized prior to being returned.
	 */
// 	return apply_filters( 'option_' . $option, maybe_unserialize( $value ) );
	return maybe_unserialize($value);
}

/**
 * Protect GeniBase special option from being modified.
 *
 * Will die if $option is in protected list. Protected options are 'alloptions'
 * and 'notoptions' options.
 *
 * @since 2.0.0
 *
 * @param string $option Option name.
 */
function gb_protect_special_option( $option ) {
	if( 'alloptions' === $option || 'notoptions' === $option )
		gb_die( sprintf( __( '%s is a protected GB option and may not be modified' ),
				esc_html( $option ) ) );
}

/**
 * Print option value after sanitizing for forms.
 *
 * @since 2.0.0
 *
 * @param string $option Option name.
 */
function form_option( $option ) {
	echo esc_attr( get_option( $option ) );
}

/**
 * Loads and caches all autoloaded options, if available or all options.
 *
 * @since 2.0.0
 *
 * @return array List of all options.
 */
function gb_load_alloptions() {
	$alloptions = gb_cache_get( 'alloptions', 'options' );

	if( !$alloptions ) {
		$suppress = $wpdb->suppress_errors();
		if( !$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload = 'yes'" ) )
			$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" );
		$wpdb->suppress_errors($suppress);
		$alloptions = array();
		foreach ( (array) $alloptions_db as $o ) {
			$alloptions[$o->option_name] = $o->option_value;
		}
		if( !defined( 'GB_INSTALLING' ) || !is_multisite() )
			gb_cache_add( 'alloptions', $alloptions, 'options' );
	}

	return $alloptions;
}

/**
 * Loads and caches certain often requested site options if is_multisite() and a persistent cache is not being used.
 *
 * @since 2.0.0
 *
 * @param int $site_id Optional site ID for which to query the options. Defaults to the current site.
 */
function gb_load_core_site_options( $site_id = null ) {
	global $wpdb;

	if( !is_multisite() || gb_using_ext_object_cache() || defined( 'GB_INSTALLING' ) )
		return;

	if( empty($site_id) )
		$site_id = $wpdb->siteid;

	$core_options = array('site_name', 'siteurl', 'active_sitewide_plugins', '_site_transient_timeout_theme_roots', '_site_transient_theme_roots', 'site_admins', 'can_compress_scripts', 'global_terms_enabled', 'ms_files_rewriting' );

	$core_options_in = "'" . implode("', '", $core_options) . "'";
	$options = $wpdb->get_results( $wpdb->prepare("SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE meta_key IN ($core_options_in) AND site_id = %d", $site_id) );

	foreach ( $options as $option ) {
		$key = $option->meta_key;
		$cache_key = "{$site_id}:$key";
		$option->meta_value = maybe_unserialize( $option->meta_value );

		gb_cache_set( $cache_key, $option->meta_value, 'site-options' );
	}
}

/**
 * Update the value of an option that was already added.
 *
 * You do not need to serialize values. If the value needs to be serialized, then
 * it will be serialized before it is inserted into the database. Remember,
 * resources can not be serialized or added as an option.
 *
 * If the option does not exist, then the option will be added with the option
 * value, but you will not be able to set whether it is autoloaded. If you want
 * to set whether an option is autoloaded, then you need to use the add_option().
 *
 * @since 2.0.0
 *
 * @param string $option Option name. Expected to not be SQL-escaped.
 * @param mixed $value Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @return bool False if value was not updated and true if value was updated.
 */
function update_option( $option, $value ) {
	global $wpdb;

	$option = trim($option);
	if( empty($option) )
		return false;

	gb_protect_special_option( $option );

	if( is_object( $value ) )
		$value = clone $value;

	$value = sanitize_option( $option, $value );
	$old_value = get_option( $option );

	/**
	 * Filter a specific option before its value is (maybe) serialized and updated.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value     The new, unserialized option value.
	 * @param mixed $old_value The old option value.
	 */
	$value = apply_filters( 'pre_update_option_' . $option, $value, $old_value );

	/**
	 * Filter an option before its value is (maybe) serialized and updated.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value     The new, unserialized option value.
	 * @param string $option    Name of the option.
	 * @param mixed  $old_value The old option value.
	 */
	$value = apply_filters( 'pre_update_option', $value, $option, $old_value );

	// If the new and old values are the same, no need to update.
	if( $value === $old_value )
		return false;

	if( false === $old_value )
		return add_option( $option, $value );

	$serialized_value = maybe_serialize( $value );

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option    Name of the option to update.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	do_action( 'update_option', $option, $old_value, $value );

	$result = $wpdb->update( $wpdb->options, array( 'option_value' => $serialized_value ), array( 'option_name' => $option ) );
	if( !$result )
		return false;

	$notoptions = gb_cache_get( 'notoptions', 'options' );
	if( is_array( $notoptions ) && isset( $notoptions[$option] ) ) {
		unset( $notoptions[$option] );
		gb_cache_set( 'notoptions', $notoptions, 'options' );
	}

	if( !defined( 'GB_INSTALLING' ) ) {
		$alloptions = gb_load_alloptions();
		if( isset( $alloptions[$option] ) ) {
			$alloptions[ $option ] = $serialized_value;
			gb_cache_set( 'alloptions', $alloptions, 'options' );
		} else {
			gb_cache_set( $option, $serialized_value, 'options' );
		}
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $value     The new option value.
	 */
	do_action( "update_option_{$option}", $old_value, $value );

	/**
	 * Fires after the value of an option has been successfully updated.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	do_action( 'updated_option', $option, $old_value, $value );
	return true;
}

/**
 * Add a new option.
 *
 * You do not need to serialize values. If the value needs to be serialized, then
 * it will be serialized before it is inserted into the database. Remember,
 * resources can not be serialized or added as an option.
 *
 * You can create options without values and then update the values later.
 * Existing options will not be updated and checks are performed to ensure that you
 * aren't adding a protected WordPress option. Care should be taken to not name
 * options the same as the ones which are protected.
 *
 * @since 2.0.0
 *
 * @param string         $option      Name of option to add. Expected to not be SQL-escaped.
 * @param mixed          $value       Optional. Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @param string         $deprecated  Optional. Description. Not used anymore.
 * @param string|bool    $autoload    Optional. Default is enabled. Whether to load the option when WordPress starts up.
 * @return bool False if option was not added and true if option was added.
 */
function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
	global $wpdb;

	if( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.3' );

	$option = trim($option);
	if( empty($option) )
		return false;

	gb_protect_special_option( $option );

	if( is_object($value) )
		$value = clone $value;

	$value = sanitize_option( $option, $value );

	// Make sure the option doesn't already exist. We can check the 'notoptions' cache before we ask for a db query
	$notoptions = gb_cache_get( 'notoptions', 'options' );
	if( !is_array( $notoptions ) || !isset( $notoptions[$option] ) )
		if( false !== get_option( $option ) )
			return false;

	$serialized_value = maybe_serialize( $value );
	$autoload = ( 'no' === $autoload ) ? 'no' : 'yes';

	/**
	 * Fires before an option is added.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( 'add_option', $option, $value );

	$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) );
	if( !$result )
		return false;

	if( !defined( 'GB_INSTALLING' ) ) {
		if( 'yes' == $autoload ) {
			$alloptions = gb_load_alloptions();
			$alloptions[ $option ] = $serialized_value;
			gb_cache_set( 'alloptions', $alloptions, 'options' );
		} else {
			gb_cache_set( $option, $serialized_value, 'options' );
		}
	}

	// This option exists now
	$notoptions = gb_cache_get( 'notoptions', 'options' ); // yes, again... we need it to be fresh
	if( is_array( $notoptions ) && isset( $notoptions[$option] ) ) {
		unset( $notoptions[$option] );
		gb_cache_set( 'notoptions', $notoptions, 'options' );
	}

	/**
	 * Fires after a specific option has been added.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.0.0 As "add_option_{$name}"
	 * @since 2.0.0
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( "add_option_{$option}", $option, $value );

	/**
	 * Fires after an option has been added.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option Name of the added option.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( 'added_option', $option, $value );
	return true;
}

/**
 * Removes option by name. Prevents removal of protected WordPress options.
 *
 * @since 2.0.0
 *
 * @param string $option Name of option to remove. Expected to not be SQL-escaped.
 * @return bool True, if option is successfully deleted. False on failure.
 */
function delete_option( $option ) {
	global $wpdb;

	$option = trim( $option );
	if( empty( $option ) )
		return false;

	gb_protect_special_option( $option );

	// Get the ID, if no ID then return
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) );
	if( is_null( $row ) )
		return false;

	/**
	 * Fires immediately before an option is deleted.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option Name of the option to delete.
	 */
	do_action( 'delete_option', $option );

	$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
	if( !defined( 'GB_INSTALLING' ) ) {
		if( 'yes' == $row->autoload ) {
			$alloptions = gb_load_alloptions();
			if( is_array( $alloptions ) && isset( $alloptions[$option] ) ) {
				unset( $alloptions[$option] );
				gb_cache_set( 'alloptions', $alloptions, 'options' );
			}
		} else {
			gb_cache_delete( $option, 'options' );
		}
	}
	if( $result ) {

		/**
		 * Fires after a specific option has been deleted.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 2.0.0
		 *
		 * @param string $option Name of the deleted option.
		 */
		do_action( "delete_option_$option", $option );

		/**
		 * Fires after an option has been deleted.
		 *
		 * @since 2.0.0
		 *
		 * @param string $option Name of the deleted option.
		 */
		do_action( 'deleted_option', $option );
		return true;
	}
	return false;
}

/**
 * Delete a transient.
 *
 * @since 2.0.0
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return bool true if successful, false otherwise
 */
function delete_transient( $transient ) {

	/**
	 * Fires immediately before a specific transient is deleted.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $transient Transient name.
	 */
	do_action( 'delete_transient_' . $transient, $transient );

	if( gb_using_ext_object_cache() ) {
		$result = gb_cache_delete( $transient, 'transient' );
	} else {
		$option_timeout = '_transient_timeout_' . $transient;
		$option = '_transient_' . $transient;
		$result = delete_option( $option );
		if( $result )
			delete_option( $option_timeout );
	}

	if( $result ) {

		/**
		 * Fires after a transient is deleted.
		 *
		 * @since 2.0.0
		 *
		 * @param string $transient Deleted transient name.
		 */
		do_action( 'deleted_transient', $transient );
	}

	return $result;
}

/**
 * Get the value of a transient.
 *
 * If the transient does not exist, does not have a value, or has expired,
 * then the return value will be false.
 *
 * @since 2.0.0
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_transient( $transient ) {

 	/**
	 * Filter the value of an existing transient.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * Passing a truthy value to the filter will effectively short-circuit retrieval
	 * of the transient, returning the passed value instead.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $pre_transient The default value to return if the transient does not exist.
	 *                             Any value other than false will short-circuit the retrieval
	 *                             of the transient, and return the returned value.
	 */
	$pre = apply_filters( 'pre_transient_' . $transient, false );
	if( false !== $pre )
		return $pre;

	if( gb_using_ext_object_cache() ) {
		$value = gb_cache_get( $transient, 'transient' );
	} else {
		$transient_option = '_transient_' . $transient;
		if( !defined( 'GB_INSTALLING' ) ) {
			// If option is not in alloptions, it is not autoloaded and thus has a timeout
			$alloptions = gb_load_alloptions();
			if( !isset( $alloptions[$transient_option] ) ) {
				$transient_timeout = '_transient_timeout_' . $transient;
				if( get_option( $transient_timeout ) < time() ) {
					delete_option( $transient_option  );
					delete_option( $transient_timeout );
					$value = false;
				}
			}
		}

		if( !isset( $value ) )
			$value = get_option( $transient_option );
	}

	/**
	 * Filter an existing transient's value.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Value of transient.
	 */
	return apply_filters( 'transient_' . $transient, $value );
}

/**
 * Set/update the value of a transient.
 *
 * You do not need to serialize values. If the value needs to be serialized, then
 * it will be serialized before it is set.
 *
 * @since 2.0.0
 *
 * @param string $transient  Transient name. Expected to not be SQL-escaped. Must be
 *                           45 characters or fewer in length.
 * @param mixed  $value      Transient value. Must be serializable if non-scalar.
 *                           Expected to not be SQL-escaped.
 * @param int    $expiration Optional. Time until expiration in seconds. Default 0.
 * @return bool False if value was not set and true if value was set.
 */
function set_transient( $transient, $value, $expiration = 0 ) {

	/**
	 * Filter a specific transient before its value is set.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value New value of transient.
	 */
	$value = apply_filters( 'pre_set_transient_' . $transient, $value );

	$expiration = (int) $expiration;

	if( gb_using_ext_object_cache() ) {
		$result = gb_cache_set( $transient, $value, 'transient', $expiration );
	} else {
		$transient_timeout = '_transient_timeout_' . $transient;
		$transient = '_transient_' . $transient;
		if( false === get_option( $transient ) ) {
			$autoload = 'yes';
			if( $expiration ) {
				$autoload = 'no';
				add_option( $transient_timeout, time() + $expiration, '', 'no' );
			}
			$result = add_option( $transient, $value, '', $autoload );
		} else {
			// If expiration is requested, but the transient has no timeout option,
			// delete, then re-create transient rather than update.
			$update = true;
			if( $expiration ) {
				if( false === get_option( $transient_timeout ) ) {
					delete_option( $transient );
					add_option( $transient_timeout, time() + $expiration, '', 'no' );
					$result = add_option( $transient, $value, '', 'no' );
					$update = false;
				} else {
					update_option( $transient_timeout, time() + $expiration );
				}
			}
			if( $update ) {
				$result = update_option( $transient, $value );
			}
		}
	}

	if( $result ) {

		/**
		 * Fires after the value for a specific transient has been set.
		 *
		 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
		 *
		 * @since 2.0.0
		 *
		 * @param mixed $value      Transient value.
		 * @param int   $expiration Time until expiration in seconds. Default 0.
		 */
		do_action( 'set_transient_' . $transient, $value, $expiration );

		/**
		 * Fires after the value for a transient has been set.
		 *
		 * @since 2.0.0
		 *
		 * @param string $transient  The name of the transient.
		 * @param mixed  $value      Transient value.
		 * @param int    $expiration Time until expiration in seconds. Default 0.
		 */
		do_action( 'setted_transient', $transient, $value, $expiration );
	}
	return $result;
}

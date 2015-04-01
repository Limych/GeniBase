<?php
/**
 * Options API
 *
 * @package GeniBase
 * @subpackage Options
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

/**
 * GeniBase Options.
 *
 * @package GeniBase
 * @subpackage Options
 * @since	2.3.0
 */
class GB_Options {
	static protected $_cache = array();

	static protected function _cache_set($key, $data, $group = '', $expire = 0){
		if( self::$has_cache )
			return gb_cache_set($key, $data, $group, $expire);

		$hash = "$group::$key";
		self::$_cache[$hash] = $data;
		return true;
	}

	static protected function _cache_add($key, $data, $group = '', $expire = 0){
		if( self::$has_cache )
			return gb_cache_add($key, $data, $group, $expire);

		$hash = "$group::$key";
		if( isset(self::$_cache[$hash]) )
			return false;
		self::$_cache[$hash] = $data;
		return true;
	}
	
	static protected function _cache_get($key, $group = '', $force = false, &$found = null){
		if( self::$has_cache )
			return gb_cache_get($key, $group, $force, $found);

		$hash = "$group::$key";
		if( !isset(self::$_cache[$hash]) )
			return false;
		return self::$_cache[$hash];
	}
	
	static protected function _cache_delete($key, $group = ''){
		if( self::$has_cache )
			return gb_cache_delete($key, $group);

		if( !isset(self::$_cache[$hash]) )
			return false;
		unset(self::$_cache[$hash]);
		return true;
	}

	static $has_hooks = false;
	static $has_cache = false;

	static function init($reinitialize = false){
		static $initialized = false;

		if( $initialized && !$reinitialize )
			return;
		$initialized = true;

		self::$cache = array();
		self::$has_cache = function_exists('gb_cache_init');
		self::$has_hooks = class_exists('GB_Hooks');
	}

	/**
	 * Build Unique ID for storage options in cache.
	 * 
	 * @since	2.3.0
	 * 
	 * @param string $option Name of option to retrieve. Expected to not be SQL-escaped.
	 * @param string $section Options section name. As a rule it's similar to plugin's name. Optional.
	 * @return string	Global option ID.
	 */
	static function build_hash($option, $section = ''){
		return $section . '::' . $option; 
	}

	/**
	 * Print option value after sanitizing for forms.
	 *
	 * @since	2.3.0
	 *
	 * @param string $section Options section name. As a rule it's similar to plugin's name. Optional.
	 * @param string $option Option name.
	 */
	static function form_option($option, $section = '') {
		echo esc_attr(self::get($option, $section));
	}
	
	/**
	 * Loads and caches all autoloaded options, if available or all options.
	 *
	 * @since	2.3.0
	 *
	 * @return array List of all options.
	 */
	static function load_alloptions() {
		self::init();	// Initialize GB_Options if it needed

		$alloptions = self::_cache_get('alloptions', 'options');
		if( !$alloptions ) {
			$suppress = gbdb()->suppress_errors();
			if(!$alloptions_db = gbdb()->get_table('SELECT section, option_name, option_value FROM ?_options WHERE autoload = "yes"') )
				$alloptions_db = gbdb()->get_table('SELECT section, option_name, option_value FROM ?_options');
			gbdb()->suppress_errors($suppress);
			$alloptions = array();
			foreach( (array) $alloptions_db as $o )
				$alloptions[self::build_hash($o['option_name'], $o['section'])] = $o['option_value'];
			if( !defined('GB_INSTALLING') )
				self::_cache_add('alloptions', $alloptions, 'options');
		}
	
		return $alloptions;
	}
	
	/**
	 * Sanitize option value.
	 * 
	 * @since	2.3.0
	 * @access	private
	 * 
	 * @param string	$section	Options section name. As a rule it's similar to plugin's name.
	 * @param string	$option		Name of option to add. Expected to not be SQL-escaped.
	 * @param mixed		$value		Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
	 * @return mixed	Sanitized option value.
	 */
	static function _sanitize($section, $option, $value){
		self::init();	// Initialize GB_Options if it needed
		if( !self::$has_hooks )
			return $value;

		$option_hash = self::build_hash($option, $section);

		/**
		 * Filter an option value following sanitization.
		 *
		 * @since 2.3.0
		 *
		 * @param string $value			The sanitized option value.
		 * @param string $option_hash	The unique option hash.
		 */
		$value = GB_Hooks::apply_filters("sanitize_option_{$option_hash}", $value, $option_hash);
	
		/**
		 * Filter an option value following sanitization.
		 *
		 * @since 2.3.0
		 *
		 * @param string $value			The sanitized option value.
		 * @param string $option_hash	The unique option hash.
		*/
		$value = GB_Hooks::apply_filters("sanitize_option_section_{$section}", $value, $option_hash);
	
		/**
		 * Filter an option value following sanitization.
		 *
		 * @since 2.3.0
		 *
		 * @param string $value			The sanitized option value.
		 * @param string $option_hash	The unique option hash.
		*/
		$value = GB_Hooks::apply_filters("sanitize_option", $value, $option_hash);
		
		return $value;
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
	 * @since	2.3.0
	 *
	 * @param string		$option		Name of option to add. Expected to not be SQL-escaped.
	 * @param mixed			$value		Optional. Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
	 * @param string		$section	Options section name. As a rule it's similar to plugin's name. Optional.
	 * @param bool|mixed	$autoload	Optional. Default is enabled. Whether to load the option when GeniBase starts up.
	 * @return bool		False if option was not added and true if option was added.
	 */
	static function add($option, $value = '', $section = '', $autoload = 'yes') {
		$option = trim($option);
		if( empty($option) )
			return false;
		$option_hash = self::build_hash($option, $section);

		if( is_object($value) )
			$value = clone $value;

		self::init();	// Initialize GB_Options if it needed
		$value = self::_sanitize($section, $option, $value);

		// Make sure the option doesn't already exist. We can check the 'notoptions' cache before we ask for a db query
		$notoptions = self::_cache_get('notoptions', 'options');
		if( !is_array($notoptions) || !isset($notoptions[$option]) )
			if( false !== self::get($option) )
				return false;

			$serialized_value = maybe_serialize($value);
			$autoload = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';

			if( self::$has_hooks ){
				/**
				 * Fires before an option is added.
				 *
				 * @since	2.3.0
				 *
				 * @param string $option Name of the option to add.
				 * @param mixed  $value  Value of the option.
				 */
				GB_Hooks::do_action('add_option', $option_hash, $value);
			}

			$result = gbdb()->set_row('?_options', array(
						'section'		=> $section,
						'option_name'	=> $option,
						'option_value'	=> $value,
						'autoload'		=> $autoload,
					), array('section', 'option_name'), GB_DBase::MODE_DUPLICATE);
			if( !$result )
				return false;
	
			if( !defined('GB_INSTALLING') ) {
				if( 'yes' == $autoload ) {
					$alloptions = self::load_alloptions();
					$alloptions[$option_hash] = $serialized_value;
					self::_cache_set('alloptions', $alloptions, 'options');
				}else{
					self::_cache_set($option, $serialized_value, 'options');
				}
			}
	
			// This option exists now
			$notoptions = self::_cache_get('notoptions', 'options'); // yes, again… we need it to be fresh
			if( is_array($notoptions) && isset($notoptions[$option]) ) {
				unset( $notoptions[$option] );
				self::_cache_set('notoptions', $notoptions, 'options');
			}
	
			/**
			 * Fires after a specific option has been added.
			 *
			 * The dynamic portion of the hook name, `$option`, refers to the option name.
			 *
			 * @since	2.3.0 As "add_option_{$name}"
			 * @since	2.3.0
			 *
			 * @param string $option Name of the option to add.
			 * @param mixed  $value  Value of the option.
			 */
			GB_Hooks::do_action( "add_option_{$option}", $option, $value );
	
			/**
			 * Fires after an option has been added.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option Name of the added option.
			 * @param mixed  $value  Value of the option.
		 */
			GB_Hooks::do_action( 'added_option', $option, $value );
			return true;
	}

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
	 * @since	2.3.0
	 *
	 * @param string	$option		Name of option to retrieve. Expected to not be SQL-escaped.
	 * @param string	$section	Options section name. As a rule it's similar to plugin's name. Optional.
	 * @param mixed		$default	Optional. Default value to return if the option does not exist.
	 * @return mixed	Value set for the option.
	 */
	static function get($option, $section = '', $default = false) {
		$option = trim($option);
		if( empty($option) )
			return false;
		$option_hash = self::build_hash($option, $section);

		self::init();	// Initialize GB_Options if it needed
		if( self::$has_hooks ){
			/**
			 * Filter the value of an existing option before it is retrieved.
			 *
			 * The dynamic portion of the hook name, `$option_hash`, refers to the unique
			 * option hash.
			 *
			 * Passing a truthy value to the filter will short-circuit retrieving
			 * the option value, returning the passed value instead.
			 *
			 * @since	2.3.0
			 *
			 * @param bool|mixed $pre_option Value to return instead of the option value.
			 *                               Default false to skip it.
			 */
			$pre = GB_Hooks::apply_filters('pre_option_' . $option_hash, false);
			if( false !== $pre )
				return $pre;
		}
	
		if( defined('GB_SETUP_CONFIG') )
			return false;
	
		if( defined('GB_INSTALLING') ){
			$suppress = gbdb()->suppress_errors();
			$row = gbdb()->get_row('SELECT option_value FROM ?_options ' .
					'WHERE section = ?section AND option_name = ?key LIMIT 1',
					array('section' => $section, 'key' => $option));
			gbdb()->suppress_errors($suppress);
			if( !empty($row) )
				$value = $row['option_value'];
			elseif( self::$has_hooks ){
				/**
				 * Filter the default value for an option.
				 *
				 * The dynamic portion of the hook name, `$option_hash`, refers to the unique
				 * option hash.
				 *
				 * @since	2.3.0
				 *
				 * @param mixed $default The default value to return if the option does not exist
				 *                       in the database.
				 */
				return GB_Hooks::apply_filters('default_option_' . $option_hash, $default);
			}else
				return $default;
	
		}else{
			// prevent non-existent options from triggering multiple queries
			$notoptions = self::_cache_get('notoptions', 'options');
			if( false !== $notoptions && isset($notoptions[$option_hash])){
				if( self::$has_hooks ){
					/** This filter is documented in gb/class.gb-options.php */
					return GB_Hooks::apply_filters('default_option_' . $option_hash, $default);
				}else
					return $default;
			}
	
			$alloptions = self::load_alloptions();
	
			if( isset($alloptions[$option_hash]) )
				$value = $alloptions[$option_hash];
			else{
				$value = self::_cache_get($option_hash, 'options');
				if( false === $value ){
					$row = gbdb()->get_row('SELECT option_value FROM ?_options' .
							' WHERE section = ?section AND option_name = ?key LIMIT 1',
							array('section' => $section, 'key' => $option));
					if( !empty($row) ){
						$value = $row['option_value'];
						self::_cache_add($option_hash, $value, 'options');
	
					}else{ // option does not exist, so we must cache its non-existence
						$notoptions[$option_hash] = true;
						self::_cache_set('notoptions', $notoptions, 'options');
	
						if( self::$has_hooks ){
							/** This filter is documented in gb/class.gb-options.php */
							return GB_Hooks::apply_filters('default_option_' . $option_hash, $default);
						}else
							return $default;
					}
				}
			}
		} // if !GB_INSTALLING
	
		// If home is not set use siteurl.
		if( 'home' == $option && '' == $value )
			return self::get('siteurl');
	
// 		if( in_array($option, array('siteurl', 'home')) )
// 			$value = untrailingslashit($value);
	
		$value = maybe_unserialize($value);
		if( self::$has_hooks ){
			/**
			 * Filter the value of an existing option.
			 *
			 * The dynamic portion of the hook name, `$option_hash`, refers to the unique
			 * option hash.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $value Value of the option. If stored serialized, it will be
			 *                     unserialized prior to being returned.
			 */
			$value = GB_Hooks::apply_filters('option_' . $option_hash, $value);
		}
		return $value;
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
	 * to set whether an option is autoloaded, then you need to use the self::add().
	 *
	 * @since	2.3.0
	 *
	 * @param string	$option		Option name. Expected to not be SQL-escaped.
	 * @param mixed		$value		Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
	 * @param string	$section	Options section name. As a rule it's similar to plugin's name. Optional.
	 * @return bool		False if value was not updated and true if value was updated.
	 */
	static function update($option, $value, $section = '') {
		$option = trim($option);
		if( empty($option) )
			return false;
		$option_hash = self::build_hash($option, $section);

		if( is_object($value) )
			$value = clone $value;

		self::init();	// Initialize GB_Options if it needed
		$value = self::_sanitize($section, $option, $value);
		$old_value = self::get($option);

		if( self::$has_hooks ){
			/**
			 * Filter a specific option before its value is (maybe) serialized and updated.
			 *
			 * The dynamic portion of the hook name, `$option_hash`, refers to the unique
			 * option hash.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $value     The new, unserialized option value.
			 * @param mixed $old_value The old option value.
			 */
			$value = GB_Hooks::apply_filters("pre_update_option_{$option_hash}", $value, $old_value);

			/**
			 * Filter an option before its value is (maybe) serialized and updated.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed  $value			The new, unserialized option value.
			 * @param string $option_hash	The unique option hash.
			 * @param mixed  $old_value		The old option value.
			 */
			$value = GB_Hooks::apply_filters('pre_update_option', $value, $option_hash, $old_value);
		}

		// If the new and old values are the same, no need to update.
		if( $value === $old_value )
			return false;
	
		if( false === $old_value )
			return self::add($option, $value);
	
		$serialized_value = maybe_serialize($value);
	
		if( self::$has_hooks ){
			/**
			 * Fires after the value of a specific option has been successfully updated.
			 *
			 * The dynamic portion of the hook name, `$option_hash`, refers to the unique
			 * option hash.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $old_value The old option value.
			 * @param mixed $value     The new option value.
			 */
			GB_Hooks::do_action("update_option_{$option_hash}", $old_value, $value);

			/**
			 * Fires immediately before an option value is updated.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option_hash	The unique hash of option to update.
			 * @param mixed  $old_value		The old option value.
			 * @param mixed  $value			The new option value.
			 */
			GB_Hooks::do_action("update_option_section_{$section}", $option_hash, $old_value, $value);

			/**
			 * Fires immediately before an option value is updated.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option_hash	The unique hash of option to update.
			 * @param mixed  $old_value		The old option value.
			 * @param mixed  $value			The new option value.
			 */
			GB_Hooks::do_action('update_option', $option_hash, $old_value, $value);
		}
	
		$result = gbdb()->set_row('?_options', array(
					'section'		=> $section,
					'option_name'	=> $option,
					'option_value'	=> $serialized_value,
				), array('section', 'option_name'), GB_DBase::MODE_UPDATE);
		if( !$result )
			return false;
	
		$notoptions = self::_cache_get('notoptions', 'options');
		if( is_array($notoptions) && isset($notoptions[$option]) ) {
			unset( $notoptions[$option] );
			self::_cache_set('notoptions', $notoptions, 'options');
		}
	
		if( !defined('GB_INSTALLING') ) {
			$alloptions = self::load_alloptions();
			if( isset( $alloptions[$option] ) ) {
				$alloptions[ $option ] = $serialized_value;
				self::_cache_set('alloptions', $alloptions, 'options');
			}else
				self::_cache_set($option_hash, $serialized_value, 'options');
		}
	
		if( self::$has_hooks ){
			/**
			 * Fires after the value of an option has been successfully updated.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option_hash	The unique hash of the updated option.
			 * @param mixed  $old_value		The old option value.
			 * @param mixed  $value			The new option value.
			 */
			GB_Hooks::do_action("updated_option_{$option_hash}", $old_value, $value);

			/**
			 * Fires after the value of an option has been successfully updated.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option_hash	The unique hash of the updated option.
			 * @param mixed  $old_value		The old option value.
			 * @param mixed  $value			The new option value.
			 */
			GB_Hooks::do_action("updated_option_section_{$section}", $option_hash, $old_value, $value);

			/**
			 * Fires after the value of an option has been successfully updated.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option_hash	The unique hash of the updated option.
			 * @param mixed  $old_value		The old option value.
			 * @param mixed  $value			The new option value.
			 */
			GB_Hooks::do_action('updated_option', $option_hash, $old_value, $value);
		}
		return true;
	}

	/**
	 * Removes option by name. Prevents removal of protected GeniBase options.
	 *
	 * @since	2.3.0
	 *
	 * @param string $option Name of option to remove. Expected to not be SQL-escaped.
	 * @param string	$section	Options section name. As a rule it's similar to plugin's name. Optional.
	 * @return bool True, if option is successfully deleted. False on failure.
	 */
	static function delete($option, $section = '') {
		$option = trim($option);
		if( empty($option) )
			return false;
		$option_hash = self::build_hash($option, $section);

		// Checking that option there exists and retrieve record's 'autoload' state
		$row = gbdb()->get_cell('SELECT autoload FROM ?_options WHERE section = ?section AND option_name = ?key',
				array(
						'section'	=> $section,
						'key'		=> $option,
				));
		if( !$row )
			return false;

		self::init();	// Initialize GB_Options if it needed
		if( self::$has_hooks ){
			/**
			 * Fires immediately before an option is deleted.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option Name of the option to delete.
			 */
			GB_Hooks::do_action('delete_option', $option_hash);
		}
	
		$result = gbdb()->delete('?_options', array(
				'section'		=> $section,
				'option_name'	=> $option,
		));
		if( !defined('GB_INSTALLING') ){
			if( 'yes' === $row['autoload'] ) {
				$alloptions = self::load_alloptions();
				if( is_array($alloptions) && isset($alloptions[$option]) ){
					unset($alloptions[$option]);
					self::_cache_set('alloptions', $alloptions, 'options');
				}
			}else{
				self::_cache_delete($option, 'options');
			}
		}
		if( !$result )
			return false;
	
		if( self::$has_hooks ){
			/**
			 * Fires after a specific option has been deleted.
			 *
			 * The dynamic portion of the hook name, `$option`, refers to the option name.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option Name of the deleted option.
			 */
			GB_Hooks::do_action("delete_option_$option_hash", $option_hash);
	
			/**
			 * Fires after an option has been deleted.
			 *
			 * @since	2.3.0
			 *
			 * @param string $option Name of the deleted option.
			*/
			GB_Hooks::do_action('deleted_option', $option_hash);
		}
		return true;
	}

	/**
	 * Set/update the value of a transient.
	 *
	 * You do not need to serialize values. If the value needs to be serialized, then
	 * it will be serialized before it is set.
	 *
	 * @since	2.3.0
	 *
	 * @param string $transient  Transient name. Expected to not be SQL-escaped. Must be
	 *                           45 characters or fewer in length.
	 * @param mixed  $value      Transient value. Must be serializable if non-scalar.
	 *                           Expected to not be SQL-escaped.
	 * @param int    $expiration Optional. Time until expiration in seconds. Default 0.
	 * @return bool False if value was not set and true if value was set.
	 */
	static function set_transient($transient, $value, $expiration = 0) {
		self::init();	// Initialize GB_Options if it needed

		if( self::$has_hooks ){
			/**
			 * Filter a specific transient before its value is set.
			 *
			 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $value New value of transient.
			 */
			$value = GB_Hooks::apply_filters("pre_set_transient_{$transient}", $value);
		}
	
		$expiration = (int) $expiration;
	
		if( gb_using_ext_object_cache() ) {
			$result = self::_cache_set($transient, $value, 'transient', $expiration);

		}else{
			$transient_timeout = '_transient_timeout_' . $transient;
			$transient = '_transient_' . $transient;
			if( false === self::get($transient) ){
				$autoload = 'yes';
				if( $expiration ){
					$autoload = 'no';
					self::add($transient_timeout, time() + $expiration, '', 'no');
				}
				$result = self::add($transient, $value, '', $autoload);

			}else{
				// If expiration is requested, but the transient has no timeout option,
				// delete, then re-create transient rather than update.
				$update = true;
				if( $expiration ) {
					if( false === self::get($transient_timeout) ){
						self::delete($transient);
						self::add($transient_timeout, time() + $expiration, '', false);
						$result = self::add($transient, $value, '', false);
						$update = false;
					}else{
						self::update($transient_timeout, time() + $expiration);
					}
				}
				if( $update ) {
					$result = self::update($transient, $value);
				}
			}
		}
	
		if( $result && self::$has_hooks ) {
			/**
			 * Fires after the value for a specific transient has been set.
			 *
			 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $value      Transient value.
			 * @param int   $expiration Time until expiration in seconds. Default 0.
			 */
			GB_Hooks::do_action("set_transient_{$transient}", $value, $expiration);
	
			/**
			 * Fires after the value for a transient has been set.
			 *
			 * @since	2.3.0
			 *
			 * @param string $transient  The name of the transient.
			 * @param mixed  $value      Transient value.
			 * @param int    $expiration Time until expiration in seconds. Default 0.
			*/
			GB_Hooks::do_action('setted_transient', $transient, $value, $expiration);
		}
		return $result;
	}

	/**
	 * Get the value of a transient.
	 *
	 * If the transient does not exist, does not have a value, or has expired,
	 * then the return value will be false.
	 *
	 * @since	2.3.0
	 *
	 * @param string $transient Transient name. Expected to not be SQL-escaped.
	 * @return mixed Value of transient.
	 */
	static function get_transient($transient) {
		self::init();	// Initialize GB_Options if it needed
		
		if( self::$has_hooks ){
			/**
			 * Filter the value of an existing transient.
			 *
			 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
			 *
			 * Passing a truthy value to the filter will effectively short-circuit retrieval
			 * of the transient, returning the passed value instead.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $pre_transient The default value to return if the transient does not exist.
			 *                             Any value other than false will short-circuit the retrieval
			 *                             of the transient, and return the returned value.
			 */
			$pre = GB_Hooks::apply_filters("pre_transient_{$transient}", false);
			if( false !== $pre )
				return $pre;
		}
	
		if( gb_using_ext_object_cache() ) {
			$value = self::_cache_get($transient, 'transient');

		}else{
			$transient_option = '_transient_' . $transient;
			if( !defined('GB_INSTALLING') ) {
				// If option is not in alloptions, it is not autoloaded and thus has a timeout
				$alloptions = self::load_alloptions();
				if( !isset($alloptions[$transient_option]) ){
					$transient_timeout = '_transient_timeout_' . $transient;
					if( self::get($transient_timeout) < time() ){
						self::delete($transient_option);
						self::delete($transient_timeout);
						$value = false;
					}
				}
			}
	
			if( !isset($value) )
				$value = self::get($transient_option);
		}
	
		if( self::$has_hooks ){
			/**
			 * Filter an existing transient's value.
			 *
			 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
			 *
			 * @since	2.3.0
			 *
			 * @param mixed $value Value of transient.
			 */
			$value = GB_Hooks::apply_filters("transient_{$transient}", $value);
		}
		return $value;
	}

	/**
	 * Delete a transient.
	 *
	 * @since	2.3.0
	 *
	 * @param string $transient Transient name. Expected to not be SQL-escaped.
	 * @return bool true if successful, false otherwise
	 */
	static function delete_transient($transient) {
		self::init();	// Initialize GB_Options if it needed

		if( self::$has_hooks ){
			/**
			 * Fires immediately before a specific transient is deleted.
			 *
			 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
			 *
			 * @since	2.3.0
			 *
			 * @param string $transient Transient name.
			 */
			GB_Hooks::do_action("delete_transient_{$transient}", $transient);
		}
	
		if( gb_using_ext_object_cache() ) {
			$result = self::_cache_delete($transient, 'transient');

		}else{
			$option_timeout = '_transient_timeout_' . $transient;
			$option = '_transient_' . $transient;
			$result = self::delete('', $option);
			if( $result )
				self::delete('', $option_timeout);
		}
	
		if( $result && self::$has_hooks ) {
			/**
			 * Fires after a transient is deleted.
			 *
			 * @since	2.3.0
			 *
			 * @param string $transient Deleted transient name.
			 */
			GB_Hooks::do_action('deleted_transient', $transient);
		}
	
		return $result;
	}
}

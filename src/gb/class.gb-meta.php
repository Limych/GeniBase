<?php
/**
 * Metadata API
 *
 * Functions for retrieving and manipulating metadata of various GeniBase object types. Metadata
 * for an object is a represented by a simple key-value pair. Objects may contain multiple
 * metadata entries that share the same key and differ only in their value.
 *
 * @package GeniBase
 * @subpackage Meta
 * @since 2.3.0
 */

/**
 * Class for manipulating metadata of various GeniBase object types.
 *
 * @since 2.3.0
 */
class GB_Meta {

	/**
	 * Retrieve the name of the metadata table for the specified object type.
	 *
	 * @since	2.3.0
	 * @access	private
	 *
	 * @param string $type Type of object to get metadata table for (e.g., comment, post, or user)
	 * @return mixed Metadata table name, or false if no metadata table exists
	 */
	static function _get_table($type) {
		if ( !in_array($type, array('user')) )
			return false;

		return gbdb()->table_escape($type . 'meta');
	}

	/**
	 * Sanitize meta value.
	 *
	 * @since 2.3.0
	 * @access	private
	 *
	 * @param string $meta_type Type of meta
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value to sanitize
	 * @return mixed Sanitized $meta_value
	 */
	static function _sanitize( $meta_type, $meta_key, $meta_value ) {
	 
		/**
		 * Filter the sanitization of a specific meta key of a specific meta type.
		 *
		 * The dynamic portions of the hook name, `$meta_type`, and `$meta_key`,
		 * refer to the metadata object type (comment, post, or user) and the meta
		 * key value,
		 * respectively.
		 *
		 * @since 2.3.0
		 *
		 * @param mixed  $meta_value Meta value to sanitize.
		 * @param string $meta_key   Meta key.
		 * @param string $meta_type  Meta type.
		 */
		return GB_Hooks::apply_filters( "sanitize_{$meta_type}_meta_{$meta_key}", $meta_value, $meta_key, $meta_type );
	}
	 
	/**
	 * Add metadata for the specified object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $object_id ID of the object metadata is for
	 * @param string $meta_key Metadata key
	 * @param mixed $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool $unique Optional, default is false. Whether the specified metadata key should be
	 * 		unique for the object. If true, and the object already has a value for the specified
	 * 		metadata key, no change will be made
	 * @return int|bool The meta ID on success, false on failure.
	 */
	static function add($meta_type, $object_id, $meta_key, $meta_value, $unique = false) {
		if ( !$meta_type || !$meta_key || !is_numeric( $object_id ) ) {
			return false;
		}
	
		$object_id = absint( $object_id );
		if ( !$object_id ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$column = sanitize_key($meta_type . '_id');
	
		// expected_slashed ($meta_key)
		$meta_key = gb_unslash($meta_key);
		$meta_value = gb_unslash($meta_value);
		$meta_value = self::_sanitize( $meta_type, $meta_key, $meta_value );
	
		/**
		 * Filter whether to add metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user). Returning a non-null value
		 * will effectively short-circuit the function.
		 *
		 * @since 2.3.0
		 *
		 * @param null|bool $check      Whether to allow adding metadata for the given type.
		 * @param int       $object_id  Object ID.
		 * @param string    $meta_key   Meta key.
		 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
		 * @param bool      $unique     Whether the specified meta key should be unique
		 *                              for the object. Optional. Default false.
		 */
		$check = GB_Hooks::apply_filters( "add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique );
		if ( null !== $check )
			return $check;
	
		if ( $unique && gbdb()->get_cell('SELECT COUNT(*) FROM ?@table WHERE meta_key = ?key AND ?#column = ?id',
				array(
						'table'	=> $table,
						'key'	=> $meta_key,
						'column'	=> $column,
						'id'	=> $object_id,
				) ) )
			return false;
	
		$_meta_value = $meta_value;
		$meta_value = maybe_serialize( $meta_value );
	
		/**
		 * Fires immediately before meta of a specific type is added.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.3.0
		 *
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		GB_Hooks::do_action( "add_{$meta_type}_meta", $object_id, $meta_key, $_meta_value );
	
		$mid = gbdb()->set_row( $table, array(
			$column => $object_id,
			'meta_key' => $meta_key,
			'meta_value' => $meta_value
		), array($column, 'meta_key') );
	
		if ( false === $mid )
			return false;
	
		gb_cache_delete($object_id, $meta_type . '_meta');
	
		/**
		 * Fires immediately after meta of a specific type is added.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.3.0
		 *
		 * @param int    $mid        The meta ID after successful update.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		GB_Hooks::do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );
	
		return $mid;
	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $object_id ID of the object metadata is for
	 * @param string $meta_key Optional. Metadata key. If not specified, retrieve all metadata for
	 * 		the specified object.
	 * @param bool $single Optional, default is false. If true, return only the first value of the
	 * 		specified meta_key. This parameter has no effect if meta_key is not specified.
	 * @return string|array Single metadata value, or array of values
	 */
	static function get($meta_type, $object_id, $meta_key = '', $single = false) {
	 	if ( ! $meta_type || ! is_numeric( $object_id ) ) {
	 		return false;
	 	}
	
	 	$object_id = absint( $object_id );
	 	if ( ! $object_id ) {
	 		return false;
	 	}
	
	 	/**
	 	 * Filter whether to retrieve metadata of a specific type.
	 	 *
	 	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 	 * object type (comment, post, or user). Returning a non-null value
	 	 * will effectively short-circuit the function.
	 	 *
	 	 * @since 2.3.0
	 	 *
	 	 * @param null|array|string $value     The value get_metadata() should
	 	 *                                     return - a single metadata value,
	 	 *                                     or an array of values.
	 	 * @param int               $object_id Object ID.
	 	 * @param string            $meta_key  Meta key.
	 	 * @param string|array      $single    Meta value, or an array of values.
	 	 */
	 	$check = GB_Hooks::apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );
	 	if ( null !== $check ) {
	 		if ( $single && is_array( $check ) )
	 			return $check[0];
	 		else
	 			return $check;
	 	}
	
	 	$meta_cache = gb_cache_get($object_id, $meta_type . '_meta');
	
	 	if ( !$meta_cache ) {
	 		$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
	 		$meta_cache = $meta_cache[$object_id];
	 	}
	
	 	if ( ! $meta_key ) {
	 		return $meta_cache;
	 	}
	
	 	if ( isset($meta_cache[$meta_key]) ) {
	 		if ( $single )
	 			return maybe_unserialize( $meta_cache[$meta_key][0] );
	 		else
	 			return array_map('maybe_unserialize', $meta_cache[$meta_key]);
	 	}
	
	 	if ($single)
	 		return '';
	 	else
	 		return array();
	}
	
	/**
	 * Update metadata for the specified object. If no value already exists for the specified object
	 * ID and metadata key, the metadata will be added.
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $object_id ID of the object metadata is for
	 * @param string $meta_key Metadata key
	 * @param mixed $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param mixed $prev_value Optional. If specified, only update existing metadata entries with
	 * 		the specified value. Otherwise, update all entries.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	static function update($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '') {
		if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
			return false;
		}
	
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$column = sanitize_key($meta_type . '_id');
	
		$meta_key = gb_unslash($meta_key);
		$passed_value = $meta_value;
		$meta_value = gb_unslash($meta_value);
		$meta_value = self::_sanitize( $meta_type, $meta_key, $meta_value );
	
		/**
		 * Filter whether to update metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user). Returning a non-null value
		 * will effectively short-circuit the function.
		 *
		 * @since 2.3.0
		 *
		 * @param null|bool $check      Whether to allow updating metadata for the given type.
		 * @param int       $object_id  Object ID.
		 * @param string    $meta_key   Meta key.
		 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
		 * @param mixed     $prev_value Optional. If specified, only update existing
		 *                              metadata entries with the specified value.
		 *                              Otherwise, update all entries.
		 */
		$check = GB_Hooks::apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value );
		if ( null !== $check )
			return (bool) $check;
	
		// Compare existing value to new value if no prev value given and the key exists only once.
		if ( empty($prev_value) ) {
			$old_value = self::get($meta_type, $object_id, $meta_key);
			if ( count($old_value) == 1 ) {
				if ( $old_value[0] === $meta_value )
					return false;
			}
		}

		$meta_ids = gbdb()->get_column("SELECT `mID` FROM $table WHERE meta_key = ?meta_key AND ?#column = ?object_id",
				compact( 'meta_key', 'column', 'object_id' ) );
		if ( empty( $meta_ids ) ) {
			return self::add($meta_type, $object_id, $meta_key, $passed_value);
		}
	
		$_meta_value = $meta_value;
		$meta_value = maybe_serialize( $meta_value );
	
		$data  = compact( 'meta_value' );
		$where = array( $column => $object_id, 'meta_key' => $meta_key );
	
		if ( !empty( $prev_value ) ) {
			$prev_value = maybe_serialize($prev_value);
			$where['meta_value'] = $prev_value;
		}
	
		foreach ( $meta_ids as $meta_id ) {
			/**
			 * Fires immediately before updating metadata of a specific type.
			 *
			 * The dynamic portion of the hook, `$meta_type`, refers to the meta
			 * object type (comment, post, or user).
			 *
			 * @since 2.3.0
			 *
			 * @param int    $meta_id    ID of the metadata entry to update.
			 * @param int    $object_id  Object ID.
			 * @param string $meta_key   Meta key.
			 * @param mixed  $meta_value Meta value.
			 */
			GB_Hooks::do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );
		}

		$result = gbdb()->set_row( $table, $data, $where );
		if ( ! $result )
			return false;
	
		gb_cache_delete($object_id, $meta_type . '_meta');
	
		foreach ( $meta_ids as $meta_id ) {
			/**
			 * Fires immediately after updating metadata of a specific type.
			 *
			 * The dynamic portion of the hook, `$meta_type`, refers to the meta
			 * object type (comment, post, or user).
			 *
			 * @since 2.3.0
			 *
			 * @param int    $meta_id    ID of updated metadata entry.
			 * @param int    $object_id  Object ID.
			 * @param string $meta_key   Meta key.
			 * @param mixed  $meta_value Meta value.
			 */
			GB_Hooks::do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );
		}
	
		return true;
	}

	/**
	 * Delete metadata for the specified object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $object_id ID of the object metadata is for
	 * @param string $meta_key Metadata key
	 * @param mixed $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified,
	 * 		only delete metadata entries with this value. Otherwise, delete all entries with the specified
	 * 		meta_key.
	 * @param bool $delete_all Optional, default is false. If true, delete matching metadata entries
	 * 		for all objects, ignoring the specified object_id. Otherwise, only delete matching
	 * 		metadata entries for the specified object_id.
	 * @return bool True on successful delete, false on failure.
	 */
	static function delete($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
		if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) && ! $delete_all ) {
			return false;
		}
	
		$object_id = absint( $object_id );
		if ( ! $object_id && ! $delete_all ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$type_column = sanitize_key($meta_type . '_id');
		$meta_key = gb_unslash($meta_key);
		$meta_value = gb_unslash($meta_value);
	
		/**
		 * Filter whether to delete metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user). Returning a non-null value
		 * will effectively short-circuit the function.
		 *
		 * @since 2.3.0
		 *
		 * @param null|bool $delete     Whether to allow metadata deletion of the given type.
		 * @param int       $object_id  Object ID.
		 * @param string    $meta_key   Meta key.
		 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
		 * @param bool      $delete_all Whether to delete the matching metadata entries
		 *                              for all objects, ignoring the specified $object_id.
		 *                              Default false.
		 */
		$check = GB_Hooks::apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all );
		if ( null !== $check )
			return (bool) $check;
	
		$_meta_value = $meta_value;
		$meta_value = maybe_serialize( $meta_value );
	
		$query = "SELECT mID FROM $table WHERE meta_key = ?meta_key";
		if ( !$delete_all )
			$query .= " AND ?#type_column = ?object_id";
		if ( $meta_value )
			$query .= " AND meta_value = ?meta_value";
		
		$query = gbdb()->prepare_query($query, compact( 'type_column', 'object_id', 'meta_key', 'meta_value' ));

		$meta_ids = gbdb()->get_col( $query );
		if ( !count( $meta_ids ) )
			return false;
	
		if ( $delete_all )
			$object_ids = gbdb()->get_column( "SELECT ?#type_column FROM $table WHERE meta_key = ?meta_key", 
					compact( 'type_column', 'meta_key' ) );
	
		/**
		 * Fires immediately before deleting metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.3.0
		 *
		 * @param array  $meta_ids   An array of metadata entry IDs to delete.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		GB_Hooks::do_action( "delete_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );
	
		$count = gbdb()->query("DELETE FROM $table WHERE mID IN( ?meta_ids )", compact( 'meta_ids' ));
	
		if ( !$count )
			return false;
	
		if ( $delete_all ) {
			foreach ( (array) $object_ids as $o_id ) {
				gb_cache_delete($o_id, $meta_type . '_meta');
			}
		} else {
			gb_cache_delete($object_id, $meta_type . '_meta');
		}
	
		/**
		 * Fires immediately after deleting metadata of a specific type.
		 *
		 * The dynamic portion of the hook name, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.3.0
		 *
		 * @param array  $meta_ids   An array of deleted metadata entry IDs.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		GB_Hooks::do_action( "deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );
	
		return true;
	}

	/**
	 * Determine if a meta key is set for a given object
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $object_id ID of the object metadata is for
	 * @param string $meta_key Metadata key.
	 * @return boolean true of the key is set, false if not.
	 */
	static function exists( $meta_type, $object_id, $meta_key ) {
		if ( ! $meta_type || ! is_numeric( $object_id ) ) {
			return false;
		}
	
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return false;
		}
	
		/** This filter is documented in gb/class.gb-meta.php */
		$check = GB_Hooks::apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, true );
		if ( null !== $check )
			return (bool) $check;
	
		$meta_cache = gb_cache_get( $object_id, $meta_type . '_meta' );
	
		if ( !$meta_cache ) {
			$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
			$meta_cache = $meta_cache[$object_id];
		}
	
		if ( isset( $meta_cache[ $meta_key ] ) )
			return true;
	
		return false;
	}
	
	/**
	 * Get meta data by meta ID
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $meta_id ID for a specific meta row
	 * @return object Meta object or false.
	 */
	static function get_by_mid( $meta_type, $meta_id ) {
		if ( ! $meta_type || ! is_numeric( $meta_id ) ) {
			return false;
		}
	
		$meta_id = absint( $meta_id );
		if ( ! $meta_id ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$meta = gbdb()->get_row( "SELECT * FROM $table WHERE mID = ?meta_id", compact( 'meta_id' ) );
	
		if ( empty( $meta ) )
			return false;
	
		if ( isset( $meta->meta_value ) )
			$meta->meta_value = maybe_unserialize( $meta->meta_value );
	
		return $meta;
	}
	
	/**
	 * Update meta data by meta ID
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $meta_id ID for a specific meta row
	 * @param string $meta_value Metadata value
	 * @param string $meta_key Optional, you can provide a meta key to update it
	 * @return bool True on successful update, false on failure.
	 */
	static function update_by_mid( $meta_type, $meta_id, $meta_value, $meta_key = false ) {
		// Make sure everything is valid.
		if ( ! $meta_type || ! is_numeric( $meta_id ) ) {
			return false;
		}
	
		$meta_id = absint( $meta_id );
		if ( ! $meta_id ) {
			return false;
		}
	
		$table = _get_meta_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$column = sanitize_key($meta_type . '_id');
	
		// Fetch the meta and go on if it's found.
		if ( $meta = self::get_by_mid( $meta_type, $meta_id ) ) {
			$original_key = $meta->meta_key;
			$object_id = $meta->{$column};
	
			// If a new meta_key (last parameter) was specified, change the meta key,
			// otherwise use the original key in the update statement.
			if ( false === $meta_key ) {
				$meta_key = $original_key;
			} elseif ( ! is_string( $meta_key ) ) {
				return false;
			}
	
			// Sanitize the meta
			$_meta_value = $meta_value;
			$meta_value = self::_sanitize( $meta_type, $meta_key, $meta_value );
			$meta_value = maybe_serialize( $meta_value );
	
			// Format the data query arguments.
			$data = compact( 'meta_key', 'meta_value' );
	
			// Format the where query arguments.
			$where = array();
			$where['mID'] = $meta_id;
	
			/** This action is documented in gb/class.gb-meta.php */
			GB_Hooks::do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );
	
			// Run the update query, all fields in $data are %s, $where is a %d.
			$result = gbdb()->set_row( $table, $data, $where );
			if ( ! $result )
				return false;
	
			// Clear the caches.
			gb_cache_delete($object_id, $meta_type . '_meta');
	
			/** This action is documented in gb/class.gb-meta.php */
			GB_Hooks::do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );
	
			return true;
		}
	
		// And if the meta was not found.
		return false;
	}
	
	/**
	 * Delete meta data by meta ID
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int $meta_id ID for a specific meta row
	 * @return bool True on successful delete, false on failure.
	 */
	static function delete_by_mid( $meta_type, $meta_id ) {
		// Make sure everything is valid.
		if ( ! $meta_type || ! is_numeric( $meta_id ) ) {
			return false;
		}
	
		$meta_id = absint( $meta_id );
		if ( ! $meta_id ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		// object and id columns
		$column = sanitize_key($meta_type . '_id');
	
		// Fetch the meta and go on if it's found.
		if ( $meta = self::get_by_mid( $meta_type, $meta_id ) ) {
			$object_id = $meta->{$column};
	
			/** This action is documented in gb/class.gb-meta.php */
			GB_Hooks::do_action( "delete_{$meta_type}_meta", (array) $meta_id, $object_id, $meta->meta_key, $meta->meta_value );
	
			// Run the query, will return true if deleted, false otherwise
			$result = (bool) gbdb()->delete( $table, array( 'mID' => $meta_id ) );
	
			// Clear the caches.
			gb_cache_delete($object_id, $meta_type . '_meta');
	
			/** This action is documented in gb/class.gb-meta.php */
			GB_Hooks::do_action( "deleted_{$meta_type}_meta", (array) $meta_id, $object_id, $meta->meta_key, $meta->meta_value );
	
			return $result;
	
		}
	
		// Meta id was not found.
		return false;
	}
	
	/**
	 * Update the metadata cache for the specified objects.
	 *
	 * @since 2.3.0
	 *
	 * @global wpdb $wpdb GeniBase database abstraction object.
	 *
	 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
	 * @param int|array $object_ids array or comma delimited list of object IDs to update cache for
	 * @return mixed Metadata cache for the specified objects, or false on failure.
	 */
	static function update_cache($meta_type, $object_ids) {
		if ( ! $meta_type || ! $object_ids ) {
			return false;
		}
	
		$table = self::_get_table( $meta_type );
		if ( ! $table ) {
			return false;
		}
	
		$column = sanitize_key($meta_type . '_id');
	
		if ( !is_array($object_ids) ) {
			$object_ids = preg_replace('|[^0-9,]|', '', $object_ids);
			$object_ids = explode(',', $object_ids);
		}
	
		$object_ids = array_map('intval', $object_ids);
	
		$cache_key = $meta_type . '_meta';
		$ids = array();
		$cache = array();
		foreach ( $object_ids as $id ) {
			$cached_object = gb_cache_get( $id, $cache_key );
			if ( false === $cached_object )
				$ids[] = $id;
			else
				$cache[$id] = $cached_object;
		}
	
		if ( empty( $ids ) )
			return $cache;
	
		// Get meta info
		$id_list = join( ',', $ids );
		$meta_list = gbdb()->get_table( "SELECT ?#column, meta_key, meta_value FROM $table WHERE ?#column IN (?id_list) ORDER BY mID ASC", 
				compact( 'column', 'id_list' ) );
	
		if ( !empty($meta_list) ) {
			foreach ( $meta_list as $metarow) {
				$mpid = intval($metarow[$column]);
				$mkey = $metarow['meta_key'];
				$mval = $metarow['meta_value'];
	
				// Force subkeys to be array type:
				if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
					$cache[$mpid] = array();
				if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
					$cache[$mpid][$mkey] = array();
	
				// Add a value to the current pid/key:
				$cache[$mpid][$mkey][] = $mval;
			}
		}
	
		foreach ( $ids as $id ) {
			if ( ! isset($cache[$id]) )
				$cache[$id] = array();
			gb_cache_add( $id, $cache[$id], $cache_key );
		}
	
		return $cache;
	}
	
	/**
	 * Determine whether a meta key is protected.
	 *
	 * @since 2.3.0
	 *
	 * @param string      $meta_key Meta key
	 * @param string|null $meta_type
	 * @return bool True if the key is protected, false otherwise.
	 */
	static function is_protected( $meta_key, $meta_type = null ) {
		$protected = ( '_' == $meta_key[0] );
	
		/**
		 * Filter whether a meta key is protected.
		 *
		 * @since 2.3.0
		 *
		 * @param bool   $protected Whether the key is protected. Default false.
		 * @param string $meta_key  Meta key.
		 * @param string $meta_type Meta type.
		 */
		return GB_Hooks::apply_filters( 'is_protected_meta', $protected, $meta_key, $meta_type );
	}
	
	/**
	 * Register meta key
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of meta
	 * @param string $meta_key Meta key
	 * @param string|array $sanitize_callback A function or method to call when sanitizing the value of $meta_key.
	 * @param string|array $auth_callback Optional. A function or method to call when performing edit_*_meta, add_*_meta, and delete_*_meta capability checks.
	 */
	static function register( $meta_type, $meta_key, $sanitize_callback, $auth_callback = null ) {
		if ( is_callable( $sanitize_callback ) )
			GB_Hooks::add_filter( "sanitize_{$meta_type}_meta_{$meta_key}", $sanitize_callback, 10, 3 );
	
		if ( empty( $auth_callback ) ) {
			if ( self::is_protected( $meta_key, $meta_type ) )
				$auth_callback = '__return_false';
			else
				$auth_callback = '__return_true';
		}
	
		if ( is_callable( $auth_callback ) )
			GB_Hooks::add_filter( "auth_{$meta_type}_meta_{$meta_key}", $auth_callback, 10, 6 );
	}
}

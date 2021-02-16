<?php declare( strict_types=1 );

namespace Tribe\ACF_Post_List;

use RuntimeException;

/**
 * Cache ACF fields data so we can always have access to their state.
 *
 * @package Tribe\ACF_Post_List
 */
class Cache {

	/**
	 * Prefix transients with a group.
	 *
	 * @var string
	 */
	protected $group;

	/**
	 * How long to store the item in the cache.
	 *
	 * @var int
	 */
	protected $ttl;

	/**
	 * Cache constructor.
	 *
	 * @param  string  $group  The object cache group to store in.
	 * @param  int     $ttl    How long the cache should live for.
	 */
	public function __construct( string $group = 'tribe_acf', int $ttl = 86400 ) {
		$this->group = $group;
		$this->ttl   = $ttl;
	}

	/**
	 * Get an item from the cache.
	 *
	 * @param  string  $key      The un prefixed cache key to get.
	 * @param  null    $default  The value to return if the data doesn't exist.
	 *
	 * @return mixed|null
	 */
	public function get( string $key, $default = null ) {
		return get_transient( $this->get_key( $key ) ) ?: $default;
	}

	/**
	 * Put an item in the cache.
	 *
	 * @param  string    $key
	 * @param  mixed     $value
	 * @param  int|null  $ttl
	 *
	 * @return bool
	 */
	public function set( string $key, $value, ?int $ttl = null ): bool {
		if ( ! is_int( $ttl )  ) {
			$ttl = $this->ttl;
		}

		if ( empty( $value ) ) {
			return false;
		}

		return (bool) set_transient( $this->get_key( $key ), $value, $ttl );
	}

	/**
	 * Delete an item from the cache.
	 *
	 * @param  string  $key
	 *
	 * @return bool
	 */
	public function delete( string $key ): bool {
		return (bool) delete_transient( $this->get_key( $key ) );
	}

	/**
	 * Clear all items from this group or flush the entire object cache.
	 *
	 * @return bool
	 */
	public function clear(): bool {
		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_flush();
		}

		global $wpdb;

		$like = '_transient_' . $this->group . '_%';
		$sql  = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%s'", $wpdb->esc_like( $like ) );

		return (bool) $wpdb->query( $sql );
	}

	/**
	 * Check if the cache has an item.
	 *
	 * @param  string  $key
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return ! empty( $this->get( $this->get_key( $key ) ) );
	}

	/**
	 * Get a key with our group prefix.
	 *
	 * @param  string  $key
	 *
	 * @return string
	 */
	protected function get_key( string $key ): string {
		$prefix_key = $this->group . '_' . $key;

		if ( strlen( $prefix_key ) > 172 ) {
			throw new RuntimeException( 'The total key length with prefix should be 172 characters or fewer.' );
		}

		return $prefix_key;
	}

}

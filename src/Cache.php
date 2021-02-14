<?php declare( strict_types=1 );

namespace Tribe\ACF_Post_List;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache ACF fields to keep their field state.
 *
 * @package Tribe\ACF_Post_List
 */
class Cache implements CacheInterface {

	protected $group;
	protected $expire;

	/**
	 * Cache constructor.
	 *
	 * @param  string  $group   The object cache group to store in.
	 * @param  int     $expire  How long the cache should live for.
	 */
	public function __construct( string $group = 'tribe_acf', int $expire = 86400 ) {
		$this->group  = $group;
		$this->expire = $expire;
	}

	public function get( $key, $default = null ) {
		return wp_cache_get( $key, $this->group );
	}

	public function set( $key, $value, $ttl = null ) {
		return wp_cache_set( $key, $value, $this->group, $this->expire );
	}

	public function delete( $key ) {
		return wp_cache_delete( $key, $this->group );
	}

	public function clear() {
		return wp_cache_flush();
	}

	public function getMultiple( $keys, $default = null ) {
		return wp_cache_get_multiple( $keys, $this->group );
	}

	public function setMultiple( $values, $ttl = null ) {
		// TODO: Implement setMultiple() method.
	}

	public function deleteMultiple( $keys ) {
		// TODO: Implement deleteMultiple() method.
	}

	public function has( $key ) {
		return ! is_null( $this->get( $key ) );
	}

}

<?php declare( strict_types=1 );

namespace Tribe\ACF_Post_List;

use ACF_Data;

/**
 * Access ACF's in memory store
 *
 * @package Tribe\ACF_Post_List
 */
class Config {

	public const STORE_TYPE = 'fields';

	/**
	 * Returns the data for a parent field.
	 *
	 * @param  string  $acf_field_name
	 *
	 * @return array|mixed|null
	 */
	public function get_parent_field( string $acf_field_name ): ?array {
		$store  = $this->populate_store();
		$fields = $store->get_data();
		$keys   = wp_list_pluck( $fields, 'key', 'key' );
		$key    = array_search( $acf_field_name, $keys );

		if ( ! $key ) {
			return null;
		}

		return $store->get( $key );
	}

	/**
	 * Populates our store with ACF's local cached data.
	 *
	 * @note This does not work with fields registered though the ACF UI.
	 *
	 * @return \ACF_Data
	 */
	private function populate_store(): ACF_Data {
		return acf_get_local_store( self::STORE_TYPE );
	}

}

<?php declare( strict_types=1 );

/*
Plugin Name: Advanced Custom Fields: Tribe Post List Field
Plugin URI: https://tri.be
Description: A post list field type for advanced custom fields
Version: 2.2.1
Author: Modern Tribe
Author URI: https://tri.be
*/

use Tribe\ACF_Post_List\Cache;
use Tribe\ACF_Post_List\Config;
use Tribe\ACF_Post_List\Post_List_Field;
use Tribe\ACF_Post_List\Multiple_Taxonomy_Field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Support SquareOne autoloaders.
$autoloaders = [
	defined( 'ABSPATH' ) ? ABSPATH . '../vendor/autoload.php' : '',
	defined( 'ABSPATH' ) ? ABSPATH . 'vendor/autoload.php' : '',
	trailingslashit( __DIR__ ) . 'vendor/autoload.php',
];

$autoload = current( array_filter( $autoloaders, 'is_file' ) );

require_once $autoload;

function tribe_acf_post_list(): void {

	$settings = [
		'version' => '2.2.1',
		'url'     => plugin_dir_url( __FILE__ ),
		'path'    => plugin_dir_path( __FILE__ ),
	];

	$cache               = new Cache();
	$config              = new Config();
	$multiple_taxonomies = new Multiple_Taxonomy_Field( $cache );
	$post_list           = new Post_List_Field( $cache, $settings );

	acf_register_field_type( $multiple_taxonomies );
	acf_register_field_type( $post_list );

	// Cache any post list fields as ajax requests do not contain any relational data to use.
	add_filter( 'acf/load_fields', static function ( $fields, $parent ) use ( $cache ) {
		$types = wp_list_pluck( $fields, 'type' );
		$key   = array_search( Post_List_Field::NAME, $types, true );

		if ( is_int( $key ) && ! $cache->has( $fields[ $key ]['key'] ) ) {
			$cache->set( $fields[ $key ]['key'], $fields[ $key ] );
		}

		return $fields;
	}, 10, 2 );

	// Filter the manual post picker for the post types passed to the field type.
	add_filter(
		'acf/fields/post_object/query/name=' . Post_List_Field::FIELD_MANUAL_POST,
		static function ( $args, $field, $post_id ) use ( $config, $cache ) {
			$group = filter_input( INPUT_POST, 'group', FILTER_SANITIZE_STRING );

			// Check ACF's store for this field, otherwise grab from our cache.
			$parent_field = $config->get_parent_field( (string) $group ) ?? $cache->get( (string) $group );

			if ( $parent_field ) {
				$args['post_type'] = acf_get_array( $parent_field[ Post_List_Field::SETTINGS_FIELD_POST_TYPES_ALLOWED_MANUAL ] );
			}

			return $args;
		},
		10,
		3
	);
}

add_action( 'acf/init', static function (): void {
	tribe_acf_post_list();
}, 1 );

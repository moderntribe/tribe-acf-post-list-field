<?php declare( strict_types=1 );

/*
Plugin Name: Advanced Custom Fields: Tribe Post List Field
Plugin URI: https://tri.be
Description: A post list field type for advanced custom fields
Version: 1.1.0
Author: Modern Tribe
Author URI: https://tri.be
*/

use Tribe\ACF_Post_List\Cache;
use Tribe\ACF_Post_List\Config;
use Tribe\ACF_Post_List\Post_List_Field;

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
		'version' => '1.1.0',
		'url'     => plugin_dir_url( __FILE__ ),
		'path'    => plugin_dir_path( __FILE__ ),
	];

	$cache    = new Cache();
	$config   = new Config();
	$instance = new Post_List_Field( $cache, $settings );

	// Cache any post list fields as ajax requests do not contain any relational data to use.
	add_filter( 'acf/load_fields', static function ( $fields, $parent ) use ( $cache ) {
		$types = wp_list_pluck( $fields, 'type', 'key' );
		$key   = array_search( Post_List_Field::NAME, $types, true );

		if ( $key ) {
			$cache->set( $key, $fields[ $key ] );
		}

		return $fields;
	}, 10, 2 );

	add_action( 'wp_ajax_load_taxonomy_choices', [ $instance, 'get_taxonomies_options_ajax' ] );

	// Filter the manual post picker for the post types passed to the field type.
	add_filter(
		'acf/fields/post_object/query/name=' . Post_List_Field::MANUAL_POST,
		static function ( $args, $field, $post_id ) use ( $config ) {
			$parent_field = $config->get_parent_field( Post_List_Field::NAME );

			if ( $parent_field ) {
				$args['post_type'] = $parent_field[ Post_List_Field::POST_TYPES_ALLOWED_MANUAL ];
			}

			return $args;
		},
		10,
		3
	);
}

add_action( 'plugins_loaded', static function (): void {
	tribe_acf_post_list();
}, 10 );

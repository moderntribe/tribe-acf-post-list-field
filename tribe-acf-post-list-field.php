<?php
/*
Plugin Name: Advanced Custom Fields: Tribe Post List Field
Plugin URI: https://tri.be
Description: A post list field type for advanced custom fields
Version: 1.0.2
Author: Modern Tribe
Author URI: https://tri.be
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'vendor/autoload.php';

function tribe_acf_post_list(): void {
	$settings = [
		'version' => '1.0.0',
		'url'     => plugin_dir_url( __FILE__ ),
		'path'    => plugin_dir_path( __FILE__ ),
	];

	$instance = new \Tribe\ACF_Post_List\ACF_Post_List_Field_v5( $settings );

	add_action( 'wp_ajax_load_taxonomy_choices', [ $instance, 'get_taxonomies_options_ajax' ] );
}

add_action( 'plugins_loaded', static function (): void {
	tribe_acf_post_list();
} );


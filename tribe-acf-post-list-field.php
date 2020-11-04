<?php
/*
Plugin Name: Advanced Custom Fields: Tribe Post List Field
Plugin URI: https://tri.be
Description: A post list field type for advanced custom fields
Version: 1.0.0
Author: Modern Tribe
Author URI: https://tri.be
*/

require_once 'vendor/autoload.php';


class Tribe_ACF_Post_List {

	function __construct() {
		$settings = [
			'version' => '1.0.0',
			'url'     => plugin_dir_url( __FILE__ ),
			'path'    => plugin_dir_path( __FILE__ ),
		];

		add_action( 'acf/include_field_types', function () use ( $settings ) {
			new \Tribe\ACF_Post_List\ACF_Post_List_Field_v5( $settings );
		} );
	}

}

add_action( 'plugins_loaded', function () {
	new Tribe_ACF_Post_List();
} );


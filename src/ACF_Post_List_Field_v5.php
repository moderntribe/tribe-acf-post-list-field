<?php

namespace Tribe\ACF_Post_List;

class ACF_Post_List_Field_v5 extends \acf_field {

	//Admin options
	public const AVAILABLE_TYPES        = 'available_types';
	public const AVAILABLE_TYPES_BOTH   = 'both';
	public const AVAILABLE_TYPES_MANUAL = 'manual';
	public const AVAILABLE_TYPES_QUERY  = 'query';

	//Common Options
	public const LIMIT_MIN          = 'limit_min';
	public const LIMIT_MAX          = 'limit_max';
	public const POST_TYPES_ALLOWED = 'post_types';

	//Manual Options
	public const ALLOW_OVERRIDE = 'allow_override';

	//Auto Options
	public const TAXONOMIES_ALLOWED = 'taxonomies';

	//rendered Options
	public const QUERY_TYPE        = 'query_type';
	public const QUERY_TYPE_AUTO   = 'query_type_auto';
	public const QUERY_TYPE_MANUAL = 'query_type_manual';

	// Manual query fields
	public const MANUAL_QUERY     = 'manual_query';
	public const MANUAL_POST      = 'manual_post';
	public const MANUAL_TOGGLE    = 'manual_toggle';
	public const MANUAL_TITLE     = 'manual_title';
	public const MANUAL_EXCERPT   = 'manual_excerpt';
	public const MANUAL_CTA       = 'manual_cta';
	public const MANUAL_THUMBNAIL = 'manual_thumbnail';

	//Query Fields
	public const QUERY_GROUP      = 'query_group';
	public const QUERY_LIMIT      = 'query_limit';
	public const QUERY_TAXONOMIES = 'query_taxonomy_terms';
	public const QUERY_POST_TYPES = 'query_post_types';

	protected $value_defaults = [
		self::QUERY_TYPE => self::QUERY_TYPE_AUTO,
	];

	/*
	*  __construct
	*
	*  This function will setup the field type data
	*
	*  @type	function
	*  @date	5/03/2014
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/

	function __construct( $settings ) {

		$this->name       = 'acf_tribe_post_list';
		$this->label      = __( 'Post List', 'tribe' );
		$this->category   = 'relational';
		$this->defaults   = [
			self::AVAILABLE_TYPES    => self::AVAILABLE_TYPES_BOTH,
			self::LIMIT_MAX          => 10,
			self::LIMIT_MIN          => 0,
			self::POST_TYPES_ALLOWED => [],
			self::TAXONOMIES_ALLOWED => [],
			self::ALLOW_OVERRIDE     => true,
		];
		$this->sub_fields = [];
		$this->l10n       = [
			'error' => __( 'Error!', 'tribe' ),
		];
		$this->settings   = $settings;

		// do not delete!
		parent::__construct();
	}


	/*
	*  render_field_settings()
	*
	*  Create extra settings for your field. These are visible when editing a field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field (array) the $field being edited
	*  @return	n/a
	*/

	function render_field_settings( $field ) {

		acf_render_field_setting( $field, [
			'label'        => __( 'Available Types', 'tribe' ),
			'instructions' => __( 'Allows you to set manual creation, auto query, or both.', 'tribe' ),
			'type'         => 'button_group',
			'name'         => self::AVAILABLE_TYPES,
			'choices'      => [
				self::AVAILABLE_TYPES_BOTH   => __( 'Both', 'tribe' ),
				self::AVAILABLE_TYPES_MANUAL => __( 'Manual Only', 'tribe' ),
				self::AVAILABLE_TYPES_QUERY  => __( 'Automatic Query Only', 'tribe' ),
			],
		] );

		acf_render_field_setting( $field, [
			'label'    => __( 'Post Types', 'tribe' ),
			'type'     => 'select',
			'multiple' => true,
			'ui'       => true,
			'name'     => self::POST_TYPES_ALLOWED,
			'choices'  => $this->get_public_post_types(),
		] );

		acf_render_field_setting( $field, [
			'label'             => __( 'Taxonomies', 'tribe' ),
			'type'              => 'select',
			'multiple'          => true,
			'ui'                => true,
			'name'              => self::TAXONOMIES_ALLOWED,
			'choices'           => $this->get_taxonomies(),
			'conditional_logic' => [
				[
					[
						'field'    => self::AVAILABLE_TYPES,
						'operator' => '==',
						'value'    => self::AVAILABLE_TYPES_BOTH,
					],
				],
				[
					[
						'field'    => self::AVAILABLE_TYPES,
						'operator' => '==',
						'value'    => self::AVAILABLE_TYPES_QUERY,
					],
				],
			],
		] );

		acf_render_field_setting( $field, [
			'label'         => __( 'Minimum Items', 'tribe' ),
			'type'          => 'number',
			'default_value' => 0,
			'min'           => 0,
			'step'          => 1,
			'name'          => self::LIMIT_MIN,
		] );

		acf_render_field_setting( $field, [
			'label'         => __( 'Maximum Items', 'tribe' ),
			'type'          => 'number',
			'default_value' => 10,
			'min'           => 1,
			'step'          => 1,
			'name'          => self::LIMIT_MAX,
		] );

		acf_render_field_setting( $field, [
			'label'             => __( 'Allow Override &amp; Manual Creation', 'tribe' ),
			'type'              => 'true_false',
			'ui'                => true,
			'name'              => self::ALLOW_OVERRIDE,
			'conditional_logic' => [
				[
					[
						'field'    => self::AVAILABLE_TYPES,
						'operator' => '==',
						'value'    => self::AVAILABLE_TYPES_BOTH,
					],
				],
				[
					[
						'field'    => self::AVAILABLE_TYPES,
						'operator' => '==',
						'value'    => self::AVAILABLE_TYPES_MANUAL,
					],
				],
			],
		] );
	}

	/**
	 * @return array
	 */
	private function get_public_post_types() {
		$post_types        = get_post_types( [
			'public' => true,
		], 'objects' );
		$post_type_options = [];
		foreach ( $post_types as $slug => $pt_object ) {
			$post_type_options[ $slug ] = $pt_object->label;
		}

		/**
		 * Provided options for post types
		 * @para array $options
		 */
		return apply_filters( 'tribe/acf_post_list/post_types', $post_type_options );
	}

	/**
	 * @return array
	 */
	private function get_taxonomies() {
		$taxonomies         = get_object_taxonomies( array_keys( $this->get_public_post_types() ), 'object' );
		$taxonomies_options = [];

		foreach ( $taxonomies as $slug => $tax_object ) {
			$taxonomies_options[ $slug ] = $tax_object->label;
		}

		/**
		 * Provided options for post types
		 * @para array $options
		 */
		return apply_filters( 'tribe/acf_post_list/taxonomies', $taxonomies_options );
	}

	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field (array) the $field being rendered
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field (array) the $field being edited
	*  @return	n/a
	*/

	function render_field( $field ) {
		echo '<pre>';
		print_r( $field );
		echo '</pre>';

	}

}

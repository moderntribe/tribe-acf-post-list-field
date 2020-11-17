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

	/**
	 * The default values for the editor fields.
	 * @var array
	 */
	protected $value_defaults = [
		self::QUERY_TYPE  => self::QUERY_TYPE_AUTO,
		self::QUERY_LIMIT => 2,
	];

	/**
	 * @var array
	 */
	protected $settings = [];

	/**
	 * @var array
	 */
	protected $post_types_allowed = [];

	/**
	 * @var array
	 */
	protected $taxonomies_allowed = [];

	public function __construct( $settings ) {
		add_action( 'wp_ajax_load_taxonomy_choices', [ $this, 'get_taxonomies_options_ajax' ] );
		$this->name     = 'tribe_post_list';
		$this->label    = __( 'Post List', 'tribe' );
		$this->category = 'relational';
		$this->defaults = [
			self::AVAILABLE_TYPES    => self::AVAILABLE_TYPES_BOTH,
			self::LIMIT_MAX          => 10,
			self::LIMIT_MIN          => 0,
			self::POST_TYPES_ALLOWED => [],
			self::TAXONOMIES_ALLOWED => [],
			self::ALLOW_OVERRIDE     => true,
		];
		$this->settings = $settings;
		$this->add_field_groups();
		parent::__construct();
	}

	/*
	*  Create extra settings for your field. These are visible when editing a field
	*
	*  @action acf/render_field_settings
	*
	*  @param	$field (array) the $field being edited
	*/
	public function render_field_settings( $field ) {

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
			'default_value' => 12,
			'min'           => 1,
			'step'          => 1,
			'name'          => self::LIMIT_MAX,
		] );

	}

	/*
	*  Create the HTML interface for your field
	*  @param	$field (array) the $field being rendered
	*
	*  @action acf/render_field
	*
	*  @param	$field (array) the $field being edited
	*/
	public function render_field( $field ) {
		$this->post_types_allowed = array_filter( $this->get_public_post_types(), function ( $cpt_key ) use ( $field ) {
			return in_array( $cpt_key, (array) $field[ self::POST_TYPES_ALLOWED ] );
		}, ARRAY_FILTER_USE_KEY );

		$this->taxonomies_allowed = array_filter( $this->get_taxonomies(), function ( $cpt_key ) use ( $field ) {
			return in_array( $cpt_key, (array) $field[ self::TAXONOMIES_ALLOWED ] );
		}, ARRAY_FILTER_USE_KEY );

		//This field is needed for acf to see saving this field as a valid request. We're mostly populating this field
		// with javascript when a form field is changed.
		?>
		<input type="hidden"
			   name="<?php echo esc_attr( $field[ 'name' ] ) ?>"
			   data-allowed_taxonomies="<?php echo esc_attr( wp_json_encode( $field[ self::TAXONOMIES_ALLOWED ] ) ); ?>"
			   value=" <?php echo esc_attr( wp_json_encode( $field[ 'value' ] ) ) ?>"
			   class="js-post-list-data"
		/>
		<?
		//QUERY Type option
		if ( $field[ self::AVAILABLE_TYPES ] === self::AVAILABLE_TYPES_BOTH ) {
			acf_render_field_wrap(
				$this->get_query_types_config( $field ),
				'div'
			);
		}

		//MANUAL Fields
		if ( $field[ self::AVAILABLE_TYPES ] === self::AVAILABLE_TYPES_BOTH ||
		     $field[ self::AVAILABLE_TYPES ] === self::AVAILABLE_TYPES_MANUAL ) {
			acf_render_field_wrap(
				$this->get_manual_field_config( $field ),
				'div'
			);
		}
		//AUTO Fields
		if ( $field[ self::AVAILABLE_TYPES ] === self::AVAILABLE_TYPES_QUERY ||
		     $field[ self::AVAILABLE_TYPES ] === self::AVAILABLE_TYPES_BOTH ) {
			foreach ( $this->get_auto_query_fields( $field ) as $config ) {
				acf_render_field_wrap( $config, 'div' );
			}
		}
	}


	/*
	*  This filter is applied to the $value after it is loaded from the db
	*
	*  @filter load_value
	*
	*  @param	$value (mixed) the value found in the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*  @return	$value
	*/
	public function load_value( $value, $post_id, $field ) {
		if ( ! $value ) {
			return $this->value_defaults; // preset the field values with our defaults.
		}

		return json_decode( $value, true );
	}


	/*
	*  This filter is applied to the $value after it is loaded from the db and before it is returned to the template
	*
	*  @filter	 format_value
	*  @param	$value (mixed) the value which was loaded from the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*
	*  @return	$value (mixed) the modified value
	*/
	function format_value( $value, $post_id, $field ) {
		if ( empty( $value ) ) {
			return $value;
		}

		if ( self::QUERY_TYPE_AUTO === $value[ self::QUERY_TYPE ] ) {
			return $this->get_posts_from_query( $value );
		}

		return $this->get_manually_selected_posts( $value );
	}

	/**
	 * @return array
	 */
	private function get_manually_selected_posts( $value ): array {
		$manual_rows = $value[ self::MANUAL_QUERY ] ?? [];

		$post_array = [];
		foreach ( $manual_rows as $row ) {
			$item = [];
			if ( ! $row[ self::MANUAL_POST ] && ! $row[ self::MANUAL_TOGGLE ] ) {
				continue; //no post and no override/custom
			}

			//Get manually selected post
			if ( $row[ self::MANUAL_POST ] ) {
				$manual_post = get_post( $row[ self::MANUAL_POST ] );
				if ( ! $manual_post ) {
					continue;
				}
				$item = $this->format_post( $manual_post );
			}

			//build custom or overwrite selected post above
			if ( $row[ self::MANUAL_TOGGLE ] ) {
				$item = $this->maybe_overwrite_values( $row, $item );
			}

			//Check if we have data for this post to remove any empty rows
			if ( ! $item || ! $this->is_valid_post( $item ) ) {
				continue;
			}
			$post_array[] = $item;
		}

		return $post_array;
	}

	/**
	 * @param      $values
	 * @param null $post_array
	 *
	 * @return array
	 */
	private function maybe_overwrite_values( $values, $post_array = [] ): array {
		if ( ! empty( $values[ self::MANUAL_TITLE ] ) ) {
			$post_array[ 'title' ] = $values[ self::MANUAL_TITLE ];
		}

		if ( ! empty( $values[ self::MANUAL_EXCERPT ] ) ) {
			$post_array[ 'excerpt' ] = $values[ self::MANUAL_EXCERPT ];
		}

		if ( ! empty( $values[ self::MANUAL_THUMBNAIL ] ) ) {
			$post_array[ 'image_id' ] = (int) $values[ self::MANUAL_THUMBNAIL ];
		}

		if ( $values[ self::MANUAL_CTA ] && is_array( $values[ self::MANUAL_CTA ] ) ) {
			$post_array[ 'link' ] = $values[ self::MANUAL_CTA ];
		}

		return $post_array;
	}

	/**
	 * @param array $post_array
	 *
	 * @return bool
	 */
	private function is_valid_post( array $post_array ): bool {
		return ! ( empty( $post_array[ 'title' ] ) &&
		           empty( $post_array[ 'excerpt' ] ) &&
		           ! $post_array[ 'image_id' ] &&
		           empty( $post_array[ 'link' ] ) );
	}

	/**
	 * @return []
	 */
	private function get_posts_from_query( $value ): array {
		$post_types = (array) $value[ ACF_Post_List_Field_v5::QUERY_POST_TYPES ] ?? [];
		$tax_query  = $this->get_tax_query_args( $value );
		$args       = [
			'post_type'      => $post_types,
			'tax_query'      => [
				'relation' => 'AND',
			],
			'post_status'    => 'publish',
			'posts_per_page' => $value[ ACF_Post_List_Field_v5::QUERY_LIMIT ] ?? 0,
		];
		foreach ( $tax_query as $taxonomy => $ids ) {
			$args[ 'tax_query' ][] = [
				'taxonomy' => $taxonomy,
				'field'    => 'id',
				'terms'    => array_map( 'intval', $ids ),
				'operator' => 'IN',
			];
		}
		$args   = apply_filters( 'tribe/acf_post_list/query_args', $args );
		$_posts = get_posts( $args );

		$return = [];
		foreach ( $_posts as $p ) {
			$return[] = $this->format_post( $p );
		}

		return $return;
	}

	/**
	 * Builds an array of taxonomy terms based on the selected taxonomies so to ignore term fields hidden with conditional logic.
	 *
	 * @param array $value
	 *
	 * @return array
	 */
	private function get_tax_query_args( array $value ): array {
		if ( ! $value[ self::QUERY_TAXONOMIES ] ) {
			return [];
		}
		$tax_and_terms = [];
		foreach ( $value[ self::QUERY_TAXONOMIES ] as $taxonomy ) {
			$terms = $value[ ACF_Post_List_Field_v5::QUERY_TAXONOMIES . '_' . $taxonomy ] ?? false;

			if ( ! $terms ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! is_a( $term, 'WP_Term' ) ) {
					continue;
				}
				$tax_and_terms[ $term->taxonomy ][] = $term->term_id;
			}
		}

		return $tax_and_terms;
	}

	/**
	 * @param \WP_Post $_post
	 *
	 * @return array
	 */
	private function format_post( $_post ): array {
		global $post;
		$post = $_post;
		setup_postdata( $post );
		$post_array = [
			'title'     => get_the_title(),
			'content'   => get_the_content(),
			'excerpt'   => get_the_excerpt(),
			'image_id'  => get_post_thumbnail_id(),
			'link'      => [
				'url'    => get_the_permalink(),
				'target' => '',
				'label'  => get_the_title(),
			],
			'post_type' => get_post_type(),
			'post_id'   => $_post->ID,
		];

		wp_reset_postdata();

		return $post_array;
	}


	/*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	*  Use this action to add CSS + JavaScript to assist your render_field() action.
	*
	*  @action admin_enqueue_scripts
	*/
	function input_admin_head() {
		$url     = $this->settings[ 'url' ];
		$version = $this->settings[ 'version' ];

		wp_register_script( 'tribe-acf-post-list', "{$url}assets/js/post-list-field.js", [ 'acf-input' ], $version );
		wp_enqueue_script( 'tribe-acf-post-list' );

		wp_register_style( 'tribe-acf-post-list', "{$url}assets/css/post-list-field.css", [ 'acf-input' ], $version );
		wp_enqueue_style( 'tribe-acf-post-list' );
	}

	/**
	 * @return array
	 */
	private function get_public_post_types(): array {
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
	private function get_taxonomies(): array {
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

	/**
	 * Ajax response to load taxonomy options
	 */
	public function get_taxonomies_options_ajax() {
		// we can use the acf nonce to verify
		if ( ! wp_verify_nonce( $_POST[ 'nonce' ], 'acf_nonce' ) ) {
			die();
		}

		$post_types           = $_POST[ 'post_types' ] ?? $this->get_public_post_types();
		$available_taxonomies = $_POST[ 'available_taxonomies' ] ?? [];
		$taxonomies           = get_object_taxonomies( $post_types, 'object' );
		$taxonomies_options   = [];
		$taxonomies           = array_filter( $taxonomies, function ( $tax_slug ) use ( $available_taxonomies ) {
			return in_array( $tax_slug, (array) $available_taxonomies );
		}, ARRAY_FILTER_USE_KEY );
		foreach ( $taxonomies as $slug => $tax_object ) {
			$taxonomies_options[ $slug ] = $tax_object->label;
		}
		/**
		 * Provided options for post types
		 * @para array $options
		 */
		$taxonomies_options = apply_filters( 'tribe/acf_post_list/taxonomies', $taxonomies_options );

		echo wp_json_encode( $taxonomies_options );
		exit;

	}

	/**
	 * Config values for manual, repeater fields
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	private function get_manual_field_config( $field = [] ): array {
		$config = [
			'min'               => $field[ self::LIMIT_MIN ] ?? $this->defaults[ self::LIMIT_MIN ],
			'max'               => $field[ self::LIMIT_MAX ] ?? $this->defaults[ self::LIMIT_MAX ],
			'layout'            => 'row',
			'name'              => self::MANUAL_QUERY,
			'key'               => self::MANUAL_QUERY,
			'label'             => __( 'Manual Items', 'tribe' ),
			'type'              => 'repeater',
			'value'             => $field[ 'value' ][ self::MANUAL_QUERY ] ?? [],
			'conditional_logic' => [
				[
					[
						'field'    => self::QUERY_TYPE,
						'operator' => '==',
						'value'    => self::QUERY_TYPE_MANUAL,
					],
				],
			],
			'sub_fields'        => [
				[
					'label' => __( 'Start w/ Existing Content', 'tribe' ),
					'type'  => 'message',
					'key'   => '',
				],
				[
					'label'      => __( 'Post Selection', 'tribe' ),
					'name'       => self::MANUAL_POST,
					'key'        => self::MANUAL_POST,
					'type'       => 'post_object',
					'post_type'  => array_keys( $this->get_public_post_types() ),
					'allow_null' => true,
				],
				[
					'label'        => __( 'Create or Override Content', 'tribe' ),
					'instructions' => __( 'Data entered below will overwrite the respective data from the post selected above.',
						'tribe' ),
					'name'         => self::MANUAL_TOGGLE,
					'key'          => self::MANUAL_TOGGLE,
					'type'         => 'true_false',
				],
				[
					'label'             => __( 'Title', 'tribe' ),
					'type'              => 'text',
					'name'              => self::MANUAL_TITLE,
					'key'               => self::MANUAL_TITLE,
					'conditional_logic' => [
						[
							'field'    => self::MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'label'             => __( 'Excerpt', 'tribe' ),
					'type'              => 'textarea',
					'name'              => self::MANUAL_EXCERPT,
					'key'               => self::MANUAL_EXCERPT,
					'conditional_logic' => [
						[
							'field'    => self::MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'name'              => self::MANUAL_CTA,
					'key'               => self::MANUAL_CTA,
					'label'             => __( 'Call to Action', 'tribe' ),
					'type'              => 'link',
					'conditional_logic' => [
						[
							'field'    => self::MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'name'              => self::MANUAL_THUMBNAIL,
					'key'               => self::MANUAL_THUMBNAIL,
					'label'             => __( 'Thumbnail Image', 'tribe' ),
					'type'              => 'image',
					'return_format'     => 'id',
					'conditional_logic' => [
						[
							'field'    => self::MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
			],
		];

		return apply_filters( 'tribe/acf_post_list/manual_fields_config', $config );
	}

	/**
	 * Field config for query type config
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	private function get_query_types_config( $field = [] ): array {
		$config = [
			'label'   => __( 'Type of Query', 'tribe' ),
			'name'    => self::QUERY_TYPE,
			'key'     => self::QUERY_TYPE,
			'type'    => 'button_group',
			'value'   => $field[ 'value' ][ self::QUERY_TYPE ] ?? $this->value_defaults[ self::QUERY_TYPE ],
			'choices' => [
				self::QUERY_TYPE_AUTO   => __( 'Automatic', 'tribe' ),
				self::QUERY_TYPE_MANUAL => __( 'Manual', 'tribe' ),
			],
		];

		return apply_filters( 'tribe/acf_post_list/query_types_config', $config );
	}

	/**
	 * Field config for the auto query values
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	private function get_auto_query_fields( $field = [] ): array {
		$config = [
			[
				'label'             => __( 'Build your Query', 'tribe' ),
				'type'              => 'message',
				'key'               => '',
				'conditional_logic' => [
					[
						[
							'field'    => self::QUERY_TYPE,
							'operator' => '==',
							'value'    => self::QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'type'              => 'select',
				'label'             => __( 'Post Types', 'tribe' ),
				'multiple'          => true,
				'ui'                => true,
				'name'              => self::QUERY_POST_TYPES,
				'key'               => self::QUERY_POST_TYPES,
				'choices'           => $this->post_types_allowed,
				'value'             => $field[ 'value' ][ self::QUERY_POST_TYPES ] ?? [],
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::QUERY_TYPE,
							'operator' => '==',
							'value'    => self::QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'label'             => __( 'Limit', 'tribe' ),
				'name'              => self::QUERY_LIMIT,
				'key'               => self::QUERY_LIMIT,
				'value'             => $field[ 'value' ][ self::QUERY_LIMIT ] ?? ( $field[ self::LIMIT_MIN ] ?? $this->defaults[ self::LIMIT_MIN ] ),
				'min'               => $field[ self::LIMIT_MIN ] ?? $this->defaults[ self::LIMIT_MIN ],
				'max'               => $field[ self::LIMIT_MAX ] ?? $this->defaults[ self::LIMIT_MAX ],
				'step'              => 1,
				'type'              => 'range',
				'default_value'     => 2,
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::QUERY_TYPE,
							'operator' => '==',
							'value'    => self::QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'type'              => 'select',
				'multiple'          => true,
				'label'             => __( 'Filter by Taxonomies', 'tribe' ),
				'ui'                => true,
				'name'              => self::QUERY_TAXONOMIES,
				'key'               => self::QUERY_TAXONOMIES,
				'return_format'     => 'value',
				'choices'           => $this->taxonomies_allowed,
				'value'             => $field[ 'value' ][ self::QUERY_TAXONOMIES ] ?? [],
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::QUERY_TYPE,
							'operator' => '==',
							'value'    => self::QUERY_TYPE_AUTO,
						],
					],
				],
			],
		];
		foreach ( $this->taxonomies_allowed as $name => $label ) {
			$config[] = [
				'label'             => sprintf(
					__( 'Filter by %s Terms', 'tribe' ),
					$label
				),
				'name'              => self::QUERY_TAXONOMIES . '_' . $name,
				'key'               => self::QUERY_TAXONOMIES . '_' . $name,
				'type'              => 'taxonomy',
				'field_type'        => 'multi_select',
				'taxonomy'          => $name,
				'allow_null'        => false,
				'add_term'          => false,
				'save_terms'        => false,
				'load_terms'        => false,
				'value'             => $field[ 'value' ][ self::QUERY_TAXONOMIES . '_' . $name ] ?? [],
				'return_format'     => 'object',
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::QUERY_TAXONOMIES,
							'operator' => '==contains',
							'value'    => $name,
						],
					],
				],
			];
		}

		return apply_filters( 'tribe/acf_post_list/auto_config', $config );
	}

	/**
	 * @param $key
	 * @param $fields
	 */
	protected function add_config_to_field_group( $key, $fields ) {
		acf_add_local_field_group( [
			'key'    => $key,
			'fields' => [ $fields ],
		] );
	}

	/**
	 * In order for fields like relationships, post object, etc to work properly
	 * they need to be registered as local field groups.
	 */
	protected function add_field_groups() {
		$this->add_config_to_field_group( 'query_type_config', $this->get_query_types_config() );
		$this->add_config_to_field_group( 'auto_query_config', $this->get_auto_query_fields() );
		$this->add_config_to_field_group( 'manual_field_config', $this->get_manual_field_config() );
	}
}

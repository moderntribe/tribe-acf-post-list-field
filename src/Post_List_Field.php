<?php declare( strict_types=1 );

namespace Tribe\ACF_Post_List;

use WP_Post;
use acf_field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Custom Post List Field for ACF 5.0
 *
 * @package Tribe\ACF_Post_List
 */
class Post_List_Field extends acf_field {

	public const NAME = 'tribe_post_list';

	// Admin options
	public const SETTINGS_FIELD_AVAILABLE_TYPES = 'available_types';
	public const OPTION_AVAILABLE_TYPES_BOTH    = 'both';
	public const OPTION_AVAILABLE_TYPES_MANUAL  = 'manual';
	public const OPTION_AVAILABLE_TYPES_QUERY   = 'query';

	// Common Options
	public const SETTINGS_FIELD_LIMIT_MIN                 = 'limit_min';
	public const SETTINGS_FIELD_LIMIT_MAX                 = 'limit_max';
	public const SETTINGS_FIELD_POST_TYPES_ALLOWED        = 'post_types';
	public const SETTINGS_FIELD_POST_TYPES_ALLOWED_MANUAL = 'post_types_manual';
	public const SETTINGS_FIELD_TAXONOMIES_ALLOWED        = 'taxonomies';

	// Manual Options
	public const OPTION_ALLOW_OVERRIDE = 'allow_override';

	// Rendered Options
	public const FIELD_QUERY_TYPE         = 'query_type';
	public const OPTION_QUERY_TYPE_AUTO   = 'query_type_auto';
	public const OPTION_QUERY_TYPE_MANUAL = 'query_type_manual';

	// Manual query fields
	public const FIELD_MANUAL_QUERY     = 'manual_query';
	public const FIELD_MANUAL_POST      = 'manual_post';
	public const FIELD_MANUAL_TOGGLE    = 'manual_toggle';
	public const FIELD_MANUAL_TITLE     = 'manual_title';
	public const FIELD_MANUAL_EXCERPT   = 'manual_excerpt';
	public const FIELD_MANUAL_CTA       = 'manual_cta';
	public const FIELD_MANUAL_THUMBNAIL = 'manual_thumbnail';

	// Query Fields
	public const FIELD_QUERY_LIMIT      = 'query_limit';
	public const FIELD_QUERY_POST_TYPES = 'query_post_types';
	public const FIELD_QUERY_TERMS      = 'query_terms';

	/**
	 * The cache instance.
	 *
	 * @var \Tribe\ACF_Post_List\Cache
	 */
	protected $cache;

	/**
	 * @var string[]
	 */
	protected $settings = [];

	/**
	 * ACF_Post_List_Field_v5 constructor.
	 *
	 * @param  \Tribe\ACF_Post_List\Cache  $cache
	 * @param  array                       $settings
	 */
	public function __construct( Cache $cache, array $settings ) {
		parent::__construct();
		$this->cache    = $cache;
		$this->settings = $settings;
	}

	public function initialize() {
		parent::initialize();
		$this->name     = self::NAME;
		$this->label    = __( 'Tribe Post List', 'tribe' );
		$this->category = 'relational';
		$this->defaults = [
			self::SETTINGS_FIELD_AVAILABLE_TYPES           => self::OPTION_AVAILABLE_TYPES_BOTH,
			self::SETTINGS_FIELD_LIMIT_MAX                 => 10,
			self::SETTINGS_FIELD_LIMIT_MIN                 => 0,
			self::SETTINGS_FIELD_POST_TYPES_ALLOWED        => [],
			self::SETTINGS_FIELD_POST_TYPES_ALLOWED_MANUAL => [],
			self::SETTINGS_FIELD_TAXONOMIES_ALLOWED        => [],
			self::OPTION_ALLOW_OVERRIDE                    => true,
			self::FIELD_QUERY_LIMIT                        => 5,
			self::FIELD_QUERY_TYPE                         => self::OPTION_QUERY_TYPE_AUTO,
			self::FIELD_QUERY_TERMS                        => [],
			self::FIELD_QUERY_POST_TYPES                   => [],
		];
		$this->add_field_groups();
	}

	/**
	 * Add settings that allow the user to configure this field.
	 *
	 * @action acf/render_field_settings
	 *
	 * @param  array  $field  The $field being edited
	 */
	public function render_field_settings( array $field ): void {

		acf_render_field_setting( $field, [
			'label'        => __( 'Available Types', 'tribe' ),
			'instructions' => __( 'Allows you to set manual creation, auto query, or both.', 'tribe' ),
			'type'         => 'button_group',
			'name'         => self::SETTINGS_FIELD_AVAILABLE_TYPES,
			'choices'      => [
				self::OPTION_AVAILABLE_TYPES_BOTH   => __( 'Both', 'tribe' ),
				self::OPTION_AVAILABLE_TYPES_MANUAL => __( 'Manual Only', 'tribe' ),
				self::OPTION_AVAILABLE_TYPES_QUERY  => __( 'Automatic Query Only', 'tribe' ),
			],
		] );

		acf_render_field_setting( $field, [
			'label'    => __( 'Post Types', 'tribe' ),
			'type'     => 'select',
			'multiple' => true,
			'ui'       => true,
			'name'     => self::SETTINGS_FIELD_POST_TYPES_ALLOWED,
			'choices'  => acf_get_pretty_post_types(),
		] );

		acf_render_field_setting( $field, [
			'label'    => __( 'Post Types for Manual Query', 'tribe' ),
			'type'     => 'select',
			'multiple' => true,
			'ui'       => true,
			'name'     => self::SETTINGS_FIELD_POST_TYPES_ALLOWED_MANUAL,
			'choices'  => acf_get_pretty_post_types(),
		] );

		acf_render_field_setting( $field, [
			'label'         => __( 'Minimum Items', 'tribe' ),
			'type'          => 'number',
			'default_value' => 0,
			'min'           => 0,
			'step'          => 1,
			'name'          => self::SETTINGS_FIELD_LIMIT_MIN,
		] );

		acf_render_field_setting( $field, [
			'label'         => __( 'Maximum Items', 'tribe' ),
			'type'          => 'number',
			'default_value' => 12,
			'min'           => 1,
			'step'          => 1,
			'name'          => self::SETTINGS_FIELD_LIMIT_MAX,
		] );

		acf_render_field_setting( $field, [
			'label'        => __( 'Taxonomies' ),
			'instructions' => __( 'Select the taxonomies to be displayed' ),
			'type'         => 'select',
			'name'         => self::SETTINGS_FIELD_TAXONOMIES_ALLOWED,
			'multiple'     => 1,
			'ui'           => 1,
			'choices'      => acf_get_taxonomy_labels(),
		] );
	}

	/**
	 * Create the HTML interface for your field
	 *
	 * @action acf/render_field
	 *
	 * @param  array  $field  The $field being rendered
	 */
	public function render_field( array $field = [] ): void {
		$field = acf_parse_args( $field, $this->defaults );

		acf_render_field_wrap( $this->get_query_types_config( $field ) );
		acf_render_field_wrap( $this->get_manual_field_config( $field ) );

		foreach ( $this->get_auto_query_fields( acf_get_pretty_post_types( $field[ self::SETTINGS_FIELD_POST_TYPES_ALLOWED ] ), $field ) as $config ) {
			acf_render_field_wrap( $config );
		}

		// Output field data as a json string to a hidden field
		acf_hidden_input( [
			'name'      => esc_attr( $field['name'] ),
			'data-name' => esc_attr( 'tribe-post-list' ),
			'value'     => wp_json_encode( $field['value'] ),
		] );
	}

	/**
	 * This filter is applied to the $value after it is loaded from the db
	 *
	 * @filter    load_value
	 *
	 * @param  mixed  $value    The value found in the database
	 * @param  mixed  $post_id  The $post_id from which the value was loaded
	 * @param  array  $field    The field array holding all the field options
	 *
	 * @return mixed The modified value
	 */
	public function load_value( $value, $post_id, $field ) {
		if ( ! $value ) {
			return $this->defaults;
		}

		return $value;
	}

	/**
	 * This filter is applied to the $value after it is loaded from the db and before it is returned to the template
	 *
	 * @filter     format_value
	 *
	 * @param  mixed  $value    The value which was loaded from the database
	 * @param  mixed  $post_id  The $post_id from which the value was loaded
	 * @param  array  $field    The field array holding all the field options
	 *
	 * @return mixed The modified value
	 */
	public function format_value( $value, $post_id, $field ) {
		if ( empty( $value ) ) {
			return $value;
		}

		if ( self::OPTION_QUERY_TYPE_AUTO === $value[ self::FIELD_QUERY_TYPE ] ) {
			return $this->get_posts_from_query( $value );
		}

		return $this->get_manually_selected_posts( $value );
	}

	/**
	 * Decode field before saving it.
	 *
	 * @param $data
	 * @param $post_id
	 * @param $field
	 *
	 * @return mixed
	 */
	public function update_value( $data, $post_id, $field ) {
		return json_decode( $data, true );
	}

	/**
	 * Returns posts selected by the user.
	 *
	 * @return array
	 */
	private function get_manually_selected_posts( $value ): array {
		$manual_rows = $value[ self::FIELD_MANUAL_QUERY ] ?? [];

		$post_array = [];

		foreach ( $manual_rows as $row ) {
			$item = [];

			if ( ! $row[ self::FIELD_MANUAL_POST ] && ! $row[ self::FIELD_MANUAL_TOGGLE ] ) {
				continue; //no post and no override/custom
			}

			//Get manually selected post
			if ( $row[ self::FIELD_MANUAL_POST ] ) {
				$manual_post = get_post( $row[ self::FIELD_MANUAL_POST ] );

				if ( ! $manual_post ) {
					continue;
				}

				$item = $this->format_post( $manual_post );
			}

			//build custom or overwrite selected post above
			if ( $row[ self::FIELD_MANUAL_TOGGLE ] ) {
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
	 * @param  array  $values
	 * @param  array  $post_array
	 *
	 * @return array
	 */
	private function maybe_overwrite_values( $values, $post_array = [] ): array {
		if ( ! empty( $values[ self::FIELD_MANUAL_TITLE ] ) ) {
			$post_array['title'] = $values[ self::FIELD_MANUAL_TITLE ];
		}

		if ( ! empty( $values[ self::FIELD_MANUAL_EXCERPT ] ) ) {
			$post_array['excerpt'] = $values[ self::FIELD_MANUAL_EXCERPT ];
		}

		if ( ! empty( $values[ self::FIELD_MANUAL_THUMBNAIL ] ) ) {
			$post_array['image_id'] = (int) $values[ self::FIELD_MANUAL_THUMBNAIL ];
		}

		if ( $values[ self::FIELD_MANUAL_CTA ] && is_array( $values[ self::FIELD_MANUAL_CTA ] ) ) {
			$post_array['link'] = $values[ self::FIELD_MANUAL_CTA ];
		}

		return $post_array;
	}

	/**
	 * @param  array  $post_array
	 *
	 * @return bool
	 */
	private function is_valid_post( array $post_array ): bool {
		return ! ( empty( $post_array['title'] )
				   && empty( $post_array['excerpt'] )
				   &&
				   ! $post_array['image_id']
				   && empty( $post_array['link'] ) );
	}

	/**
	 * @param  array  $value
	 *
	 * @return array
	 */
	private function get_posts_from_query( $value ): array {
		$post_types = (array) ( $value[ self::FIELD_QUERY_POST_TYPES ] ?? [] );
		$tax_query  = $this->get_tax_query_args( $value );

		$args = [
			'post_type'      => $post_types,
			'tax_query'      => [
				'relation' => 'AND',
			],
			'post_status'    => 'publish',
			'posts_per_page' => $value[ self::FIELD_QUERY_LIMIT ] ?? self::SETTINGS_FIELD_LIMIT_MIN,
		];

		foreach ( $tax_query as $taxonomy => $ids ) {
			$args['tax_query'][] = [
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
	 * @param  array  $value
	 *
	 * @return array
	 */
	private function get_tax_query_args( array $value ): array {
		if ( empty( $value[ self::FIELD_QUERY_TERMS ] ) ) {
			return [];
		}

		$tax_and_terms = [];

		foreach ( $value[ self::FIELD_QUERY_TERMS ] as $term_id ) {
			$term = get_term( $term_id );

			if ( ! is_a( $term, 'WP_Term' ) ) {
				continue;
			}

			$tax_and_terms[ $term->taxonomy ][] = $term->term_id;
		}

		return $tax_and_terms;
	}

	/**
	 * @param  \WP_Post  $_post
	 *
	 * @return array
	 */
	private function format_post( WP_Post $_post ): array {
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
				'title'  => get_the_title(),
			],
			'post_type' => get_post_type(),
			'post_id'   => $_post->ID,
		];

		wp_reset_postdata();

		return $post_array;
	}


	/**
	 *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	 *  Use this action to add CSS + JavaScript to assist your render_field() action.
	 *
	 * @action admin_enqueue_scripts
	 */
	public function input_admin_head() {
		$url     = $this->settings['url'];
		$version = $this->settings['version'];

		wp_register_script( 'tribe-acf-post-list', "{$url}assets/js/post-list-field.js", [ 'acf-input' ], $version );
		wp_enqueue_script( 'tribe-acf-post-list' );

		wp_register_style( 'tribe-acf-post-list', "{$url}assets/css/post-list-field.css", [ 'acf-input' ], $version );
		wp_enqueue_style( 'tribe-acf-post-list' );
	}

	/**
	 * Field config for query type config
	 *
	 * @param  array  $field
	 *
	 * @return array
	 */
	private function get_query_types_config( $field = [] ): array {
		$config = [
			'label'   => __( 'Type of Query', 'tribe' ),
			'name'    => self::FIELD_QUERY_TYPE,
			'key'     => self::FIELD_QUERY_TYPE,
			'type'    => 'button_group',
			'value'   => $field['value'][ self::FIELD_QUERY_TYPE ] ?? self::OPTION_QUERY_TYPE_AUTO,
			'choices' => [
				self::OPTION_QUERY_TYPE_AUTO   => __( 'Automatic', 'tribe' ),
				self::OPTION_QUERY_TYPE_MANUAL => __( 'Manual', 'tribe' ),
			],
		];

		return apply_filters( 'tribe/acf_post_list/query_types_config', $config );
	}

	/**
	 * Config values for manual, repeater fields
	 *
	 * @param  array  $field
	 *
	 * @return array
	 */
	private function get_manual_field_config( $field = [] ): array {

		$config = [
			'min'               => $field['value'][ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ],
			'max'               => $field['value'][ self::SETTINGS_FIELD_LIMIT_MAX ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MAX ],
			'layout'            => 'row',
			'name'              => self::FIELD_MANUAL_QUERY,
			'key'               => self::FIELD_MANUAL_QUERY,
			'label'             => __( 'Manual Items', 'tribe' ),
			'type'              => 'repeater',
			'value'             => $field['value'][ self::FIELD_MANUAL_QUERY ] ?? [],
			'conditional_logic' => [
				[
					[
						'field'    => self::FIELD_QUERY_TYPE,
						'operator' => '==',
						'value'    => self::OPTION_QUERY_TYPE_MANUAL,
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
					'name'       => self::FIELD_MANUAL_POST,
					'key'        => self::FIELD_MANUAL_POST,
					'type'       => 'post_object',
					'post_type'  => [], // populated via acf/fields/post_object/query/name=
					'allow_null' => true,
				],
				[
					'label'        => __( 'Create or Override Content', 'tribe' ),
					'instructions' => __(
						'Data entered below will overwrite the respective data from the post selected above.',
						'tribe'
					),
					'name'         => self::FIELD_MANUAL_TOGGLE,
					'key'          => self::FIELD_MANUAL_TOGGLE,
					'type'         => 'true_false',
				],
				[
					'label'             => __( 'Title', 'tribe' ),
					'type'              => 'text',
					'name'              => self::FIELD_MANUAL_TITLE,
					'key'               => self::FIELD_MANUAL_TITLE,
					'conditional_logic' => [
						[
							'field'    => self::FIELD_MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'label'             => __( 'Excerpt', 'tribe' ),
					'type'              => 'textarea',
					'name'              => self::FIELD_MANUAL_EXCERPT,
					'key'               => self::FIELD_MANUAL_EXCERPT,
					'conditional_logic' => [
						[
							'field'    => self::FIELD_MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'name'              => self::FIELD_MANUAL_CTA,
					'key'               => self::FIELD_MANUAL_CTA,
					'label'             => __( 'Call to Action', 'tribe' ),
					'type'              => 'link',
					'conditional_logic' => [
						[
							'field'    => self::FIELD_MANUAL_TOGGLE,
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
				[
					'name'              => self::FIELD_MANUAL_THUMBNAIL,
					'key'               => self::FIELD_MANUAL_THUMBNAIL,
					'label'             => __( 'Thumbnail Image', 'tribe' ),
					'type'              => 'image',
					'return_format'     => 'id',
					'conditional_logic' => [
						[
							'field'    => self::FIELD_MANUAL_TOGGLE,
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
	 * Field config for the auto query values
	 *
	 * @param  array  $post_types_allowed
	 * @param  array  $field
	 *
	 * @return array
	 */
	private function get_auto_query_fields( array $post_types_allowed = [], $field = [] ): array {
		$config = [
			[
				'label'             => __( 'Build your Query', 'tribe' ),
				'type'              => 'message',
				'key'               => '',
				'conditional_logic' => [
					[
						[
							'field'    => self::FIELD_QUERY_TYPE,
							'operator' => '==',
							'value'    => self::OPTION_QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'type'              => 'select',
				'label'             => __( 'Post Types', 'tribe' ),
				'multiple'          => true,
				'ui'                => true,
				'name'              => self::FIELD_QUERY_POST_TYPES,
				'key'               => self::FIELD_QUERY_POST_TYPES,
				'choices'           => $post_types_allowed,
				'value'             => $field['value'][ self::FIELD_QUERY_POST_TYPES ] ?? [],
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::FIELD_QUERY_TYPE,
							'operator' => '==',
							'value'    => self::OPTION_QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'label'             => __( 'Limit', 'tribe' ),
				'name'              => self::FIELD_QUERY_LIMIT,
				'key'               => self::FIELD_QUERY_LIMIT,
				'value'             => $field['value'][ self::FIELD_QUERY_LIMIT ] ?? ( $field[ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ] ),
				'min'               => $field['value'][ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ],
				'max'               => $field['value'][ self::SETTINGS_FIELD_LIMIT_MAX ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MAX ],
				'step'              => 1,
				'type'              => 'range',
				'default_value'     => $field['value'][ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ],
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::FIELD_QUERY_TYPE,
							'operator' => '==',
							'value'    => self::OPTION_QUERY_TYPE_AUTO,
						],
					],
				],
			],
			[
				'label'             => __( 'Filter by Terms', 'tribe' ),
				'name'              => self::FIELD_QUERY_TERMS,
				'key'               => self::FIELD_QUERY_TERMS,
				'type'              => Multiple_Taxonomy_Field::NAME,
				'multiple'          => true,
				'ui'                => true,
				'taxonomies'        => [], // Pulled via ajax
				'value'             => $field['value'][ self::FIELD_QUERY_TERMS ] ?? [],
				'wrapper'           => [
					'class' => 'auto-query-row',
				],
				'conditional_logic' => [
					[
						[
							'field'    => self::FIELD_QUERY_TYPE,
							'operator' => '==',
							'value'    => self::OPTION_QUERY_TYPE_AUTO,
						],
					],
				],
			],
		];

		return apply_filters( 'tribe/acf_post_list/auto_config', $config );
	}

	/**
	 * @param $key
	 * @param $fields
	 */
	protected function add_config_to_field_group( $key, $fields ): void {
		acf_add_local_field_group( [
			'key'    => $key,
			'fields' => [ $fields ],
		] );
	}

	/**
	 * In order for fields like relationships, post object, etc to work properly
	 * they need to be registered as local field groups.
	 */
	private function add_field_groups(): void {
		$this->add_config_to_field_group( 'query_type_config', $this->get_query_types_config() );
		$this->add_config_to_field_group( 'auto_query_config', $this->get_auto_query_fields() );
		$this->add_config_to_field_group( 'manual_field_config', $this->get_manual_field_config() );
	}

}

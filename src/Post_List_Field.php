<?php declare(strict_types=1);

namespace Tribe\ACF_Post_List;

use acf_field;
use stdClass;
use WP_Post;

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
	public const OPTION_BUTTON_LABEL   = 'button_label';

	// Rendered Options
	public const FIELD_QUERY_TYPE         = 'query_type';
	public const OPTION_QUERY_TYPE_AUTO   = 'query_type_auto';
	public const OPTION_QUERY_TYPE_MANUAL = 'query_type_manual';

	// Manual query fields
	public const FIELD_MANUAL_QUERY       = 'manual_query';
	public const FIELD_MANUAL_POST        = 'manual_post';
	public const FIELD_MANUAL_TOGGLE      = 'manual_toggle';
	public const FIELD_MANUAL_TITLE       = 'manual_title';
	public const FIELD_MANUAL_EXCERPT     = 'manual_excerpt';
	public const FIELD_MANUAL_LINK_TOGGLE = 'manual_link_toggle';
	public const FIELD_MANUAL_CTA         = 'manual_cta';
	public const FIELD_MANUAL_THUMBNAIL   = 'manual_thumbnail';

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
		$this->cache    = $cache;
		$this->settings = $settings;
		parent::__construct();
	}

	public function initialize(): void {
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
			self::OPTION_BUTTON_LABEL                      => __( 'Add Row', 'tribe' ),
			self::FIELD_QUERY_LIMIT                        => 5,
			self::FIELD_QUERY_TYPE                         => self::OPTION_QUERY_TYPE_AUTO,
			self::FIELD_QUERY_TERMS                        => [],
			self::FIELD_QUERY_POST_TYPES                   => [],
			// Properly converts into JSON object
			self::FIELD_MANUAL_QUERY                       => new stdClass(),
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
			'label'        => __( 'Post Types', 'tribe' ),
			'instructions' => __( 'Limit the post types an editor can pick for the automatic query', 'tribe' ),
			'type'         => 'select',
			'multiple'     => true,
			'ui'           => true,
			'name'         => self::SETTINGS_FIELD_POST_TYPES_ALLOWED,
			'choices'      => acf_get_pretty_post_types(),
		] );

		acf_render_field_setting( $field, [
			'label'        => __( 'Post Types for Manual Query', 'tribe' ),
			'instructions' => __( 'Limit the post types an editor can pick for the manual post picker', 'tribe' ),
			'type'         => 'select',
			'multiple'     => true,
			'ui'           => true,
			'name'         => self::SETTINGS_FIELD_POST_TYPES_ALLOWED_MANUAL,
			'choices'      => acf_get_pretty_post_types(),
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
			'instructions' => __( 'Limit the terms an editor can filter by with these taxonomies', 'tribe' ),
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
		// Replace empty field data with data passed via ajax post.
		if ( empty( $field ) ) {
			$field = json_decode( filter_input( INPUT_POST, 'field_data', FILTER_SANITIZE_STRING ) ?: [] );
		}

		$field = acf_parse_args( $field, $this->defaults );

		acf_render_field_wrap( $this->get_query_types_config( $field ) );
		acf_render_field_wrap( $this->get_manual_field_config( $field ) );

		foreach ( $this->get_auto_query_fields( acf_get_pretty_post_types( $field[ self::SETTINGS_FIELD_POST_TYPES_ALLOWED ] ), $field ) as $config ) {
			acf_render_field_wrap( $config );
		}

		$field['value'][ self::SETTINGS_FIELD_LIMIT_MIN ] = $field[ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ];
		$field['value'][ self::SETTINGS_FIELD_LIMIT_MAX ] = $field[ self::SETTINGS_FIELD_LIMIT_MAX ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MAX ];

		// Output field data as a json string to a hidden field
		acf_hidden_input( [
			'name'      => esc_attr( $field['name'] ),
			'data-name' => esc_attr( 'tribe-post-list' ),
			'value'     => wp_json_encode( $field['value'] ),
		] );
	}

	/**
	 * This filter is applied to the $value after it is loaded from the db.
	 *
	 * If there is no value (e.g. when a block is initially loaded), replace the default values
	 * with data from the field itself.
	 *
	 * @filter    acf/load_value
	 *
	 * @param  mixed  $value    The value found in the database
	 * @param  mixed  $post_id  The $post_id from which the value was loaded
	 * @param  array  $field    The field array holding all the field options
	 *
	 * @return mixed The modified value
	 */
	public function load_value( $value, $post_id, $field ) {
		if ( ! $value ) {
			$value = array_replace( $this->defaults, array_intersect_key( $field, $this->defaults ) );
		}

		return $value;
	}

	/**
	 * This filter is applied to the $value after it is loaded from the db and before it is returned to the template
	 *
	 * @filter     acf/format_value
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

		if ( self::OPTION_QUERY_TYPE_AUTO === ( $value[ self::FIELD_QUERY_TYPE ] ?? '' ) ) {
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
		return json_decode( wp_unslash( $data ), true );
	}

	/**
	 * Returns posts selected by the user.
	 *
	 * @param $value
	 *
	 * @return array
	 */
	private function get_manually_selected_posts( $value ): array {
		$manual_rows = $value[ self::FIELD_MANUAL_QUERY ] ?? [];

		if ( empty( $manual_rows ) ) {
			return [];
		}

		$post_array = [];

		foreach ( $manual_rows as $row ) {
			$item = [];

			// No post and no override/custom
			if ( empty( $row[ self::FIELD_MANUAL_POST ] ) || empty( $row[ self::FIELD_MANUAL_TOGGLE ] ) ) {
				continue;
			}

			// Get manually selected post
			if ( $row[ self::FIELD_MANUAL_POST ] ) {
				$manual_post = get_post( $row[ self::FIELD_MANUAL_POST ] );

				if ( ! $manual_post ) {
					continue;
				}

				$item = $this->format_post( $manual_post );
			}

			// Build custom or overwrite selected post above
			if ( $row[ self::FIELD_MANUAL_TOGGLE ] ) {
				$item = $this->maybe_overwrite_values( $row, $item );
			}

			// Check if we have data for this post to remove any empty rows
			if ( ! $item || ! $this->is_valid_post( $item ) ) {
				continue;
			}

			$post_array[] = $item;
		}

		return $post_array;
	}

	/**
	 * Replace a post object's content with that manually entered by the user.
	 *
	 * @param  array  $repeater    The ACF repeater data.
	 * @param  array  $post_array  The post array to replace or build.
	 *
	 * @return array
	 */
	private function maybe_overwrite_values( array $repeater = [], $post_array = [] ): array {
		$post_array['title']    = ( $repeater[ self::FIELD_MANUAL_TITLE ] ?? '' ) ?: $post_array['title'] ?? '';
		$post_array['excerpt']  = ( $repeater[ self::FIELD_MANUAL_EXCERPT ] ?? '' ) ?: $post_array['excerpt'] ?? '';
		$post_array['image_id'] = (int) ( $repeater[ self::FIELD_MANUAL_THUMBNAIL ] ?? '' ) ?: $post_array['image_id'] ?? 0;
		$post_array['link']     = ( $repeater[ self::FIELD_MANUAL_CTA ] ?? [] ) ?: $post_array['link'] ?? [];

		// Allow the user to have a post with no hyperlink by creating empty defaults
		$disable_hyperlink = (bool) ( $repeater[ self::FIELD_MANUAL_LINK_TOGGLE ] ?? false );

		if ( $disable_hyperlink || empty( $post_array['link'] ) ) {
			$post_array['link'] = [
				'url'    => '',
				'target' => '',
				'title'  => '',
			];
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
		$post_types = (array) ( $value[ self::FIELD_QUERY_POST_TYPES ] ?? [] ) ?: ( $value[ self::SETTINGS_FIELD_POST_TYPES_ALLOWED ] ?? [] );
		$tax_query  = $this->get_tax_query_args( $value );
		$limit      = $value[ self::FIELD_QUERY_LIMIT ] ?? self::SETTINGS_FIELD_LIMIT_MIN;

		// Allow user to select no posts.
		if ( 0 === (int) $limit ) {
			return [];
		}

		$args = [
			'post_type'      => $post_types,
			'tax_query'      => [
				'relation' => 'AND',
			],
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
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

		/**
		 * Add all fields that need to have an event listener registered on render.
		 * Set the value to true if it's a field part of the manual query repeater.
		 */
		wp_localize_script( 'tribe-acf-post-list', 'TRIBE_POST_LIST_CONFIG', [
			'listenerFields' => [
				self::FIELD_MANUAL_POST        => true,
				self::FIELD_MANUAL_TITLE       => true,
				self::FIELD_MANUAL_EXCERPT     => true,
				self::FIELD_MANUAL_LINK_TOGGLE => true,
				self::FIELD_MANUAL_CTA         => true,
				self::FIELD_MANUAL_TOGGLE      => true,
				self::FIELD_MANUAL_THUMBNAIL   => true,
				self::FIELD_QUERY_LIMIT        => false,
				self::FIELD_QUERY_TYPE         => false,
			],
		] );

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
		if ( empty( $field ) ) {
			$field = json_decode( filter_input( INPUT_POST, 'field_data', FILTER_SANITIZE_STRING ) ?: '' );
		}

		$config = [
			'min'               => $field[ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ],
			'max'               => $field[ self::SETTINGS_FIELD_LIMIT_MAX ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MAX ],
			'layout'            => 'row',
			'name'              => self::FIELD_MANUAL_QUERY,
			'key'               => self::FIELD_MANUAL_QUERY,
			'label'             => __( 'Manual Items', 'tribe' ),
			'button_label'      => $field[ self::OPTION_BUTTON_LABEL ] ?? $this->defaults[ self::OPTION_BUTTON_LABEL ],
			'type'              => 'repeater',
			'value'             => $field['value'][ self::FIELD_MANUAL_QUERY ] ?? $field[ self::FIELD_MANUAL_QUERY ] ?? [],
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
					'conditional_logic' => [
						[
							'field'    => self::FIELD_MANUAL_POST,
							'operator' => '!=empty',
						],
					],
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
					'label'             => __( 'Disable hyperlink', 'tribe' ),
					'instructions'      => __(
						'No link will be output',
						'tribe'
					),
					'name'              => self::FIELD_MANUAL_LINK_TOGGLE,
					'key'               => self::FIELD_MANUAL_LINK_TOGGLE,
					'type'              => 'true_false',
					'default'           => false,
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
						[
							'field'    => self::FIELD_MANUAL_LINK_TOGGLE,
							'operator' => '!=',
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
				'min'               => $field[ self::SETTINGS_FIELD_LIMIT_MIN ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MIN ],
				'max'               => $field[ self::SETTINGS_FIELD_LIMIT_MAX ] ?? $this->defaults[ self::SETTINGS_FIELD_LIMIT_MAX ],
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

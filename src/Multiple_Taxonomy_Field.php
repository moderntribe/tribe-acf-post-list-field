<?php declare( strict_types=1 );

namespace Tribe\ACF_Post_List;

use acf_field_taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extend the ACF Taxonomy field to select from multiple taxonomies.
 *
 * @package Tribe\ACF_Post_List
 */
class Multiple_Taxonomy_Field extends acf_field_taxonomy {

	public const NAME = 'tribe_multiple_taxonomy';

	public const TERMS_PER_PAGE = 20;

	/**
	 * @var \Tribe\ACF_Post_List\Cache
	 */
	protected $cache;

	/**
	 * ACF_Post_List_Field_v5 constructor.
	 *
	 * @param  \Tribe\ACF_Post_List\Cache  $cache
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
		parent::__construct();
	}

	/**
	 * Overload the ACF Taxonomy field's initialization.
	 */
	public function initialize() {
		parent::initialize();

		$this->name     = self::NAME;
		$this->label    = __( 'Tribe Multiple Term Selector', 'tribe' );
		$this->defaults = [
			'taxonomies'    => [ 'category' ],
			'field_type'    => 'select',
			'multiple'      => 1,
			'allow_null'    => 0,
			'return_format' => 'id',
			'add_term'      => 0,
			'load_terms'    => 0,
			'save_terms'    => 0,
			'ui'            => 1,
			'ajax'          => 1,
			'ajax_action'   => sprintf( 'acf/fields/%s/query', self::NAME ),
			'placeholder'   => 'Select Term',
		];
		$this->hooks();
	}

	/**
	 * Register the hooks for this field.
	 */
	protected function hooks(): void {

		// Ajax related callbacks
		add_action( sprintf( 'wp_ajax_acf/fields/%s/query', self::NAME ), [ $this, 'ajax_query' ] );
		add_action( sprintf( 'wp_ajax_nopriv_acf/fields/%s/query', self::NAME ), [ $this, 'ajax_query' ] );

		// Wrapper attributes
		add_filter( 'acf/field_wrapper_attributes', [ $this, 'wrapper_attributes' ], 10, 2 );
	}

	public function wrapper_attributes( $wrapper, $field ) {
		if ( self::NAME === $field['type'] ) {
			$wrapper['class']     .= ' acf-field-taxonomy';
			$wrapper['data-type'] = 'taxonomy';
		}

		return $wrapper;
	}

	/**
	 * Overload ACF's get_ajax_query method to deliver grouped terms by their taxonomy.
	 *
	 * @param  array  $options
	 *
	 * @return array
	 */
	public function get_ajax_query( $options = [] ): array {

		// Defaults.
		$options = acf_parse_args( $options, [
			'post_id'   => 0,
			's'         => '',
			'field_key' => '',
			'paged'     => 0,
			'group'     => '',
		] );

		$field = $this->cache->get( $options['group'] );

		if ( ! $field ) {
			return [];
		}

		$results = [];
		$args    = [];
		$limit   = self::TERMS_PER_PAGE;
		$offset  = self::TERMS_PER_PAGE * ( $options['paged'] - 1 );

		// Hide Empty.
		$args['hide_empty'] = false;
		$args['number']     = $limit;
		$args['offset']     = $offset;

		// Pagination
		// Don't bother for hierarchical terms, we will need to load all terms anyway.
		if ( $options['s'] ) {
			$args['search'] = $options['s'];
		}

		// Get terms.
		$terms = get_terms( $field['taxonomies'], $args );

		// Build results loop.
		foreach ( $terms as $term ) {
			$taxonomy          = get_taxonomy( $term->taxonomy );
			$parents           = wp_list_pluck( $results, 'id' );
			$key               = array_search( $taxonomy->name, $parents, true );
			$field['taxonomy'] = $taxonomy->name;

			// Add terms to existing taxonomy optgroup.
			if ( is_int( $key ) ) {
				$results[ $key ]['children'][] = [
					'id'   => $term->term_id,
					'text' => $this->get_term_title( $term, $field, $options['post_id'] ),
				];
			} else {
				// Create a new optgroup
				$results[] = [
					'id'       => $taxonomy->name,
					'text'     => $taxonomy->label,
					'children' => [
						[
							'id'   => $term->term_id,
							'text' => $this->get_term_title( $term, $field, $options['post_id'] ),
						],
					],
				];
			}
		}

		return [
			'results' => $results,
			'limit'   => $limit,
		];
	}

	/**
	 * Render the HTML interface for this field.
	 *
	 * @param  array  $field
	 */
	public function render_field( $field ) {

		// Force value to array.
		$field['value']    = acf_get_array( $field['value'] );
		$field['multiple'] = 0;

		// Vars.
		$div = [
			'class'           => 'acf-taxonomy-field acf-soh',
			'data-save'       => $field['save_terms'],
			'data-type'       => $field['field_type'],
			'data-taxonomies' => $field['taxonomies'],
			'data-ftype'      => 'select',
		];

		?>
        <div <?php acf_esc_attrs( $div ); ?>>
			<?php $this->render_field_select( $field ); ?>
        </div>
		<?php
	}


	/**
	 * Render the taxonomy select field.
	 *
	 * @param  array  $field
	 */
	public function render_field_select( $field ): void {

		// Change Field into a select.
		$field['type']     = 'select';
		$field['ui']       = 1;
		$field['ajax']     = 1;
		$field['multiple'] = 1;
		$field['choices']  = [];

		$choices = [];

		if ( count( $field['value'] ) >= 1 ) {
			$terms = get_terms( [
				'hide_empty' => false,
				'include'    => $field['value'],
			] );

			foreach ( $terms as $term ) {
				$choices[ $term->term_id ] = $term->name;
			}

		}

		$field['choices'] = $choices;

		acf_render_field( $field );
	}

	/**
	 * Render the field settings.
	 *
	 * @param  array  $field
	 */
	public function render_field_settings( $field ): void {
		// Default value.
		acf_render_field_setting( $field, [
			'label'        => __( 'Taxonomies', 'tribe' ),
			'instructions' => __( 'Select the taxonomies to be displayed', 'tribe' ),
			'type'         => 'select',
			'name'         => 'taxonomies',
			'multiple'     => 1,
			'ui'           => 1,
			'choices'      => acf_get_taxonomy_labels(),
		] );

		// Allow null.
		acf_render_field_setting( $field, [
			'label'        => __( 'Allow Null?', 'tribe' ),
			'instructions' => '',
			'name'         => 'allow_null',
			'type'         => 'true_false',
			'ui'           => 1,
			'conditions'   => [
				'field'    => 'field_type',
				'operator' => '!=',
				'value'    => 'checkbox',
			],
		] );

		// Save terms.
		acf_render_field_setting( $field, [
			'label'        => __( 'Save Terms', 'tribe' ),
			'instructions' => __( 'Connect selected terms to the post', 'tribe' ),
			'name'         => 'save_terms',
			'type'         => 'true_false',
			'ui'           => 1,
		] );

		// Load terms.
		acf_render_field_setting( $field, [
			'label'        => __( 'Load Terms', 'tribe' ),
			'instructions' => __( 'Load value from posts terms', 'tribe' ),
			'name'         => 'load_terms',
			'type'         => 'true_false',
			'ui'           => 1,
		] );

		// Return format.
		acf_render_field_setting( $field, [
			'label'        => __( 'Return Value', 'tribe' ),
			'instructions' => '',
			'type'         => 'radio',
			'name'         => 'return_format',
			'choices'      => [
				'object' => __( 'Term Object', 'tribe' ),
				'id'     => __( 'Term ID', 'tribe' ),
			],
			'layout'       => 'horizontal',
		] );
	}

}

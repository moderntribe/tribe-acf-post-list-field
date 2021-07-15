(
	function ( $ ) {

		// Global as set via wp_localize_script()
		var postListFieldConfig = window.TRIBE_POST_LIST_CONFIG || [];

		const state = {
			oldRowIndex: 0,
		};

		// Append the group's field key to the ajax request
		acf.add_filter( 'select2_ajax_data', function ( data, args, $input, field, instance ) {
			const fields = document.querySelectorAll( '[data-type=\'tribe_post_list\']' );
			const input = document.querySelector( '[data-name=tribe-post-list]' );

			if ( fields.length ) {
				data.group = fields.item( 0 ).getAttribute( 'data-key' );
			}

			if ( input && input.value ) {
				data.field_data = input.value;
			}

			return data;
		} );

		// Register event listeners for our select2 inputs.
		acf.addAction( 'select2_init', function ( $select, args, settings, field ) {
			const allowedFields = ['query_post_types', 'query_terms'];

			if ( !allowedFields.includes( field.data.name ) ) {
				return;
			}

			$select.bind( 'change', function ( e ) {
				const selected = $( '#' + e.target.getAttribute( 'id' ) ).select2( 'data' );

				let fieldData = getFieldData( field.$el );

				// Add the terms to the hidden field.
				if ( 'query_terms' === field.data.name ) {
					fieldData.query_terms = selected.map( ( selection ) => selection.id );
				}

				// Add post types to the hidden field.
				if ( 'query_post_types' === field.data.name ) {
					fieldData.query_post_types = selected.map( ( selection ) => selection.id );
				}

				saveFieldData( fieldData, field.$el );
			} );
		} );

		/**
		 * Get the jQuery field object.
		 *
		 * @param {jQuery} $field
		 *
		 * @returns {*|Window.jQuery|HTMLElement}
		 */
		const getFieldObject = function ( $field ) {
			const container = $field.closest( '[data-type="tribe_post_list"]' );
			return $( '[data-name="tribe-post-list"]', container );
		};

		/**
		 * Retrieve the JSON parsed hidden input data.
		 *
		 * @param {jQuery} $field
		 *
		 * @returns {any}
		 */
		const getFieldData = function ( $field ) {
			return JSON.parse( getFieldObject( $field ).val() );
		};

		/**
		 * Save the field data.
		 *
		 * @param {object} fieldData
		 * @param {jQuery} $field
		 * @returns {*|string|jQuery}
		 */
		const saveFieldData = function ( fieldData, $field ) {
			return getFieldObject( $field ).val( JSON.stringify( fieldData ) );
		};

		/**
		 * Return formatted for an ACF link field.
		 *
		 * @param {object} field
		 * @returns {{title, url, target}}
		 */
		const getLinkValue = function ( field ) {
			return {
				title: field.$el.find( '.input-title' ).val(),
				url: field.$el.find( '.input-url' ).val(),
				target: field.$el.find( '.input-target' ).val(),
			};
		};

		/**
		 * Build the structure for the manual post query data.
		 *
		 * @param {Object[]} initialData
		 * @param {object} field
		 * @param {number} index
		 * @param {string | Object[]} value
		 */
		const createManualQuery = function( initialData, field, index, value ) {
			const newEntry = {
				// Defaults:
				manual_toggle: 0,
				manual_link_toggle: 0,
				manual_cta: {},
				// Initial data
				...initialData[ index ],
				// New value
				[ field.data.key ]: value,
			};

			const data = [ ...initialData ];

			data[ index ] = newEntry;

			return data;
		};

		/**
		 * Persists values in hidden input.
		 *
		 * @param {object} field
		 * @param {boolean} isManualQuery
		 */
		const persistValues = function ( field, isManualQuery = false ) {
			// Only way to get the position, not present in field object
			const index = field.$el.closest( '.acf-row' ).index();

			return function ( e ) {
				let val = e.target.value;

				switch ( field.data.type ) {
					case 'link':
						val = getLinkValue( field );
						break;
					case 'true_false':
						val = e.target.checked ? 1 : 0;
						break;
				}

				let fieldData = getFieldData( field.$el );

				if ( isManualQuery ) {
					fieldData.manual_query = createManualQuery( fieldData.manual_query, field, index, val );
				} else {
					fieldData[field.data.key] = val;
				}

				saveFieldData( fieldData, field.$el );
			};
		};

		/**
		 * Remove a manual query post.
		 *
		 * @param $el The row
		 */
		const removeManualQuery = function ( $el ) {
			// Skip if this is not an ACF post list field
			if ( $el.parents( '.acf-field-tribe-post-list' ).length === 0 ) {
				return;
			}

			const index = $el.index();

			if ( index < 0 ) {
				return;
			}

			let fieldData = getFieldData( $el );
			fieldData.manual_query.splice( index, 1 );
			saveFieldData( fieldData, $el );
		};

		/**
		 * Register event listener for our fields
		 * Runs as soon as the fields are rendered
		 *
		 * @param {object} field
		 */
		acf.addAction( 'new_field', function ( field ) {
			const keys = Object.keys( postListFieldConfig.listenerFields );

			if ( !keys.includes( field.data.key ) ) {
				return;
			}

			field.$el.bind( 'change input', persistValues( field, postListFieldConfig.listenerFields[field.data.key] ) );
		} );

		/**
		 * Remove a manual post when the ACF row is deleted
		 */
		acf.addAction( 'remove', removeManualQuery );

		/**
		 * Store the index of the row as it's being dragged
		 */
		acf.addAction( 'sortstart', function( $el ) {
			// Skip if this is not an ACF post list field
			if ( $el.closest( '.acf-field-tribe-post-list' ).length === 0 ) {
				return;
			}

			state.oldRowIndex = $el.index();
		} );

		/**
		 * Move the position of the dragged row
		 */
		acf.addAction( 'sortstop', function( $el ) {
			// Skip if this is not an ACF post list field
			if ( $el.closest( '.acf-field-tribe-post-list' ).length === 0 ) {
				return;
			}

			const index = $el.index();
			let fieldData = getFieldData( $el );
			if ( !fieldData.manual_query.length ) {
				return;
			}

			const old = fieldData.manual_query.splice( state.oldRowIndex, 1 );
			fieldData.manual_query.splice( index, 0, old.shift() );
			saveFieldData( fieldData, $el );
		} );
	}
)( jQuery );

(function($){

    // Append the group's field key to the ajax request
    acf.add_filter('select2_ajax_data', function( data, args, $input, field, instance  ){
        const fields = document.querySelectorAll("[data-type='tribe_post_list']");
        const input = document.querySelector('[data-name=tribe-post-list]').value;

        if (fields.length) {
            data.group = fields.item(0).getAttribute( 'data-key' );
        }

        if (input) {
            data.field_data = input;
        }

        return data;
    });

    // Register event listeners for our select2 inputs.
    acf.addAction('select2_init', function( $select, args, settings, field ){
        const allowedFields = ['query_post_types', 'query_terms'];

        if (!allowedFields.includes(field.data.name)) {
            return;
        }

        $select.bind( 'change', function(e) {
            const selected = $( '#' + e.target.getAttribute('id') ).select2('data');
            const $postList = $('[data-name=tribe-post-list]');

            let fieldData = JSON.parse( $postList.val() );

            // Add the terms to the hidden field.
            if('query_terms' === field.data.name) {
                fieldData.query_terms = selected.map(selection => selection.id);
            }

            // Add post types to the hidden field.
            if('query_post_types' === field.data.name) {
                fieldData.query_post_types = selected.map(selection => selection.id);
            }

            $postList.val( JSON.stringify(fieldData) );

            console.log(fieldData);
        });
    });

    // Register event listener for the limit slider
    acf.addAction('new_field/key=query_limit', function (field) {
        console.log(field);
        field.$el.bind('change', function (e) {
            const val = e.target.value;
            console.log(val);
            const $postList = $('[data-name=tribe-post-list]');

            let fieldData = JSON.parse($postList.val());

            fieldData.query_limit = val;
            $postList.val(JSON.stringify(fieldData));
        });
    });

    // Register event listener for query type
    acf.addAction('new_field/key=query_type', function (field) {
        field.$el.bind('change', function (e) {
            console.log(e.target.value);
            const val = e.target.value;
            const $postList = $('[data-name=tribe-post-list]');

            let fieldData = JSON.parse($postList.val());

            fieldData.query_type = val;
            $postList.val(JSON.stringify(fieldData));
        });
    });

})(jQuery);

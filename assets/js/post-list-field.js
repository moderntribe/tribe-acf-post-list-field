(function ($) {
    //TODO: This javascript should be cleaned up by someone who writes javascript :)
    acf.add_action('ready append', function ($el) {
        acf.get_fields({type: 'tribe_post_list'}, $el).each(function () {
            initialize_field($(this));
        });
    });

    let initialize_field = function ($field) {
        const data_obj_field = $field.find('.js-post-list-data');
        const data_obj = JSON.parse(data_obj_field.val()) || {};

        $field.on('change', '.acf-input select, .acf-input input, .acf-input textarea', function (e) {
            if ($(this).parents('.acf-field-repeater').length === 1) {
                //If this is a repeater... TODO: Abstract this to a function/method?
                let repeater_field = $(this).parents('.acf-field-repeater').first();
                let repeater_name = repeater_field.data('key');
                let repeater_rows = repeater_field.find('tbody:first > tr').not('.acf-clone');
                repeater_data = [];
                repeater_rows.each(function () {
                    field_data = {};
                    $(this).find('.acf-input select, .acf-input input, .acf-input textarea').each(function (key, value) {
                        let field_wrapper = $(this).closest('.acf-field');
                        if (field_wrapper.data('type') === 'true_false') {
                            field_data[field_wrapper.data('key')] = get_value_of_true_false($(this));
                        } else if (field_wrapper.data('type') === 'link') {
                            field_data[field_wrapper.data('key')] = get_value_of_link($(this));
                        } else {
                            field_data[field_wrapper.data('key')] = $(this).val();
                        }
                    });
                    repeater_data.push(field_data);
                });
                data_obj[repeater_name] = repeater_data;
            } else {
                let field_name = $(this).closest('.acf-field').data('key');
                data_obj[field_name] = $(this).val();
            }
            data_obj_field.val(JSON.stringify(data_obj));
        });

        $field.on('change', '[data-key="query_post_types"] .acf-input select', function (e) {
            update_taxonomy_options(e, $field);
        });
        $('[data-key="query_post_types"] .acf-input select').trigger('change'); //trigger an update if post type value is changed.
    };

    let get_value_of_link = function ($field) {
        let link_obj = {};
        $link = $field.closest('.acf-field').find('.link-node');
        if ($link === undefined) {
            return link_obj
        }

        link_obj.title = $link.text();
        link_obj.url = $link.prop('href');
        link_obj.target = $link.prop('target');
        return link_obj;

    };

    let get_value_of_true_false = function ($field) {
        return $field.is(':checked') ? 1 : 0;
    };

    let update_taxonomy_options = function (element, $field) {
        if (this.request) {
            // if a recent request has been made abort it
            this.request.abort();
        }
        let target = $(element.target);
        let allowed_taxonomies = $field.find('.js-post-list-data').data('allowed_taxonomies');
        let post_types = target.val(); //current post types selected
        let taxonomy_select = $field.find('[data-key="query_taxonomy_terms"] select');

        if (!post_types) {
            taxonomy_select.empty(); //No post types, no taxonomies
            $('[data-key="query_taxonomy_terms"] .acf-input select').trigger('change'); //trigger the conditional logic changes
            return;
        }

        taxonomy_select.empty();

        // set and prepare data for ajax
        let data = {
            action: 'load_taxonomy_choices',
            post_types: post_types,
            available_taxonomies: allowed_taxonomies,
        };

        data = acf.prepareForAjax(data);
        this.request = $.ajax({
            url: acf.get('ajaxurl'), // acf stored value
            data: data,
            type: 'post',
            dataType: 'json',
            success: function (json) {
                if (!json) {
                    return;
                }
                for (const [key, value] of Object.entries(json)) {
                    let tax_item = '<option value="' + key + '">' + value + '</option>';
                    taxonomy_select.append(tax_item);
                }
                $('[data-key="query_taxonomy_terms"] .acf-input select').trigger('change'); //trigger conditional logic update
            }
        });
    }
})(jQuery);
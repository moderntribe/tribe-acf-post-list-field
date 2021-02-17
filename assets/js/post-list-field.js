(function ($) {
  // Append the group's field key to the ajax request
  acf.add_filter('select2_ajax_data', function (data, args, $input, field, instance) {
    const fields = document.querySelectorAll("[data-type='tribe_post_list']");
    const input = document.querySelector('[data-name=tribe-post-list]').value;

    if (fields.length) {
      data.group = fields.item(0).getAttribute('data-key');
    }

    if (input) {
      data.field_data = input;
    }

    return data;
  });

  // Register event listeners for our select2 inputs.
  acf.addAction('select2_init', function ($select, args, settings, field) {
    const allowedFields = ['query_post_types', 'query_terms'];
    const $postList = $('[data-name=tribe-post-list]');

    if (!allowedFields.includes(field.data.name)) {
      return;
    }

    $select.bind('change', function (e) {
      const selected = $('#' + e.target.getAttribute('id')).select2('data');

      let fieldData = JSON.parse($postList.val());

      // Add the terms to the hidden field.
      if ('query_terms' === field.data.name) {
        fieldData.query_terms = selected.map((selection) => selection.id);
      }

      // Add post types to the hidden field.
      if ('query_post_types' === field.data.name) {
        fieldData.query_post_types = selected.map((selection) => selection.id);
      }

      $postList.val(JSON.stringify(fieldData));
    });
  });

  const getLinkValue = function (field) {
    return {
      title: field.$el.find('.input-title').val(),
      url: field.$el.find('.input-url').val(),
      target: field.$el.find('.input-target').val(),
    };
  };

  const createManualQuery = function (fieldData, field, rowID, value) {
    const newEntry = {
      // Defaults:
      manual_toggle: 0,
      manual_cta: {},
      // Initial data
      ...fieldData['manual_query'][rowID],
      // New value
      [field.data.key]: value,
    };

    return {
      ...fieldData.manual_query,
      [rowID]: newEntry,
    };
  };

  /**
   * Persists values in hidden input
   * @param {object} field 
   * @param {bool} isManualQuery 
   */
  const persistValues = function (field, isManualQuery) {
    const $postList = $('[data-name=tribe-post-list]');
    // Only way to get the ID, not present in field object
    let rowID = field.$el.closest('.acf-row').attr('data-id');

    // Keep just the ID
    if (rowID) {
      rowID = rowID.replace('row-', '');
    }

    return function (e) {
      const val = field.data.type === 'link' ? getLinkValue(field) : e.target.value;
      let fieldData = JSON.parse($postList.val());

      if (isManualQuery) {
        fieldData.manual_query = createManualQuery(fieldData, field, rowID, val);
      } else {
        fieldData[field.data.key] = val;
      }

      $postList.val(JSON.stringify(fieldData));
    };
  };

  // Register event listener for manual query fields
  // Runs as soon as the fields are rendered
  acf.addAction('new_field', function (field) {
    const observedFields = [
      'manual_post',
      'manual_title',
      'manual_excerpt',
      'manual_cta',
      'manual_toggle',
      'manual_thumbnail',
    ];

    if (!observedFields.includes(field.data.key)) {
      return;
    }

    field.$el.bind('change', persistValues(field, true));
  });

  // Register event listener for the limit slider
  acf.addAction('new_field/key=query_limit', function (field) {
    field.$el.bind('change', persistValues(field));
  });

  // Register event listener for query type
  acf.addAction('new_field/key=query_type', function (field) {
    field.$el.bind('change', persistValues(field));
  });
})(jQuery);

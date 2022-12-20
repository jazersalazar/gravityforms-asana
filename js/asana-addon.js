jQuery(document).ready(function ($) {
  $(document).on('change', '.asana-addon-workspace', function () {
    get_asana_options('project', 'workspace', $(this).val());
    get_asana_options('assignee', 'workspace', $(this).val());
    get_asana_options('section', 'project,', $(this).val());
  });

  $(document).on('change', '.asana-addon-project', function () {
    get_asana_options('section', 'project,', $(this).val());
  });

  // Handle pre-form submission
  let form_submitted = false;
  $('#gform-settings').submit(function (e) {
    if (form_submitted) {
      form_submitted = false;
      return;
    }

    e.preventDefault();

    jQuery.ajax({
      type      : 'post',
      url       : ajaxurl,
      data      : {
        action    : 'set_asana_options',
        form_id   : form.id,
        settings  : map_settings(),
      },
      complete: function () {
        form_submitted = true;
        $('#gform-settings-save').click();
      }
    });
  });

  function get_asana_options(option) {
    // Reset field options
    $('.asana-addon-' + option).val('');
    $('.asana-addon-' + option).html('<option value="">Fetching options...</option>');

    jQuery.ajax({
      type      : 'post',
      url       : ajaxurl,
      data      : {
        action    : 'get_asana_options',
        option    : option,
        settings  : map_settings(),
      },
      success   : function (response) {
        $('.asana-addon-' + option).html(response);
      }
    });
  }

  function map_settings() {
    let settings = {};
    $('.asana-addon-field').each(function () {
      let key = $(this).attr('id');
      settings[key] = $(this).val();
    });

    return settings;
  }
});
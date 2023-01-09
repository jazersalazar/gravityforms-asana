<?php
add_filter( 'gform_after_submission', 'asana_addon_create_task', 10, 2 );
function asana_addon_create_task( $entry, $form ) {
    $gfaa = GFAsanaAddOn::get_instance();

    if ( $gfaa->is_asana_configured( $form ) ) {
        $gfaa->create_asana_task( $entry, $form );
    }
}

// Skip if it's not edit page
if ( $_GET['page'] != 'gf_edit_forms' ) return;
// Skip if there's no form id
if ( !isset( $_GET['id'] ) ) return;

// Check if the current form is valid asana form
if ( !GFAsanaAddOn::get_instance()->is_asana_configured( GFAPI::get_form( $_GET['id'] ) ) ) return;

add_action( 'gform_field_advanced_settings', 'asana_addon_advanced_settings', 10, 2 );
function asana_addon_advanced_settings( $position, $form_id ) {
    //create settings on position -1 (bottom of advanced setting tab page)
    if ( $position == -1 ) :
        $form = GFAPI::get_form( $form_id );
        $fields = GFAsanaAddOn::get_instance()->get_asana_project_fields( $form ); ?>

        <li class="project_field_setting field_setting">
            <label for="field_project_field" class="section_label">Asana Project Field</label>
            <select id="field_project_field" onchange="SetFieldProperty('project_field', this.value);">
                <option value="">N/A</option>
                <option value="new field">Create New Field</option>
                <?php foreach ($fields as $gid => $field) : ?>
                    <option 
                        value='<?php echo $gid; ?>'
                        data-type='<?php echo $field->type; ?>'
                        data-options='<?php echo $field->options; ?>'
                    >
                        <?php echo $field->name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </li>

        <li class="project_field_default_value_setting field_setting">
            <label for="field_project_field_default_value" class="section_label">Asana Project Field - Default Value</label>
            <select id="field_project_field_default_value" onchange="SetFieldProperty('project_field_default_value', this.value);">
                <option value="">N/A</option>
            </select>
        </li>

    <?php endif;
}

//Action to inject supporting script to the form editor page
add_action( 'gform_editor_js', 'asana_addon_script' );
function asana_addon_script(){ ?>
    <script type='text/javascript'>
        //adding project field setting to all field type
        <?php foreach ( GF_Fields::get_all() as $gf_field ) echo 'fieldSettings.' . $gf_field->type . ' += ", .project_field_setting, .project_field_default_value_setting";'; ?>

        //binding to the load field settings event to initialize the field
        jQuery( document ).on( 'gform_load_field_settings', function( event, field, form ) {
            jQuery( '#field_project_field' ).val( rgar( field, 'project_field' ) ).change();

            let default_value = rgar( field, 'project_field_default_value' );
            if ( jQuery( '#field_project_field_default_value option[value="' + default_value + '"]' ).length ) {
                jQuery( '#field_project_field_default_value' ).val( default_value );
            }
        } );

        jQuery( document ).on('change', '#field_project_field', function() {
            let selected =  jQuery( this ).find( 'option:selected' );
            let options_list = '<option value="">N/A</option>';

            if ( selected.data( 'type' ) == 'enum' )  {
                let options = selected.data( 'options' );

                Object.entries( options ).forEach( ( [ key, option ] ) => {
                    options_list += '<option value="' + option.gid + '">' + option.name + '</option>';
                } );
            }

            jQuery( this ).parents( 'ul' ).find( '#field_project_field_default_value' ).html( options_list );
        } );
    </script>
    <?php
}

add_action( 'gform_after_save_form', 'asana_addon_after_save_form', 10, 2 );
function asana_addon_after_save_form( $form, $is_new ) {
    $save_form = false;
    $gfaa = GFAsanaAddOn::get_instance();

    foreach ( $form['fields'] as $index => $field ) {
        if ( $field['project_field'] == 'new field' ) {
            $save_form = true;

            $gid = $gfaa->create_asana_custom_field( $field['label'], $form );
            $form['fields'][ $index ]['project_field'] = $gid;
        }

        if ( ( $field['type'] == 'select' || $field['type'] == 'multiselect') && $field['project_field'] ) {
            $save_form = true;

            $form['fields'][ $index ]['choices'] = $gfaa->get_asana_custom_field_choices( $field['project_field'], $form );
        }
    }

    // Only update form if there's custom field changes
    if ( $save_form ) GFAPI::update_form( $form );
}

// Disable form editor ajax
add_filter( 'gform_disable_ajax_save', '__return_true' );
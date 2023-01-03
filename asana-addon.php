<?php
GFForms::include_addon_framework();

class GFAsanaAddOn extends GFAddOn {

    protected $_version = GF_ASANA_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'asana-addon';
    protected $_path = 'gravityforms-asana/asana-addon.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Asana';
    protected $_short_title = 'Asana';

    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFAsanaAddOn();
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();
        add_action( 'wp_ajax_get_asana_options', array( $this, 'get_asana_options' ) );
        add_filter( 'wp_ajax_set_asana_options',  array( $this, 'set_asana_options' ) );
    }

    public function scripts() {
        // Do not include script if it's not asana addon settings page
        if ( $_GET['subview'] != 'asana-addon' ) return  parent::scripts();

        $scripts = array(
            array(
                'handle'  => 'asana_addon_js',
                'src'     => $this->get_base_url() . '/js/asana-addon.js',
                'version' => $this->_version,
                'deps'    => array( 'jquery' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'enqueue' => array(
                    array(
                        'admin_page' => array( 'form_settings', 'plugin_settings' ),
                    )
                )
            ),
        );

        return array_merge( parent::scripts(), $scripts );
    }

    public function styles() {
        $styles = array(
            array(
                'handle'  => 'asana_addon_css',
                'src'     => $this->get_base_url() . '/css/asana-addon.css',
                'version' => $this->_version,
                'enqueue' => array(
                    array(
                        'admin_page' => array( 'form_settings', 'plugin_settings' ),
                    )
                )
            )
        );

        return array_merge( parent::styles(), $styles );
    }

    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Asana Add-On Settings', 'asana-addon' ),
                'fields' => array(
                    array(
                        'name'              => 'asana_pat',
                        'label'             => esc_html__( 'Personal Access Token', 'asana-addon' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                    ),
                )
            )
        );
    }

    public function form_settings_fields( $form ) {
        $asana_pat = $this->get_plugin_setting( 'asana_pat' );
        
        if ( !$asana_pat ) {
            $link = '<a target="_blank" href="' . admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '">here.</a>';
            echo 'Please provide your personal access token ' . $link;
            exit;
        }

        $settings = $this->get_form_settings( $form );

        $duedate = array(
            array(
                'label' => 'Please select a due date',
                'value' => '',
            ),
        );
        foreach ( $form['fields'] as $field ) {
            $duedate[] = array(
                'label' => esc_html__( $field['label'], 'asana-addon' ),
                'value' => $field['id'],
            );
        }

        $fields = array(
            array(
                'name'              => 'asana_title',
                'label'             => esc_html__( 'Title', 'asana-addon' ),
                'type'              => 'text',
                'class'             => 'medium asana-addon-field',
                'data-id'           => $form['id'],
            ),
            array(
                'name'              => 'asana_duedate',
                'label'             => 'Due Date',
                'type'              => 'select',
                'choices'           => $duedate,
                'class'             => 'medium asana-addon-field',
            ),
        );

        $asana_choices = array(
            'workspace',
            'project',
            'section',
            'assignee',
        );

        foreach ( $asana_choices as $asana_choice ) {
            $choices = $this->get_asana_choices( $asana_choice, $settings );

            $label = esc_html__( ucwords( $asana_choice ), 'asana-addon' );
            $fields[] = array(
                'name'              => 'asana_' . $asana_choice,
                'label'             => $label,
                'type'              => 'select',
                'choices'           => $choices,
                'class'             => 'medium asana-addon-field asana-addon-' . $asana_choice,
            );
        }

        return array(
            array(
                'title'  => esc_html__( 'Asana Form Settings', 'asana-addon' ),
                'fields' => $fields,
            ),
        );
    }

    public function get_asana_choices( $asana_choice, $settings ) {
        $asana_pat = $this->get_plugin_setting( 'asana_pat' );
        $client = Asana\Client::accessToken( $asana_pat );

        $asana_choices = array(
            array(
                'label' => esc_html__( 'Please select ' . ($asana_choice == 'assignee' ? 'an ' : 'a ') . $asana_choice, 'asana-addon' ),
                'value' => '',
                'name'  => 'none',
            ),
        );

        // Return with just default choices if there's missing config
        if ( !$settings && $asana_choice != 'workspace' ) return $asana_choices;
        if ( ( !isset( $settings['asana_workspace'] ) || !$settings['asana_workspace'] ) && $asana_choice != 'workspace' ) return $asana_choices;
        if ( ( !isset( $settings['asana_project'] ) || !$settings['asana_project'] ) && $asana_choice == 'section' ) return $asana_choices;

        $choices = array();
        switch ( $asana_choice ) {
            case 'workspace':
                $choices = $client->workspaces->getWorkspaces();
                break;

            case 'project':
                $choices = $client->projects->getProjectsForWorkspace( $settings['asana_workspace'] );
                break;
            
            case 'section':
                $choices = $client->sections->getSectionsForProject( $settings['asana_project'], array( 'opt_expand' => 'memberships' ) );
                break;

            case 'assignee':
                $choices = $client->users->getUsersForWorkspace( $settings['asana_workspace'] );
                break;
        }

        foreach ( $choices as $choice ) {
            $asana_choices[] = array(
                'label' => esc_html__( $choice->name, 'asana-addon' ),
                'value' => $choice->gid,
                'name'  => $choice->name,
            );
        }

        return $asana_choices;
    }

    public function get_asana_project_fields( $form ) {
        $asana_pat = $this->get_plugin_setting( 'asana_pat' );
        $client = Asana\Client::accessToken( $asana_pat );
        $settings = $this->get_form_settings( $form );

        $project_fields = $client->custom_field_settings->findByProject( $settings['asana_project'] );

        $asana_fields = array();
        foreach ( $project_fields as $project_field ) {
            $field = $project_field->custom_field;
            $field->options = $field->enum_options ? json_encode( $field->enum_options ) : '';
            $asana_fields[ $field->gid ] = $field;
        }

        return $asana_fields;
    }

    public function create_asana_custom_field( $field_name, $form ) {
        $asana_pat = $this->get_plugin_setting( 'asana_pat' );
        $client = Asana\Client::accessToken( $asana_pat, array( 'log_asana_change_warnings' => false ) );
        $settings = $this->get_form_settings( $form );

        $custom_field_options = array(
            'name'                   => $field_name,
            'resource_subtype'       => 'text'
        );

        $gid = $client->projects->addCustomFieldSettingForProject(
            $settings['asana_project'],
            array(
                'custom_field' => $custom_field_options
            )
        )->custom_field->gid;

        return $gid;
    }

    public function get_asana_custom_field_choices( $gid, $form ) {
        $choices = [];

        $fields = $this->get_asana_project_fields( $form );
        if ( $fields[ $gid ]->enum_options ) {
            foreach ( $fields[ $gid ]->enum_options as $option ) {
                if ( !$option->enabled ) continue;

                $choices[] = [
                    'value' => $option->gid,
                    'text'  => $option->name,
                ];
            }
        }

        return $choices;
    }

    public function create_asana_task( $entry, $form ) {
        $asana_pat = $this->get_plugin_setting( 'asana_pat' );
        $client = Asana\Client::accessToken( $asana_pat, array( 'log_asana_change_warnings' => false ) );
        $settings = $this->get_form_settings( $form );

        $asana_title = $settings['asana_title'];
        $asana_fields = array();
        $notes = "<i>This task is automatically created via form submission.</i>";
        $project_fields = $this->get_asana_project_fields( $form );

        // Interpolate custom title with entry fields
        preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $asana_title, $matches, PREG_SET_ORDER );
        if ( is_array( $matches ) ) {
            foreach ( $matches as $match ) {
                $asana_title = str_replace( $match[0], $entry[ $match[1] ], $asana_title );
            }
        }

        // Map asana fields
        foreach ( $form['fields'] as $field ) {
            $field_gid = $field->project_field;
            $default_gid = $field->project_field_default_value;
            if ( $field_gid ) {
                $value = $entry[ $field->id ] ? $entry[ $field->id ] : $default_gid;

                // Skip custom field if it doesn't have value
                if ( !$value ) continue;

                $asana_fields[ $field_gid ] = $value;

                // Support date type field
                if ( $project_fields[ $field_gid ]->type == 'date' ) {
                    $asana_fields[ $field_gid ] = array(
                        "date"  => $asana_fields[ $field_gid ]
                    );
                }

                // Support multiselect type field
                if ( $project_fields[ $field_gid ]->type == 'multi_enum' ) {
                    $multi_value = GFAddOn::maybe_decode_json( $value);
                    $asana_fields[ $field_gid ] = $multi_value;
                }
            }
        }

        $new_task_options = array(
            'name'          => $asana_title,
            'assignee'      => $settings['asana_assignee'],
            'due_on'        => $entry[ $settings['asana_duedate'] ],
            'projects'      => array( $settings['asana_project'] ),
            'memberships'   => array(
                array(
                    'project'   => $settings['asana_project'],
                    'section'   => $settings['asana_section'],
                )
            ),
            'html_notes'    => '<body>' . $notes . '</body>',
            'custom_fields' => $asana_fields,
        );

        $new_task = $client->tasks->createTask( $new_task_options );
    }

    public function is_asana_configured( $form ) {
        $settings = $this->get_form_settings( $form );
    
        return empty( $settings['asana_section']  ) ? false : true;
    }

    public function get_asana_options() {
        $option     = $_POST['option'];
        $settings   = $_POST['settings'];
        $choices = $this->get_asana_choices( $option, $settings );

        $html = '';
        foreach ($choices as $choice) {
            $html .= '<option value="' . $choice['value'] . '">' . $choice['label'] . '</option>';
        }

        echo $html;

        wp_die();
    }

    public function set_asana_options() {
        $form_id    = $_POST['form_id'];
        $settings   = $_POST['settings'];

        // Update the form field settings, so that gravity forms will allow to save the new option
        $form = GFAPI::get_form( $form_id );
        $form['asana-addon'] = $settings;
        GFAPI::update_form( $form );
    }
}
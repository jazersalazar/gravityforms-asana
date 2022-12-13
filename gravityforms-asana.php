<?php
/**
 * Plugin Name: Asana AddOn
 * Plugin URI: https://jazersalazar.com/
 * Description: Integrates asana functionalities inside gravity forms. 
 * Author: Jazer Salazar
 * Author URI: https://jazersalazar.com/
 * Version: 0.1
 */

require 'vendor/autoload.php';
require 'asana-form.php';

define( 'GF_ASANA_ADDON_VERSION', '0.1' );

class GF_Asana_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'asana-addon.php' );

        GFAddOn::register( 'GFAsanaAddOn' );
    }
}
add_action( 'gform_loaded', array( 'GF_Asana_AddOn_Bootstrap', 'load' ), 5 );

function gf_asana_addon() {
    return GFAsanaAddOn::get_instance();
}
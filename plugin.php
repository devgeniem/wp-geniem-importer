<?php
/**
 * Plugin Name: Geniem Importer
 * Plugin URI:  https://github.com/devgeniem/geniem-importer
 * Description: An object-oriented and developer friendly WordPress importer.
 * Version:     0.2.0
 * Author:      Geniem
 * Author URI:  http://www.geniem.fi/
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: geniem-importer
 * Domain Path: /languages
 */

namespace Geniem;

use \Geniem\Importer\Settings as Settings;
use \Geniem\Importer\Localization\Polylang as Polylang;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * The base class for the plugin.
 *
 * @package Geniem
 */
class Importer_Plugin {

    /**
     * Holds the general plugin data.
     *
     * @var array
     */
    protected static $plugin_data = [
        'VERSION' => '0.1.0',
    ];

    /**
     * Initialize the plugin.
     */
    public static function init() {
        // Set the basic settings.
        Settings::init( self::$plugin_data );
        Polylang::init();

        // If a custom autoloader exists, use it.
        if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
            require __DIR__ . '/vendor/autoload.php';
        }

         // Load the plugin textdomain.
        load_plugin_textdomain( 'geniem-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

// Initialize the plugin.
add_action( 'init', __NAMESPACE__ . '\\Importer_Plugin::init' );

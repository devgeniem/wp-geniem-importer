<?php
/**
 * Plugin Name: Geniem Importer
 * Plugin URI:  https://github.com/devgeniem/geniem-importer
 * Description: An object-oriented and developer friendly WordPress importer.
 * Version:     1.0.1
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The base class for the plugin.
 *
 * @package Geniem
 */
class Importer_Plugin {

    /**
     * Holds the general plugin data.
     * @todo : Get version dynamically from the plugin header information.
     *
     * @var array
     */
    protected static $plugin_data = [
        'TABLE_NAME' => 'geniem_importer_log',
    ];

    /**
     * Create the plugin database table on install.
     */
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . self::$plugin_data['TABLE_NAME'];

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
              id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              gi_id VARCHAR(255) NOT NULL,
              post_id BIGINT(20) UNSIGNED,
              import_date_gmt DATETIME NULL,
              data LONGTEXT NOT NULL,
              status VARCHAR(10) NOT NULL,
              PRIMARY KEY (id),
              INDEX gi_id (gi_id(255)),
              INDEX postid_date (post_id, import_date_gmt, status(10))
            ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $res = dbDelta( $sql );

    }

    /**
     * Initialize the plugin.
     */
    public static function init() {

        // If a custom autoloader exists, use it.
        if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
            require __DIR__ . '/vendor/autoload.php';
        }

        // Set the plugin version.
        $plugin_data = get_plugin_data( __FILE__, false, false );
        self::$plugin_data = wp_parse_args( $plugin_data, self::$plugin_data );

        // Set the basic settings.
        Settings::init( self::$plugin_data );
        Polylang::init();

         // Load the plugin textdomain.
        load_plugin_textdomain( 'geniem-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

// Install the plugin.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\Importer_Plugin::install' );

// Initialize the plugin.
add_action( 'init', __NAMESPACE__ . '\\Importer_Plugin::init' );

<?php
/**
 * Plugin Name: Geniem Importer
 * Plugin URI:  https://github.com/devgeniem/geniem-importer
 * Description: An object-oriented and developer friendly WordPress importer.
 * Version:     1.0.2
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
        return $table_name;
    }

    /**
     * Create tables on plugin activation
     *
     * @param bool $network_wide Whether we are activating the plugin network wide.
     */
    public static function create_tables_hook( bool $network_wide = false ) {
        if (
            \is_multisite() &&
            $network_wide
        ) {

            // If we are activating this network wide then generate tables for each blog
            global $wpdb;
            $blog_ids = \get_sites( [ 'fields' => 'ids' ] );
            foreach ( $blog_ids as $blog_id ) {
                static::register_table_for_blog( $blog_id );
            }
        }
        else {
            static::install();
        }
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
        \add_action( 'wpmu_new_blog', [ __CLASS__, 'wpmu_new_blog' ] );
        \add_filter( 'wpmu_drop_tables', [ __CLASS__, 'wpmu_drop_tables' ] );
    }

    /**
     * Register plugin tables on new blog create if plugin is activated network wide.
     *
     * @param int $blog_id Blog id that was created.
     */
    public static function wpmu_new_blog( int $blog_id ) {
        if ( \is_plugin_active_for_network( __FILE__ ) ) {
            static::register_table_for_blog( $blog_id );
        }
    }

    /**
     * Ran on blog deletion to delete plugin tables related to that blog
     *
     * @param  array $tables Tables to drop.
     * @return array         Modified $tables.
     */
    public static function wpmu_drop_tables( array $tables ) : array {
        $tables[] = static::install();
        return $tables;
    }

    /**
     * Register table for specific blog
     *
     * @param int $blog_id Blog id.
     */
    public static function register_table_for_blog( int $blog_id ) {
        \switch_to_blog( $blog_id );
        static::install();
        \restore_current_blog();
    }
}

// Install the plugin.
\register_activation_hook( __FILE__, __NAMESPACE__ . '\\Importer_Plugin::create_tables_hook' );

// Initialize the plugin.
add_action( 'init', __NAMESPACE__ . '\\Importer_Plugin::init' );

<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Settings
 *
 * @package Geniem\Importer
 */
class Settings {

    /**
     * Holds the settings.
     *
     * @var array
     */
    protected static $settings = [];

    /**
     * Initializes the plugin settings.
     *
     * @param array $plugin_data The basic plugin settings.
     */
    public static function init( $plugin_data ) {
        // Sets the VERSION setting.
        self::$settings = $plugin_data;

        self::set( 'GI_ID_PREFIX', 'gi_id_' );
        self::set( 'GI_ATTACHMENT_PREFIX', 'gi_attachment_' );
        self::set( 'GI_LOG_ERRORS', false );
        self::set( 'GI_TRANSIENT_KEY', 'gi_' );
        self::set( 'GI_TRANSIENT_EXPIRATION', HOUR_IN_SECONDS );
    }

    /**
     * Get a single setting.
     *
     * @param string $key The setting key.
     *
     * @return mixed|null The setting value, if found, null if not.
     */
    public static function get( $key ) {
        $key = strtoupper( $key );
        if ( isset( self::$settings[ $key ] ) ) {
            return self::$settings[ $key ];
        } else {
            return null;
        }
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public static function get_all() {
        return self::$settings;
    }

    /**
     * Setter for a single setting.
     * Every setting is overridable with constants.
     *
     * @param string $key   The setting key.
     * @param mixed  $value The setting value.
     */
    public static function set( $key, $value ) {
        $key = strtoupper( $key );
        if ( defined( $key ) ) {
            // Set the constant value.
            self::$settings[ $key ] = constant( $key );
        } else {
            // Set the value.
            self::$settings[ $key ] = $value;
        }
    }
}

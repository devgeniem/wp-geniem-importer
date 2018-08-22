<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

        self::set( 'id_prefix', 'gi_id_' );
        self::set( 'attachment_prefix', 'gi_attachment_' );
        self::set( 'log_errors', false );
        self::set( 'transient_key', 'gi_' );
        self::set( 'transient_expiration', HOUR_IN_SECONDS );
        self::set( 'tmp_folder', '/tmp/' );
        self::set( 'table_name', 'geniem_importer_log' );
        self::set( 'log_status_ok', 'OK' );
        self::set( 'log_status_fail', 'FAIL' );
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

        // If a constant is set that matches the setting key, use it.
        $constant_key = 'GI_' . strtoupper( $key );
        if ( defined( $constant_key ) ) {
            return constant( $constant_key );
        }

        if ( isset( self::$settings[ $key ] ) ) {
            return self::$settings[ $key ];
        }
        else {
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

<?php
/**
 * Created by PhpStorm.
 * User: villes
 * Date: 22/02/17
 * Time: 12:37
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
        self::$settings = $plugin_data;

        if ( defined( 'GI_ID_PREFIX' ) ) {
            self::$settings['GI_ID_PREFIX'] = GI_ID_PREFIX;
        } else {
            self::$settings['GI_ID_PREFIX'] = 'gi_id_';
        }
    }

    /**
     * Get a single setting.
     *
     * @param string $key The setting key.
     *
     * @return mixed|null The setting value, if found, null if not.
     */
    public static function get_setting( $key ) {
        $key = strtoupper( $key );
        if ( isset( self::$settings[ $key ] ) ) {
            self::$settings[ $key ];
        } else {
            return null;
        }
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public static function get_settings() {
        return self::$settings;
    }

    /**
     * Setter for a single setting.
     *
     * @param string $key   The setting key.
     * @param mixed  $value The setting value.
     */
    public static function set_setting( $key, $value ) {
        $key = strtoupper( $key );
        self::$settings[ $key  ] = $value;
    }
}
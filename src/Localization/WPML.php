<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer\Localization;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Polylang
 *
 * @package Geniem\Importer
 */
class WPML {
    /**
     * Holds polylang instance.
     *
     * @var object|null
     */
    protected static $wpml = null;

    /**
     * Holds language in an array.
     *
     * @var object|null
     */
    protected static $languages = [];

    /**
     * Holds current attachment id.
     *
     * @var string
     */
    protected static $current_attachment_ids = [];

    /**
     * Initialize.
     */
    public static function init() {
        // @todo : Do the code.
    }

    /**
     * Returns the polylang object.
     * @return object|null Polylang object.
     */
    public static function wpml() {
        return self::$wpml;
    }

    /**
     * Returns the polylang language list of language codes.
     * @return array Polylang language list.
     */
    public static function language_list() {
        return self::$wpml;
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function set_attachment_language( $attachment_post_id, $attachment_id, $language ) {
        // @todo : Do the code.
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function get_attachment_by_language( $attachment_post_id, $language ) {
        // @todo : Do the code.
    }

    /**
     * [get_attachment_post_ids description]
     * @param [type] $post_id    [description]
     * @param [type] $tr_id      [description]
     * @param [type] $lang->slug [description]
     */
    public static function get_attachment_post_ids( $post_id, $tr_id, $lang_slug ) {
        // @todo : Do the code.
    }

    /**
     * Save WPML locale
     * @todo move to polylang take only needed data post->i18n
     *
     * @param [type] $post
     * @return void
     */
    public static function save_wpml_locale( $post ) {
        // @todo : Do the code. Use getters for needed properties.
    }
}

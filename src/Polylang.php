<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Polylang
 *
 * @package Geniem\Importer
 */
class Polylang {
    /**
     * Holds polylang.
     *
     * @var object|null
     */
    protected static $polylang = null;

    /**
     * Holds polylang.
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
        $polylang  = function_exists( 'PLL' ) ? PLL() : null;
        $polylang  = $polylang instanceof \PLL_Frontend
            ? $polylang
            : null;

        if ( $polylang ) {
            // Get current languages.
            self::$languages = pll_languages_list();
            // Check if media duplication is on.
            if ( $polylang->model->options['media_support'] && $polylang->options['media']['duplicate'] ?? 0 ) {
                // Needed for PLL_Admin_Advanced_Media
                $polylang->filters_media     = new \PLL_Admin_Filters_Media( $polylang );
                // Acticates media duplication
                $polylang->gi_advanced_media = new \PLL_Admin_Advanced_Media( $polylang );
                // Hook into media duplication so we can add attachment_id meta.
                # add_action( 'pll_translate_media', array( __CLASS__, 'get_attachment_post_ids' ), 11, 3 );
            }
            self::$polylang = $polylang;
        }
    }

    /**
     * Returns the polylang object.
     * @return object|null Polylang object.
     */
    public static function pll() {
        return self::$polylang;
    }

    /**
     * Returns the polylang language list.
     * @return array Polylang language list.
     */
    public static function language_list() {
        return self::$languages;
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function set_attachment_language( $attachment_post_id, $attachment_id, $language ) {
        if ( $language ) {
            pll_set_post_language( $attachment_post_id, $language );
        }
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function get_attachment_by_language( $attachment_post_id, $language ) {
        if ( isset( self::$polylang->filters_media ) ) {
            $attachment_translations = pll_get_post_translations( $attachment_post_id );
            $attachment_post_id      = $attachment_translations[ $language ] ?? $attachment_post_id;
        }
        return $attachment_post_id;
    }

    /**
     * [get_attachment_post_ids description]
     * @param [type] $post_id    [description]
     * @param [type] $tr_id      [description]
     * @param [type] $lang->slug [description]
     */
    public static function get_attachment_post_ids( $post_id, $tr_id, $lang_slug ) {
        // $attachment_key = rtrim( Settings::get( 'GI_ATTACHMENT_PREFIX' ), '_' );
        // var_dump( $attachment_key );
        // $attachment_id  = get_post_meta( $post_id );
        // var_dump( $attachment_id );
    }
}

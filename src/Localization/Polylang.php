<?php
/**
 * Polylang translations controller.
 */

namespace Geniem\Importer\Localization;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Classes
use Geniem\Importer\Api as Api;
use Geniem\Importer\Post;
use Geniem\Importer\Settings as Settings;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

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
     * Holds polylang language codes.
     *
     * @var array
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

        if ( \function_exists( 'pll_languages_list' ) ) {
            /**
             * Get current languages.
             * Returns list of language codes.
             */
            self::$languages = pll_languages_list();
        }

        if ( $polylang ) {

            // Media index might not be set by default.
            if ( isset( $polylang->options['media'] ) ) {

                // Check if media duplication is on.
                if ( $polylang->model->options['media_support'] && $polylang->options['media']['duplicate'] ?? 0 ) {

                    // Needed for PLL_Admin_Advanced_Media.
                    $polylang->filters_media     = new \PLL_Admin_Filters_Media( $polylang );

                    // Activates media duplication.
                    $polylang->gi_advanced_media = new \PLL_Admin_Advanced_Media( $polylang );
                    // Hook into media duplication so we can add attachment_id meta.
                    # add_action( 'pll_translate_media', array( __CLASS__, 'get_attachment_post_ids' ), 11, 3 );
                }
            }

            self::$polylang = $polylang;
        } // End if().
    }

    /**
     * Returns the polylang object.
     *
     * @return object|null Polylang object.
     */
    public static function pll() {
        return self::$polylang;
    }

    /**
     * Returns the polylang language list of language codes.
     *
     * @return array
     */
    public static function language_list() {
        return self::$languages;
    }

     /**
      * Set attachment language by post_id
      *
      * @param int    $attachment_post_id Attachment wp id.
      * @param string $language The PLL language code.
      */
    public static function set_attachment_language( $attachment_post_id, $language ) {
        if ( $language ) {
            pll_set_post_language( $attachment_post_id, $language );
        }
    }

    /**
     * Get attachment by attachment id and language
     *
     * @param int    $attachment_post_id Attachment wp id.
     * @param string $language           The attachment locale.
     *
     * @return integer
     */
    public static function get_attachment_by_language( $attachment_post_id, $language ) {
        if ( isset( self::$polylang->filters_media ) ) {
            $attachment_translations = pll_get_post_translations( $attachment_post_id );
            $attachment_post_id      = $attachment_translations[ $language ] ?? $attachment_post_id;
        }
        return $attachment_post_id;
    }

    /**
     * Save Polylang locale.
     *
     * @param Post $post The current importer post object.
     * @return void
     */
    public static function save_pll_locale( &$post ) {

        // Get needed variables
        $post_id    = $post->get_post_id();
        $i18n       = $post->get_i18n();
        $locale     = Api::get_prop( $i18n, 'locale' );
        $master     = Api::get_prop( $i18n, 'master', false );

        // If pll information is in wrong format
        if ( is_array( $i18n ) ) {

            // Set post locale.
            \pll_set_post_language( $post_id, $locale );

            // Run only if master exists
            if ( $master ) {

                // Check if we need to link the post to its master.
                $master_key = Api::get_prop( $master, 'query_key', '' );

                if ( empty( $master_key ) ) {
                    // The 'master' property contains a 'gi_id'.
                    $master_key = $master;
                }

                // If a master key is not empty.
                if ( ! empty( $master_key ) ) {

                    // Get master post id for translation linking
                    $gi_id_prefix   = Settings::get( 'id_prefix' );
                    $master_id      = substr( $master_key, strlen( $gi_id_prefix ) );
                    $master_post_id = Api::get_post_id_by_api_id( $master_id );

                    // Set the link for translations if a matching post was found.
                    if ( $master_post_id ) {

                            // Get current translation.
                            $current_translations = \pll_get_post_translations( $master_post_id );

                            // Set up new translations.
                            $new_translations = [
                                'post_id' => $master_post_id,
                                $locale   => $post_id,
                            ];
                            $parsed_args = \wp_parse_args( $new_translations, $current_translations );

                            // Add and link translation.
                            \pll_save_post_translations( $parsed_args );
                    } // End if().
                } // End if().
            } // End if().
        } else {
            $post->set_error(
                'pll',
                $i18n,
                __( 'Post does not have pll information in right format.', 'geniem-importer' )
            );
        } // End if().
    }
}

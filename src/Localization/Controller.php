<?php
/**
 * Plugin localization controller.
 */

namespace Geniem\Importer\Localization;

// Classes
use Geniem\Importer\Api as Api;
use Geniem\Importer\Exception\PostException as PostException;
use Geniem\Importer\Settings as Settings;
use Geniem\Importer\Post as Post;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Localization
 *
 * @package Geniem\Importer
 */
class Controller extends Post {

    /**
     * Checks which translation plugin to use
     *
     * @return void
     */
    public static function save_locale( $post ) {

        $activated_i18n_plugin = self::get_activated_i18n_plugin();

        // If Polylang is activated use Polylang
        if ( $activated_i18n_plugin === 'polylang' ) {
            self::save_pll_locale( $post );
        }

        // If WPML is activated use WPML
        if ( $activated_i18n_plugin === 'wpml' ) {
            self::save_wpml_locale( $post );
        }
    }

    /**
     * Checks which translation plugin to use
     * @todo Can this be as a class property for later use?
     *
     * @return Slug of supported WordPress translation plugins. 'wpml', 'polylang'
     * if translation plugin is not found returns false
     */
    public static function get_activated_i18n_plugin() {

        // Checks if Polylang is installed and activated
        $polylang_activated = function_exists( 'PLL' );

        /**
         * Checks if WPML active
         * Polylang includes WPML api and WPML functions so we need to be more specific with WMPL.
         */
        $wpml_activated = defined( 'ICL_SITEPRESS_VERSION' );

        // If Polylang is activated use Polylang
        if ( $polylang_activated === true ) {
            return 'polylang';
        }

        // If WPML is activated use WPML
        if ( $wpml_activated === true ) {
            return 'wpml';
        }

        // If Polylang or wpml is not active leave an error message for debugging
        if ( $polylang_activated === false && $wpml_activated === false ) {
            return false;
            // @todo how to refactor set_error to support errors outside of Post class
            // Show an error if translation engines aren't activated
            /* $err = __( 'Polylang and WPML are both inactive. Please install and activate your desired translation plugin.', 'geniem-importer' );
            $this->set_error( 'i18n', '', $err ); */
        }
    }

    /**
     * Save Polylang locale
     *
     * @param [type] $post
     * @return void
     */
    public static function save_pll_locale( $post ) {

        // If pll information is empty
        if ( isset( $post->i18n ) ) {

            // If pll information is in wrong format
            if ( is_array( $post->i18n ) ) {

                // Set post locale.
                \pll_set_post_language( $post->post_id, $post->i18n['locale'] );

                // Check if we need to link the post to its master.
                $master_key = $post->i18n['master']['query_key'] ?? null;

                // If master key exists
                if ( ! empty( $master_key ) ) {

                    // @todo Api check - T: What it this?
                    // Get master post id for translation linking
                    $gi_id_prefix   = Settings::get( 'GI_ID_PREFIX' );
                    $master_id      = substr( $master_key, strlen( $gi_id_prefix ) );
                    $master_post_id = Api::get_post_id_by_api_id( $master_id );

                    // Set link for translations.
                    if ( $master_post_id ) {

                            // Get current translation.
                            $current_translations = \pll_get_post_translations( $master_post_id );

                            // Set up new translations.
                            $new_translations = [
                                'post_id'               => $master_post_id,
                                $post->i18n['locale']   => $post->post_id,
                            ];
                            $parsed_args = \wp_parse_args( $new_translations, $current_translations );

                            // Add and link translation.
                            \pll_save_post_translations( $parsed_args );
                    } // End if().
                } // End if().
            } else {
                // @todo show an error: Post doesn't have pll information in right format.
            } // End if().
        } else {
            // @todo show an error: Post pll information doesn't exists.
        } // End if().
    }

    /**
     * Save WPML locale
     */
    public static function save_wpml_locale( $post ) {
        // Do the code.
    }

}

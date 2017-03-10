<?php
/**
 * A collection of functions to use with the Geniem Importer.
 */

namespace Geniem\Importer;

/**
 * Class Api
 *
 * @package Geniem\Importer
 */
class Api {

    /**
     * Query the WP post id by the given api id.
     *
     * @param  int $id The api id to be mathced with postmeta.
     * @return int/boolean The found post id or 'false' for empty results.
     */
    public static function get_post_id_by_api_id( $id ) {
        global $wpdb;
        // Concatenate the meta key.
        $post_meta_key = Settings::get( 'GI_ID_PREFIX' ) . $id;
        // Prepare the sql.
        $prepared = $wpdb->prepare(
            'SELECT DISTINCT post_id FROM wp_postmeta WHERE meta_key = %s',
            $post_meta_key
        );
        // Fetch results from the postmeta table.
        $results = $wpdb->get_col( $prepared );
        if ( count( $results ) ) {
            return $results[0];
        }
        return false;
    }

}
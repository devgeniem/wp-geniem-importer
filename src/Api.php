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
     * @param  int $id     The api id to be matched with postmeta.
     * @return int|boolean The found post id or 'false' for empty results.
     */
    public static function get_post_id_by_api_id( $id ) {
        global $wpdb;
        // Concatenate the meta key.
        $post_meta_key = Settings::get( 'GI_ID_PREFIX' ) . $id;
        // Prepare the sql.
        $prepared = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
            $post_meta_key
        );
        // Fetch results from the postmeta table.
        $results = $wpdb->get_col( $prepared );

        if ( count( $results ) ) {
            return (int) $results[0];
        }

        return false;
    }

    /**
     * Query the WP post id by the given attachment id.
     *
     * @param  int $id     The attachment id to be matched with postmeta.
     * @return int|boolean The found attachment post id or 'false' for empty results.
     */
    public static function get_attachment_post_id_by_attachment_id( $id ) {
        global $wpdb;
        // Concatenate the meta key.
        $post_meta_key = Settings::get( 'GI_ATTACHMENT_PREFIX' ) . $id;
        // Prepare the sql.
        $prepared = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
            $post_meta_key
        );
        // Fetch results from the postmeta table.
        $results = $wpdb->get_col( $prepared );

        if ( count( $results ) ) {
            return (int) $results[0];
        }

        return false;
    }

    /**
     * Check if a string matches the post id query format.
     *
     * @param string $id_string The id string to inspect.
     *
     * @return string|false Returns the id without the prefix if valid, else returns false.
     */
    public static function is_query_id( $id_string ) {
        $gi_id_prefix  = Settings::get( 'GI_ID_PREFIX' );
        $prefix_length = strlen( $gi_id_prefix );
        if ( strncmp( $id_string, $gi_id_prefix, $prefix_length ) === 0 ) {
            return substr( $id_string, $prefix_length );
        }
        return false;
    }

    /**
     * [delete_post description]
     *
     * @param  [type]  $id           [description]
     * @param  boolean $force_delete Set as false, if you wish to trash instead of deleting.
     * @return mixed                 The post object (if it was deleted or moved to the trash successfully) or false (failure). If the post was moved to the trash, the post object represents its new state; if it was deleted, the post object represents its state before deletion.
     */
    public static function delete_post( $id, $force_delete = true ) {
        $post_id = self::get_post_id_by_api_id( $id );

        if ( $post_id ) {
            return wp_delete_post( $post_id, $force_delete );
        }

        return false;
    }

    public static function delete_all_posts( $force_delete = true ) {
        global $wpdb;
        $id_prefix     = Settings::get( 'GI_ID_PREFIX' );
        $identificator = rtrim( $id_prefix, '_' );
        $query         = "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = '%s'";
        $results       = $wpdb->get_col( $wpdb->prepare( $query, $identificator ) );

        if ( count( $results ) ) {
            foreach ( $results as $post_id ) {
                wp_delete_post( $post_id, $force_delete );
            }
        }
    }

    public static function get_prop( $item = [], $key = '', $default = '' ) {
        if ( is_array( $item ) && isset( $item[ $key ] ) ) {
            return $item[ $key ];
        } elseif ( is_object( $item ) && isset( $item->{ $key } ) ) {
            return $item->{ $key };
        } else {
            return $default;
        }
    }

    public static function set_prop( $item = [], $key = '', $value = '' ) {
        if ( is_array( $item ) ) {
            $item[ $key ]   = $value;
        } elseif ( is_object( $item ) ) {
            $item->{ $key } = $value;
        }
        return $value;
    }

}

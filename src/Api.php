<?php
/**
 * A collection of functions to use with the Geniem Importer.
 */

namespace Geniem\Importer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        $post_meta_key = Settings::get( 'id_prefix' ) . $id;
        // Prepare the sql.
        $prepared = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
            $post_meta_key
        );
        // Fetch results from the postmeta table.
        $results = $wpdb->get_col( $prepared );

        if ( empty( $results ) ) {
            return false;
        }

        if ( count( $results ) === 1 ) {
            return (int) $results[0];
        }

        foreach( $results as $result ) {
            if ( strpos( $post_meta_key , $result ) !== false ) {
                return (int) $result;
            }
        }

        return false;
    }

    /**
     * Deletes all postmeta related to a single post.
     * Flushes postmeta cache after database rows are deleted.
     *
     * @param int $post_id The WP post id.
     */
    public static function delete_post_meta_data( $post_id ) {
        global $wpdb;

        $query = $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = '%d'", $post_id );
        // @codingStandardsIgnoreStart
        $wpdb->query( $query );
        // @codingStandardsIgnoreEnd

        wp_cache_delete( $post_id, 'post_meta' );
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
        $post_meta_key = Settings::get( 'attachment_prefix' ) . $id;
        // Prepare the sql.
        $prepared = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
            $post_meta_key
        );
        // Fetch results from the postmeta table.
        $results = $wpdb->get_col( $prepared );

        if ( ! empty( $results ) ) {
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
        $gi_id_prefix  = Settings::get( 'id_prefix' );
        $prefix_length = strlen( $gi_id_prefix );
        if ( \strncmp( $id_string, $gi_id_prefix, $prefix_length ) === 0 ) {
            return substr( $id_string, $prefix_length );
        }
        return false;
    }

    /**
     * Delete a post by id
     *
     * @param  int  $id                 WordPress post_id
     * @param  boolean $force_delete    Set as false, if you wish to trash instead of deleting.
     * @return mixed                    The post object (if it was deleted or moved to the trash successfully) or false (failure). If the post was moved to the trash, the post object represents its new state; if it was deleted, the post object represents its state before deletion.
     */
    public static function delete_post( $id, $force_delete = true ) {
        $post_id = self::get_post_id_by_api_id( $id );

        if ( $post_id ) {
            return wp_delete_post( $post_id, $force_delete );
        }

        return false;
    }

    /**
     * Delete all posts
     *
     * @param boolean $force_delete
     * @return void
     */
    public static function delete_all_posts( $force_delete = true ) {
        global $wpdb;
        $id_prefix     = Settings::get( 'id_prefix' );
        $identificator = rtrim( $id_prefix, '_' );
        $query         = "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = '%s'";
        $results       = $wpdb->get_col( $wpdb->prepare( $query, $identificator ) );

        if ( ! empty( $results ) ) {
            foreach ( $results as $post_id ) {
                wp_delete_post( $post_id, $force_delete );
            }
        }
    }

    /**
     * Create a new term.
     *
     * @param  array $term Term data.
     * @param  Post  $post The current post instance.
     *
     * @return object|\WP_Error An array containing the `term_id` and `term_taxonomy_id`,
     *                        WP_Error otherwise.
     */
    public static function create_new_term( $term, &$post ) {
        $taxonomy = $term['taxonomy'];
        $name     = $term['name'];
        $slug     = $term['slug'];
        // There might be a parent set.
        $parent   = isset( $term['parent'] ) ? get_term_by( 'slug', $term['parent'], $taxonomy ) : false;
        // Insert the new term.
        $result   = wp_insert_term( $name, $taxonomy, [
            'slug'        => $slug,
            'description' => isset( $term['description'] ) ? $term['description'] : '',
            'parent'      => $parent ? $parent->term_id : 0,
        ] );
        // Something went wrong.
        if ( is_wp_error( $result ) ) {
            // @codingStandardsIgnoreStart
            $post->set_error( 'taxonomy', $name, __( 'An error occurred creating the taxonomy term.', 'geniem_importer' ) );
            // @codingStandardsIgnoreEnd
            return $result;
        }

        return (object) $result;
    }

    /**
     * A helper function for getting a property
     * from an object or an associative array.
     *
     * @param array  $item    An object or an associative array.
     * @param string $key     The item key we are trying to get.
     * @param string $default A default value to be returned if the item was not found.
     *
     * @return mixed
     */
    public static function get_prop( $item = [], $key = '', $default = '' ) {
        if ( is_array( $item ) && isset( $item[ $key ] ) ) {
            return $item[ $key ];
        }
        elseif ( is_object( $item ) && isset( $item->{ $key } ) ) {
            return $item->{ $key };
        }
        else {
            return $default;
        }
    }

    /**
     * A helper function for setting a property
     * into an object or an associative array.
     *
     * @param array  $item  An object or an associative array as a reference.
     * @param string $key   The property key we are trying to set.
     * @param mixed  $value The value for the property. Defaults to a null value.
     *
     * @return mixed
     */
    public static function set_prop( &$item = [], $key = '', $value = null ) {

        if ( is_array( $item ) ) {
            $item[ $key ] = $value;
        }
        elseif ( is_object( $item ) ) {
            $item->{ $key } = $value;
        }

        return $value;
    }

    /**
     * Checks whether some data is a JSON string.
     *
     * @param mixed $data The data to be checked.
     *
     * @return bool
     */
    public static function is_json( $data ) {
        json_decode( $data );

        return ( json_last_error() === JSON_ERROR_NONE );
    }

}

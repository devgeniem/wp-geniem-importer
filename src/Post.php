<?php
/**
 * The Post class is used to import posts into WordPres.
 */

namespace Geniem\Importer;

use Geniem\Importer\Exception\PostException as PostException;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Post
 *
 * @package Geniem\Importer
 */
class Post {

    /**
     * A unique id for external identification.
     *
     * @var string
     */
    protected $gi_id;

    /**
     * If this is an existing posts, the WP id is stored here.
     *
     * @var int|boolean
     */
    protected $post_id;

    /**
     * An object resembling the WP_Post class instance.
     *
     * @var object The post data object.
     */
    protected $post;

    protected $meta = [];

    protected $pll;

    protected $acf = [];

    protected $errors = [];

    /**
     * Post constructor.
     */
    public function __construct( $gi_id = null ) {
        if ( null === $gi_id ) {
           $this->errors['gi_id'] = __(
               'A unique id must be set for the Post constructor.', 'geniem-importer' );
        } else {
            // Fetch the WP post id, if it exists.
            $this->post_id = self::get_post_id( $this->gi_id );
            if ( $this->post_id ) {
                // Fetch the existing WP post object.
                $this->post = get_post( $this->post_id );
            }
        }
    }

    /**
     * Returns the instance errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Sets the basic data of a post.
     *
     * @param WP_Post|object $post Post object.
     */
    public function set_post( $post_obj ) {

        // If the post already exists, update values.
        if ( ! empty( $this->post ) ) {
            foreach ( get_object_vars( $post_obj ) as $attr => $value ) {
                $this->post->{$attr} = $value;
            }
        } else {
            // Set the post object.
            $this->post     = new \WP_Post( $post_obj );
            $this->post_id  = null;
        }

        // Filter values before validating.
        foreach ( get_object_vars( $this->post ) as $attr => $value ) {
            $this->post->{$attr} = apply_filters( "geniem_importer_post_values_{$attr}", $value );
        }

        // Validate it.
        $this->validate_post( $this->post );
    }

    /**
     * Validates the post object data.
     *
     * @param WP_Post $post_obj An WP_Post instance.
     */
    protected function validate_post( $post_obj ) {
        $errors = [];

        // TODO!

        if ( ! empty( $errors ) ) {
            $this->errors['post'] = $errors;
        }
    }

    /**
     * Sets the post meta data.
     *
     * @param array $meta_data The meta data in an associative array.
     */
    public function set_meta( $meta_data ) {
        $this->meta = $meta_data;
        $this->validate_meta( $this->meta );
    }

    /**
     * V
     */
    protected function validate_meta( $meta ) {
        $errors = [];

        // TODO!

        if ( ! empty( $errors ) ) {
            $this->errors['meta'] = $errors;
        }
    }

    /**
     * Stores the post instance and all its data into the database.
     *
     * @throws PostException If the post data is not valid.
     */
    public function save() {
        if ( ! $this->is_valid() ) {
            throw new PostException( __( 'The post data is not valid.', 'geniem-importer' ), 0, $this->get_errors() );
        }

        $post_arr = (array) $this->post;

        // Add the final post data filtering for imports.
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ], 1 );

        // Run the WP save function.
        $post_id = wp_insert_post( $post_arr );

        // Identify the post, if not yet done.
        if ( empty( $this->post_id ) ) {
            $this->post_id = $post_id;
            $this->identify();
        }

        // Save metadata.
        if ( ! empty( $this->meta ) ) {
            $this->save_meta();
        }

        // Remove the filter to prevent filtering data from other than importer inserts.
        remove_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ] );
    }

    /**
     * Saves the metadata of the post.
     */
    protected function save_meta() {
        if ( is_array( $this->meta ) ) {
            foreach ( $this->meta as $meta_key => $meta_value ) {
                update_post_meta( $this->post_id, $meta_key, $meta_value );
            }
        }
    }

    /**
     * Adds postmeta rows for matching a WP post with an external source.
     */
    protected function identify() {
        $id_prefix = Settings::get_setting( 'GI_ID_PREFIX' );
        // Remove the trailing '_'.
        $identificator = rtrim( $id_prefix, '_' );
        // Set the queryable identificator.
        // Example: meta_key = 'gi_id', meta_value = 12345
        update_post_meta( $this->post_id, $identificator, $this->gi_id );
        // Set the indexed indentificator.
        // Example: meta_key = 'gi_id_12345', meta_value = 12345
        update_post_meta( $this->post_id, $id_prefix . $this->gi_id, $this->gi_id );
    }

    /**
     * Checks whether the current post is valid.
     *
     * @return bool
     */
    protected function is_valid() {
        if ( empty( $this->errors ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Fetches a post id for a given external id.
     *
     * @param string $gi_id The external id.
     *
     * @return int|bool The post id, if found, false if not.
     */
    public static function get_post_id( $gi_id ) {
        global $wpdb;

        $id_prefix  = Settings::get_setting( 'GI_ID_PREFIX' );
        $query      = 'SELECT DISTINCT post_id FROM wp_postmeta WHERE meta_key = %s';
        $results    = $wpdb->get_col( $wpdb->prepare( $query, $id_prefix . $gi_id ) );
        if ( count( $results ) ) {
            return $results[0];
        } else {
            return false;
        }
    }

    /**
     * This function creates a filter for the 'wp_insert_posts_data' hook
     * which is enabled only while importing post data with Geniem Importer.
     *
     * @param $post_data
     *
     * @return mixed|void
     */
    public static function pre_post_save( $post_data ) {
        return apply_filters( 'geniem_importer_post_pre_save', $post_data );
    }
}
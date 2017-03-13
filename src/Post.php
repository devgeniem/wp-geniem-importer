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

    /**
     * Metadata in an associative array.
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Taxonomies in a multidimensional associative array.
     *
     * @see $this->set_taxonomies() For description.
     * @var array
     */
    protected $taxonomies = [];

    /**
     * An array for Polylang locale data.
     *
     * @var array
     */
    protected $pll = [];

    /**
     * An array of Advanced Custom Fields data.
     *
     * @var array
     */
    protected $acf = [];

    /**
     * Error messages under correspondings scopes as the key.
     * Example:
     *      [
     *          'post' => [
     *              'post_title' => 'The post title is not valid.'
     *          ]
     *      ]
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Post constructor.
     */
    public function __construct( $gi_id = null ) {
        if ( null === $gi_id ) {
            $this->set_error( 'id', 'gi_id', __( 'A unique id must be set for the Post constructor.', 'geniem-importer' ) );
        } else {
            // Fetch the WP post id, if it exists.
            $this->post_id = self::get_post_id( $gi_id );
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
     * @param WP_Post|object $post_obj Post object.
     */
    public function set_post( $post_obj ) {
        // If the post already exists, update values.
        if ( ! empty( $this->post ) ) {
            foreach ( get_object_vars( $post_obj ) as $attr => $value ) {
                $this->post->{$attr} = $value;
            }
        } else {
            // Set the post object.
            $this->post    = new \WP_Post( $post_obj );
            $this->post_id = null;
        }
        // Filter values before validating.
        foreach ( get_object_vars( $this->post ) as $attr => $value ) {
            $this->post->{$attr} = apply_filters( "geniem_importer_post_value_{$attr}", $value );
        }
        // Validate it.
        $this->validate_post( $this->post );
    }

    /**
     * Validates the post object data.
     *
     * @param \WP_Post $post_obj An WP_Post instance.
     */
    public function validate_post( $post_obj ) {
        $err_scope = 'post';

        // Validate the author.
        if ( isset( $post_obj->author ) ) {
            $user = \get_userdata( $post_obj->author );
            if ( $user === false ) {
                $err = __( 'Error in the "author" column. The value must be a valid user id.', 'geniem-importer' );
                $this->set_error( $err_scope, 'author', $err );
            }
        }

        // Validate date values
        if ( isset( $post_obj->post_date ) ) {
            $this->validate_date( $post_obj->post_date, 'post_date', $err_scope );
        }
        if ( isset( $post_obj->post_date_gmt ) ) {
            $this->validate_date( $post_obj->post_date_gmt, 'post_date_gmt', $err_scope );
        }
        if ( isset( $post_obj->post_modified ) ) {
            $this->validate_date( $post_obj->post_modified, 'post_modified', $err_scope );
        }
        if ( isset( $post_obj->post_modified_gtm ) ) {
            $this->validate_date( $post_obj->post_modified_gtm, 'post_modified_gtm', $err_scope );
        }

        // Validate the post status.
        if ( isset( $post_obj->post_status ) ) {
            $post_statuses = \get_post_statuses();
            if ( ! array_key_exists( $post_obj->post_status, $post_statuses ) ) {
                $err = __( 'Error in the "post_status" column. The value is not a valid post status.', 'geniem-importer' );
                $this->set_error( $err_scope, 'post_status', $err );
            }
        }

        // Validate the comment status.
        if ( isset( $post_obj->comment_status ) ) {
            $comment_statuses = [ 'hold', 'approve', 'spam', 'trash' ];
            if ( ! in_array( $post_obj->comment_status, $comment_statuses, true ) ) {
                $err = __( 'Error in the "comment_status" column. The value is not a valid comment status.', 'geniem-importer' );
                $this->set_error( $err_scope, 'comment_status', $err );
            }
        }

        // Validate the post parent.
        if ( isset( $post_obj->post_parent ) ) {
            $parent_id = $post_obj->post_parent;
            // The parent is in query format.
            if ( self::is_query_id( $parent_id ) ) {
                if ( Api::get_post_id_by_api_id( $parent_id ) === false ) {
                    $err = __( 'Error in the "post_parent" column. The queried post parent was not found.', 'geniem-importer' );
                    $this->set_error( $err_scope, 'menu_order', $err );
                }
            }
            // The parent is a WP post id.
            else {
                if ( \get_post( $parent_id ) === null ) {
                    $err = __( 'Error in the "post_parent" column. The parent id did not match any post.', 'geniem-importer' );
                    $this->set_error( $err_scope, 'menu_order', $err );
                }
            }
        }

        // Validate the menu order.
        if ( isset( $post_obj->menu_order ) ) {
            if ( ! is_integer( $post_obj->menu_order ) ) {
                $err = __( 'Error in the "menu_order" column. The value must be an integer.', 'geniem-importer' );
                $this->set_error( $err_scope, 'menu_order', $err );
            }
        }

        // Validate the post type.
        if ( isset( $post_obj->post_type ) ) {
            $post_types = get_post_types();
            if ( ! array_key_exists( $post_obj->post_type, $post_types ) ) {
                $err = __( 'Error in the "post_type" column. The value does not match a registered post type.', 'geniem-importer' );
                $this->set_error( $err_scope, 'post_type', $err );
            }
        }
    }

    /**
     * @param string $date_string The datetime string.
     * @param string $col_name    The posts table column name.
     * @param string $err_scope   The error scope name.
     */
    public function validate_date( $date_string = '', $col_name = '', $err_scope = '' ) {
        $valid = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
        if ( ! $valid ) {
            $err = __( "Error in the \"$col_name\" column. The value is not a valid datetime string.", 'geniem-importer' );
            $this->set_error( $err_scope, $col_name, $err );
        }
    }

    /**
     * Sets the post meta data.
     *
     * @param array $meta_data The meta data in an associative array.
     */
    public function set_meta( $meta_data = [] ) {
        // Force type to array.
        $this->meta = (array) $meta_data;
        // Filter values before validating.
        foreach ( $this->meta as $key => $value ) {
            $this->meta[$key] = apply_filters( "geniem_importer_meta_value_{$key}", $value );
        }
        $this->validate_meta( $this->meta );
    }

    /**
     * Validate postmeta.
     */
    public function validate_meta( $meta ) {
        $errors = [];
        // TODO!
        if ( ! empty( $errors ) ) {
            $this->set_error( 'meta', $errors );
        }
    }

    /**
     * Set the taxonomies of the post.
     * The taxonomies must be passed as an associative array
     * where the key is the taxonomy slug and values are associative array
     * with the name and the slug of the taxonomy term.
     * Example:
     *      $tax_array = [
     *          'category' => [
     *              [
     *                  'name' => 'My category',
     *                  'slug' => 'my-category',
     *              ]
     *      ];
     *
     * @param array $tax_array The taxonomy data.
     */
    public function set_taxonomies( $tax_array = [] ) {
        // Force type to array.
        $this->taxonomies = (array) $tax_array;
        // Filter values before validating.
        foreach ( $this->taxonomies as $key => $value ) {
            $this->taxonomies[$key] = apply_filters( "geniem_importer_taxonomy_{$key}", $value );
        }
        $this->validate_taxonomies( $this->taxonomies );
    }

    /**
     * Validate the taxonomy array.
     *
     * @param array $taxonomies The set taxonomies for the post.
     */
    public function validate_taxonomies( $taxonomies ) {
        if ( ! is_array( $taxonomies ) ) {
            $err = __( "Error in the taxonomies. Taxonomies must be passed in an associative array.", 'geniem-importer' );
            $this->set_error( 'taxonomy', $taxonomy, $err );
            return;
        }

        // The passed taxonomies must be currently registered.
        $registered_taxonomies = \get_taxonomies();
        foreach ( $taxonomies as $taxonomy => $terms ) {
            if ( ! in_array( $taxonomy, $registered_taxonomies, true ) ) {
                $err = __( "Error in the \"$taxonomy\" taxonomy. The taxonomy is not registerd.", 'geniem-importer' );
                $this->set_error( 'taxonomy', $taxonomy, $err );
            }
        }
    }

    /**
     * Stores the post instance and all its data into the database.
     *
     * @throws PostException If the post data is not valid.
     */
    public function save() {
        if ( ! $this->is_valid() ) {
            // Store the invalid data for later access.
            $key        = Settings::get( 'GI_TRANSIENT_KEY' ) . 'invalid_post_' . $this->gi_id;
            $expiration = Settings::get( 'GI_TRANSIENT_EXPIRATION' );
            set_transient( $key, get_object_vars( $this ), $expiration );
            throw new PostException( __( 'The post data is not valid.', 'geniem-importer' ), 0, $this->get_errors() );
        }
        $post_arr = (array) $this->post;

        // Add filters for data modifications before and after importer related database actions.
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ], 1 );
        add_filter( 'wp_insert_post', [ __CLASS__, 'after_post_save' ], 1 );

        // Add the

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

        // Save taxonomies.
        if ( ! empty( $this->taxonomies ) ) {
            $this->save_taxonomies();
        }

        // Remove the custom filters.
        remove_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ] );
        remove_filter( 'wp_insert_post', [ __CLASS__, 'after_post_save' ] );
    }

    /**
     * Saves the metadata of the post.
     */
    protected function save_meta() {
        if ( is_array( $this->meta ) ) {
            foreach ( $this->meta as $meta_obj ) {
                update_post_meta( $this->post_id, $meta_obj->meta_key, $meta_obj->meta_value );
            }
        }
    }

    /**
     * Sets the terms of a post and create taxonomy terms
     * if they do not exist yet.
     */
    protected function save_taxonomies() {
        if ( is_array( $this->taxonomies ) ) {
            foreach ( $this->taxonomies as $taxonomy => $terms ) {
                if ( is_array( $terms ) ) {
                    foreach ( $terms as &$term ) {
                        $name     = $term[ 'name' ];
                        $slug     = $term[ 'slug' ];
                        $term_obj = get_term_by( 'slug', $slug, $taxonomy );
                        // If the term does not exist, create it.
                        if ( ! $term_obj ) {
                            // There might be a parent set.
                            $parent = isset( $term[ 'parent' ] ) ? : get_term_by( 'slug', $term[ 'parent' ], $taxonomy );
                            // Insert the new term.
                            $result = wp_insert_term( $name, $taxonomy, [
                                'slug'        => $slug,
                                'description' => isset( $term[ 'description' ] ) ? : $term[ 'description' ],
                                'parent'      => $parent ? $parent->term_id : 0,
                            ] );
                            // Something went wrong.
                            if ( is_wp_error( $result ) ) {
                                self::set_error( 'taxonomy', $name, __( 'An error occurred creating the taxonomy term.', 'geniem_importer' ) );
                            }
                            // We only need the id.
                            $term_obj          = (object) [];
                            $term_obj->term_id = $result[ 'term_id' ];
                        }
                        // Set the term and store the result.
                        $term[ 'success' ] = $wp_set_object_terms( $this->post_id, $term_obj->term_id, $taxonomy );
                    }
                }
            }
        }
    }

    /**
     * Adds postmeta rows for matching a WP post with an external source.
     */
    protected function identify() {
        $id_prefix = Settings::get( 'GI_ID_PREFIX' );
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
        $id_prefix = Settings::get( 'GI_ID_PREFIX' );
        $query     = 'SELECT DISTINCT post_id FROM wp_postmeta WHERE meta_key = %s';
        $results   = $wpdb->get_col( $wpdb->prepare( $query, $id_prefix . $gi_id ) );
        if ( count( $results ) ) {
            return $results[ 0 ];
        } else {
            return false;
        }
    }

    /**
     * This function creates a filter for the 'wp_insert_posts_data' hook
     * which is enabled only while importing post data with Geniem Importer.
     * Use this to customize the imported data before any database actions.
     *
     * @param object $post_data The post data to be saved.
     *
     * @return mixed
     */
    public static function pre_post_save( $post_data ) {
        return apply_filters( 'geniem_importer_pre_post_save', $post_data );
    }

    public static function after_post_save( $post_ID, $post, $update ) {
        return apply_filters( 'geniem_importer_after_post_save', $post_ID, $post, $update );
    }

    /**
     * Sets a single error message or a full error array depending on the $key value.
     *
     * @param string       $scope The error scope.
     * @param string|array $key   The key or the error array.
     * @param string       $error The error message.
     */
    protected function set_error( $scope = '', $key = '', $error = '' ) {
        $this->errors[ $scope ] = isset( $this->errors[ $scope ] ) ? $this->errors[ $scope ] : [];
        if ( is_array( $key ) ) {
            // Set the full error array.
            $this->errors[ $scope ] = $key;
        } else {
            $this->errors[ $scope ][ $key ] = $error;
        }
        // Maybe log errors.
        if ( Settings::get( 'GI_LOG_ERRORS' ) ) {
            error_log( 'Geniem Importer: ' . $error );
        }
    }

    /**
     * Check if a string matches the post id query format.
     *
     * @param string $id_string The id string to inspect.
     *
     * @return bool
     */
    public static function is_query_id( $id_string ) {
        return substr( $post_obj->post_parent, 0, 5 ) === Settings::get( 'GI_ID_PREFIX' );
    }
}
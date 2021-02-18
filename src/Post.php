<?php
/**
 * The Post class is used to import posts into WordPres.
 */

namespace Geniem\Importer;

use Geniem\Importer\Exception\PostException as PostException;
use Geniem\Importer\Localization\Polylang as Polylang;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
     * Attachments in an indexed array.
     *
     * @var array
     */
    protected $attachments = [];

    /**
     * Holds attachments ids in an associative array
     * after is has been uploaded and saved.
     *
     * @var array $attachment_ids = [
     *      [ gi_attachment_{$id} => {$post_id} ]
     * ]
     */
    protected $attachment_ids = [];

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
     * An array for locale data.
     *
     * @var array
     */
    protected $i18n = [];

    /**
     * An array of Advanced Custom Fields data.
     *
     * @var array
     */
    protected $acf = [];

    /**
     * An array holding save functions already run.
     *
     * @var array
     */
    protected $save_state = [];

    /**
     * This value is true when rolling back a previous import state.
     * The rollback mode skips validations and logging.
     *
     * @var bool
     */
    protected $rollback_mode = false;

    /**
     * Get all save functions that have been run.
     *
     * @return array
     */
    public function get_savestate() {
        return $this->save_state;
    }

    /**
     * Use this to save the state of run save functions.
     *
     * @param string $save_state The object key for the saved data.
     */
    public function set_save_state( $save_state ) {
        $this->save_state[ $save_state ] = $save_state;
    }

    /**
     * Check if a specific object has been saved.
     *
     * @param string $saved The object key.
     * @return boolean
     */
    public function is_saved( $saved ) {
        return isset( $this->save_state[ $saved ] );
    }

    /**
     * Getter for post_id
     *
     * @return integer
     */
    public function get_post_id() {
        return $this->post_id;
    }

    /**
     * Getter for ig_id
     *
     * @return string
     */
    public function get_gi_id() {
        return $this->gi_id;
    }

    /**
     * Getter for i18n
     *
     * @return array
     */
    public function get_i18n() {
        return $this->i18n;
    }

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
     * Sets a single error message or a full error array depending on the $key value.
     *
     * @param string $scope The error scope.
     * @param mixed  $data  The data related to the error.
     * @param string $error The error message.
     */
    public function set_error( $scope = '', $data = '', $error = '' ) {

        // Get needed variables
        $gi_id = $this->gi_id;

        $this->errors[ $scope ] = $this->errors[ $scope ] ?? [];

        $message = '(' . Settings::get( 'id_prefix' ) . $gi_id . ') ' . $error;

        $this->errors[ $scope ][] = [
            'message' => $message,
            'data'    => $data,
        ];

        // Maybe log errors.
        if ( Settings::get( 'log_errors' ) ) {
            // @codingStandardsIgnoreStart
            error_log( 'Geniem Importer: ' . $error );
            // @codingStandardsIgnoreEnd
        }
    }

    /**
     * Returns all errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Encode an instance into JSON.
     *
     * @return array
     */
    public function to_json() {
        return wp_json_encode( get_object_vars( $this ) );
    }

    /**
     * Post constructor.
     *
     * @param string|null $gi_id The external API id.
     */
    public function __construct( $gi_id = null ) {
        if ( null === $gi_id ) {
            // @codingStandardsIgnoreStart
            $this->set_error( 'id', 'gi_id', __( 'A unique id must be set for the Post constructor.', 'geniem-importer' ) );
            // @codingStandardsIgnoreEnd
        } else {
            // Set the Importer id.
            $this->gi_id = $gi_id;
            // Fetch the WP post id, if it exists.
            $this->post_id = Api::get_post_id_by_api_id( $gi_id );
            if ( $this->post_id ) {
                // Fetch the existing WP post object.
                $this->post = get_post( $this->post_id );
                // Unset the time values to ensure updates.
                unset( $this->post->post_date );
                unset( $this->post->post_date_gmt );
                unset( $this->post->post_modified );
                unset( $this->post->post_modified_gmt );
            }
        }
    }

    /**
     * Handles a full importer object data setting.
     *
     * @param object $raw_post An object following the plugin specification.
     */
    public function set_data( $raw_post ) {
        $this->set_post( $raw_post->post );

        // Attachments
        if ( isset( $raw_post->attachments ) && is_array( $raw_post->attachments ) ) {
            $this->set_attachments( $raw_post->attachments );
        }

        // Post meta
        if ( isset( $raw_post->meta ) ) {
            $this->set_meta( $raw_post->meta );
        }

        // Taxonomies
        if ( isset( $raw_post->taxonomies ) && is_array( $raw_post->taxonomies ) ) {
            $this->set_taxonomies( $raw_post->taxonomies );
        }

        // Advanced custom fields
        if ( isset( $raw_post->acf ) && is_array( $raw_post->acf ) ) {
            $this->set_acf( $raw_post->acf );
        }

        // If post object has i18n object property set post language
        if ( isset( $raw_post->i18n ) && is_array( $raw_post->i18n ) ) {
            $this->set_i18n( $raw_post->i18n );
        }
    }

    /**
     * Sets the basic data of a post.
     *
     * @param  WP_Post|object $post_obj Post object.
     * @return WP_Post|object Post object.
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

        return $this->post;
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
            if ( false === $user ) {
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
            if ( 'trash' === $post_obj->post_status ) {
                // @codingStandardsIgnoreStart
                $err = __( 'Error in the "post_status" column. The post is currently trashed, please solve before importing.', 'geniem-importer' );
                // @codingStandardsIgnoreEnd
                $this->set_error( $err_scope, 'post_status', $err );
            } elseif ( ! array_key_exists( $post_obj->post_status, $post_statuses ) ) {
                // @codingStandardsIgnoreStart
                $err = __( 'Error in the "post_status" column. The value is not a valid post status.', 'geniem-importer' );
                // @codingStandardsIgnoreEnd
                $this->set_error( $err_scope, 'post_status', $err );
            }
        }

        // Validate the comment status.
        if ( isset( $post_obj->comment_status ) ) {
            $comment_statuses = [ 'hold', 'approve', 'spam', 'trash', 'open', 'closed' ];
            if ( ! in_array( $post_obj->comment_status, $comment_statuses, true ) ) {
                // @codingStandardsIgnoreStart
                $err = __( 'Error in the "comment_status" column. The value is not a valid comment status.', 'geniem-importer' );
                // @codingStandardsIgnoreEnd
                $this->set_error( $err_scope, 'comment_status', $err );
            }
        }

        // Validate the post parent.
        if ( isset( $post_obj->post_parent ) && $post_obj->post_parent !== 0 ) {
            $parent_id = Api::is_query_id( $post_obj->post_parent );
            if ( $parent_id !== false ) {
                // Check if parent exists.
                $parent_post_id = Api::get_post_id_by_api_id( $parent_id );
                if ( $parent_post_id === false ) {
                    // @codingStandardsIgnoreStart
                    $err = __( 'Error in the "post_parent" column. The queried post parent was not found.', 'geniem-importer' );
                    // @codingStandardsIgnoreEnd
                    $this->set_error( $err_scope, 'menu_order', $err );
                } else {
                    // Set parent post id.
                    $post_obj->post_parent = $parent_post_id;
                }
            } else {
                // The parent is a WP post id.
                if ( \get_post( $parent_id ) === null ) {
                    // @codingStandardsIgnoreStart
                    $err = __( 'Error in the "post_parent" column. The parent id did not match any post.', 'geniem-importer' );
                    // @codingStandardsIgnoreEnd
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
                // @codingStandardsIgnoreStart
                $err = __( 'Error in the "post_type" column. The value does not match a registered post type.', 'geniem-importer' );
                // @codingStandardsIgnoreEnd
                $this->set_error( $err_scope, 'post_type', $err );
            }
        }
    }

    /**
     * Validate a mysql datetime value.
     *
     * @param string $date_string The datetime string.
     * @param string $col_name    The posts table column name.
     * @param string $err_scope   The error scope name.
     */
    public function validate_date( $date_string = '', $col_name = '', $err_scope = '' ) {
        $valid = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
        if ( ! $valid ) {
            // @codingStandardsIgnoreStart
            $err = __( "Error in the \"$col_name\" column. The value is not a valid datetime string.", 'geniem-importer' );
            // @codingStandardsIgnoreEnd
            $this->set_error( $err_scope, $col_name, $err );
        }
    }


    /**
     * @todo doc / validation?
     * @param [type] $attachments [description]
     */
    public function set_attachments( $attachments ) {
        $this->attachments = $attachments;
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
            $this->meta[ $key ] = apply_filters( "geniem_importer_meta_value_{$key}", $value );
        }

        $this->validate_meta( $this->meta );
    }

    /**
     * Validate postmeta.
     *
     * @param array $meta Post meta.
     * @todo Validations and filters.
     */
    public function validate_meta( $meta ) {
        $errors = [];
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
            $this->taxonomies[ $key ] = apply_filters( "geniem_importer_taxonomy_{$key}", $value );
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
            // @codingStandardsIgnoreStart
            $err = __( "Error in the taxonomies. Taxonomies must be passed in an associative array.", 'geniem-importer' );
            // @codingStandardsIgnoreEnd
            $this->set_error( 'taxonomy', $taxonomy, $err );
            return;
        }

        // The passed taxonomies must be currently registered.
        $registered_taxonomies = \get_taxonomies();
        foreach ( $taxonomies as $term ) {
            if ( ! in_array( $term['taxonomy'], $registered_taxonomies, true ) ) {
                // @codingStandardsIgnoreStart
                $err = __( "Error in the \"{$term['taxonomy']}\" taxonomy. The taxonomy is not registerd.", 'geniem-importer' );
                // @codingStandardsIgnoreEnd
                $this->set_error( 'taxonomy', $taxonomy, $err );
            }
            apply_filters( 'geniem_importer_validate_taxonomies', $taxonomies );
        }
    }

    /**
     * Sets the post acf data.
     *
     * @param array $acf_data The acf data in an associative array.
     */
    public function set_acf( $acf_data = [] ) {
        // Force type to array.
        $this->acf = (array) $acf_data;
        // Filter values before validating.
        /* @todo filtering (by name, not $key?)
        foreach ( $this->acf as $key => $value ) {
            $this->acf[$key] = apply_filters( "geniem_importer_acf_value_{$key}", $value );
        }
        */
        $this->validate_acf( $this->acf );
    }

    /**
     * Validate acf.
     *
     * @param array $acf Post acf fields.
     * @todo Validations and filters.
     */
    public function validate_acf( $acf ) {
        $errors = [];
        if ( ! empty( $errors ) ) {
            $this->set_error( 'acf', $errors );
        }
    }

    /**
     * Sets the post localization data.
     *
     * @param array $i18n_data The polylang data in an associative array.
     */
    public function set_i18n( $i18n_data ) {
        $this->i18n = $i18n_data;
        $this->validate_i18n( $this->i18n );
    }

    /**
     * Validate the locale array.
     *
     * @param array $i18n The set pll data for the post.
     */
    public function validate_i18n( $i18n ) {

        // Check if the polylang plugin is activated.
        if ( Localization\Controller::get_activated_i18n_plugin( $this ) === false ) {
            return;
        }

        // Check if data is an array.
        if ( ! is_array( $i18n ) ) {
            // @codingStandardsIgnoreStart
            $err = __( 'Error in the i18n data. The locale data must be passed in an associative array.', 'geniem-importer' );
            // @codingStandardsIgnoreEnd
            $this->set_error( 'i18n', $i18n, $err );
            return;
        }

        // Check if locale is set and in the current installation.
        if ( ! isset( $i18n['locale'] ) ) {
            $err = __( 'Error in the polylang data. The locale is not set.', 'geniem-importer' );
            $this->set_error( 'i18n', $i18n, $err );
        } elseif ( ! in_array( $i18n['locale'], Polylang::language_list(), true ) ) {
            // @codingStandardsIgnoreStart
            $err = __( 'Error in the polylang data. The locale doesn\'t exist in the current WP installation', 'geniem-importer' );
            // @codingStandardsIgnoreEnd
            $this->set_error( 'i18n', $i18n, $err );
        }

        // If a master post is set for the current post, check its validity.
        if ( isset( $i18n['master'] ) ) {
            if ( Api::is_query_id( $i18n['master']['query_key'] ?? '' ) === false ) {
                $err = __( 'Error in the i18n data. The master query id is missing or invalid.', 'geniem-importer' );
                $this->set_error( 'i18n', $i18n, $err );
            }
        }

    }

    /**
     * Stores the post instance and all its data into the database.
     *
     * @throws PostException If the post data is not valid.
     *
     * @param boolean $force_save Force saving even if errors occurred.
     *
     * @return int Post id.
     */
    public function save( $force_save = false ) {

        // If this is not forced or a rollback save, check for errors before the saving process.
        if ( ! $force_save || ! $this->rollback_mode ) {
            $valid = $this->validate();
            if ( ! $valid ) {
                // Log this import.
                new Log( $this );

                throw new PostException(
                    __( 'The post data was not valid. The import was canceled.', 'geniem-importer' ),
                    0,
                    $this->errors
                );
            }
        }

        $post_arr = (array) $this->post;

        // Add filters for data modifications before and after importer related database actions.
        add_filter( 'wp_insert_post_data', [ $this, 'pre_post_save' ], 1 );
        add_filter( 'wp_insert_post', [ $this, 'after_post_save' ], 1 );

        // Run the WP save function.
        $post_id = wp_insert_post( $post_arr );

        // Identify the post, if not yet done.
        if ( empty( $this->post_id ) ) {
            $this->post_id = $post_id;
            $this->identify();
        }

        // Save attachments.
        if ( ! empty( $this->attachments ) ) {
            $this->save_attachments();
        }

        // Save metadata.
        if ( ! empty( $this->meta ) ) {
            $this->save_meta();
        }

        // Save taxonomies.
        if ( ! empty( $this->taxonomies ) ) {
            $this->save_taxonomies();
        }

        // Save acf data.
        if ( ! empty( $this->acf ) ) {
            $this->save_acf();
        }

        // Save localization data.
        if ( ! empty( $this->i18n ) ) {
            Localization\Controller::save_locale( $this );
        }

        // If this is not forced or a rollback save, check for errors after save process.
        if ( ! $force_save || ! $this->rollback_mode ) {
            $valid = $this->validate();
            if ( ! $valid ) {
                // Log this import.
                new Log( $this );

                $rolled_back = $this->rollback();

                // Set the correct error message.
                $err = $rolled_back ?
                    // Rollback error message
                    __( 'An error occurred while saving the import data. Rolled back the last successful import.', 'geniem-importer' ) :
                    // Default error message
                    __( 'An error occurred while saving the import data. Set the post status to "draft".', 'geniem-importer' );

                throw new PostException(
                    $err,
                    0,
                    $this->errors
                );
            }
        }

        // This logs a successful import.
        new Log( $this );

        // Remove the custom filters.
        remove_filter( 'wp_insert_post_data', [ $this, 'pre_post_save' ] );
        remove_filter( 'wp_insert_post', [ $this, 'after_post_save' ] );

        return $post_id;
    }

    /**
     * Delete all data related to a single post.
     * Note: This keeps the basic post data intact int the posts table.
     */
    public function delete_data() {

        // This removes most of data related to a post.
        Api::delete_post_meta_data( $this->post_id );

        // Delete all term relationships.
        \wp_delete_object_term_relationships( $this->post_id, \get_taxonomies() );

        // Run custom action for custom data.
        // Use this if the data is not in the postmeta table.
        do_action( 'geniem_importer_delete_data', $this->post_id );
    }

    /**
     * Saves the attachments of the post.
     * Currently supports images.
     *
     * @todo add support for other media formats too
     */
    protected function save_attachments() {
        // All of the following are required for the media_sideload_image function.
        if ( ! function_exists( '\media_sideload_image' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }
        if ( ! function_exists( '\download_url' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        if ( ! function_exists( '\wp_read_image_metadata' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        $attachment_prefix      = Settings::get( 'attachment_prefix' );
        $attachment_language    = Api::get_prop( $this->i18n, 'locale' );

        foreach ( $this->attachments as &$attachment ) {

            $attachment_id      = Api::get_prop( $attachment, 'id' );
            $attachment_src     = Api::get_prop( $attachment, 'src' );
            $attachment_post_id = Api::get_attachment_post_id_by_attachment_id( $attachment_id );

            if ( empty( $attachment_src ) || empty( $attachment_id ) ) {
                // @codingStandardsIgnoreStart
                $this->set_error( 'attachment', $attachment, __( 'The attachment object has missing parameters.', 'geniem_importer' ) );
                // @codingStandardsIgnoreEnd
                continue;
            }

            // Check if attachment doesn't exists, and upload it.
            if ( ! $attachment_post_id ) {

                // Insert upload attachment from url
                $attachment_post_id = $this->insert_attachment_from_url( $attachment_src, $attachment, $this->post_id );

                // Something went wrong.
                if ( is_wp_error( $attachment_post_id ) ) {
                    // @codingStandardsIgnoreStart
                    $this->set_error( 'attachment', $attachment, __( 'An error occurred uploading the file.', 'geniem_importer' ) );
                    // @codingStandardsIgnoreEnd
                }

                if ( $attachment_post_id ) {
                    // Set indexed meta for fast queries.
                    // Depending on the attachment prefix this would look something like:
                    // meta_key             | meta_value
                    // gi_attachment_{1234} | 1234
                    update_post_meta( $attachment_post_id, $attachment_prefix . $attachment_id, $attachment_id );
                    // Set the generally queryable id.
                    // Depending on the attachment prefix this would look something like:
                    // meta_key       | meta_value
                    // gi_attachment  | 1234
                    update_post_meta( $attachment_post_id, rtrim( $attachment_prefix, '_' ), $attachment_id );

                    // Polylang mananages languages.
                    // @todo, I think this is set automatically
                    if ( Polylang::pll() ) {
                        $attachment_language = Api::get_prop( $this->i18n, 'locale' );

                        if ( $attachment_language ) {
                            Polylang::set_attachment_language( $attachment_post_id, $attachment_language );
                        }
                    }
                } // End if().
            } // End if().

            // Update attachment meta and handle translations
            if ( $attachment_post_id ) {

                // Get attachment translations.
                if ( Polylang::pll() ) {
                    $attachment_post_id = Polylang::get_attachment_by_language(
                        $attachment_post_id,
                        $attachment_language
                    );
                }

                // Update attachment info.
                $attachment_args = [
                    'ID'           => $attachment_post_id,
                    'post_title'   => Api::get_prop( $attachment, 'title' ),
                    'post_content' => Api::get_prop( $attachment, 'description' ),
                    'post_excerpt' => Api::get_prop( $attachment, 'caption' ),
                ];

                // Save the attachement post object data
                wp_update_post( $attachment_args );

                // Use caption as an alternative text.
                $alt_text = Api::get_prop( $attachment, 'caption' );

                if ( $alt_text ) {
                    // Save image alt text into attachment post meta
                    update_post_meta( $attachment_post_id, '_wp_attachment_image_alt', $alt_text );
                }

                // Set the attachment post_id.
                $this->attachment_ids[ $attachment_prefix . $attachment_id ] =
                    Api::set_prop( $attachment, 'post_id', $attachment_post_id );
            } // End if().
        } // End foreach().

        // This functions is done.
        $this->set_save_state( 'attachments' );
    }

    /**
     * Insert an attachment from an URL address.
     *
     * @param string $attachment_src Source file url.
     * @param object $attachment     Post class instances attachment.
     * @param int    $post_id        Attachments may be associated with a parent post or page.
     *                               Specify the parent's post ID, or 0 if unattached.
     *
     * @return int   $attachment_id
     */
    protected function insert_attachment_from_url( $attachment_src, $attachment, $post_id ) {

        // Get filename from the url.
        $file_name                  = basename( $attachment_src );
        // Exif related variables
        $exif_imagetype             = exif_imagetype( $attachment_src );
        $exif_supported_imagetypes  = array(
            IMAGETYPE_JPEG,
            IMAGETYPE_TIFF_II,
            IMAGETYPE_TIFF_MM
        );

        // Construct file local url.
        $tmp_folder                 = Settings::get( 'tmp_folder' );
        $local_image                = $tmp_folder . $file_name;

        // Copy file to local image location
        copy( $attachment_src, $local_image );

        // If exif_read_data is callable and file type could contain exif data.
        if ( is_callable( 'exif_read_data' ) && in_array( $exif_imagetype, $exif_supported_imagetypes ) ) {
            // Manipulate image exif data to prevent.
            $this->strip_unsupported_exif_data( $local_image );
        }

        // Get file from local temp folder.
        $file_content = file_get_contents( $local_image );

        // Upload file to uploads.
        $upload = wp_upload_bits( $file_name, null, $file_content );

        // After upload process we are free to delete the tmp image.
        unlink( $local_image );

        // If error occured during upload return false.
        if ( ! empty( $upload['error'] ) ) {
            return false;
        }

        // File variables
        $file_path          = $upload['file'];
        $file_type          = wp_check_filetype( $file_name, null );
        $wp_upload_dir      = wp_upload_dir();

        // wp_insert_attachment post info
        $post_info = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => Api::get_prop( $attachment, 'title' ),
            'post_content'   => Api::get_prop( $attachment, 'description' ),
            'post_excerpt'   => Api::get_prop( $attachment, 'caption' ),
            'post_status'    => 'inherit',
        );

        // Insert attachment to the database.
        $attachment_id = wp_insert_attachment( $post_info, $file_path, $post_id, true );

        // Generate post thumbnail attachment meta data.
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );

        // Assign metadata to an attachment.
        wp_update_attachment_metadata( $attachment_id, $attachment_data );

        return $attachment_id;
    }

    /**
     * If exif_read_data() fails, remove exif data from the image file
     * to prevent errors in WordPress core.
     *
     * @param string $local_image       Local url for an image.
     * @return void No return.
     */
    protected function strip_unsupported_exif_data( $local_image ) {

        // Variable for exif data errors in PHP
        $php_exif_data_error_exists = false;

        // Check for PHP exif_read_data function errors!
        try {
            exif_read_data( $local_image );
        } catch ( \Exception $e ) {
            $php_exif_data_error_exists = true;
        }

        // If image magic is installed and exif_data_error exists
        if ( class_exists( 'Imagick' ) && $php_exif_data_error_exists === true ) {

            // Run image through image magick
            $imagick_object = new \Imagick( realpath( $local_image ) );

            // Strip off all exif data to prevent PHP 5.6 and PHP 7.0 exif errors!
            $imagick_object->stripImage();

            // Write manipulated file to the tmp folder
            $imagick_file   = $imagick_object->writeImage( $local_image );
        }
    }

    /**
     * Saves the metadata of the post.
     *
     * @return void
     */
    protected function save_meta() {
        if ( is_array( $this->meta ) ) {
            foreach ( $this->meta as $key => $value ) {

                // Check if post has a attachment thumbnail
                if ( $key === '_thumbnail_id' ) {
                    // First check if attachments have been saved.
                    // If not, set an error and skip thumbnail setting.
                    if ( ! $this->is_saved( 'attachments' ) ) {
                        // @codingStandardsIgnoreStart
                        $err = __( 'Attachments must be saved before saving the thumbnail id for a post. Discarding saving meta for the key "_thumbnail_id".', 'geniem-importer' );
                        // @codingStandardsIgnoreEnd
                        $this->set_error( 'meta', $key, $err );
                        continue;
                    }

                    // If attachment id exists
                    $attachment_prefix  = Settings::get('attachment_prefix');
                    $attachment_post_id = $this->attachment_ids[  $attachment_prefix  . $value ] ?? '';

                    // If not empty set _thumbnail_id
                    if ( ! empty( $attachment_post_id ) ) {
                        $value = $attachment_post_id;
                    }
                    // Set error: attachment did not exist.
                    else {
                        // @codingStandardsIgnoreStart
                        $this->set_error( 'meta', $key, __( 'Can not save the thumbnail data. The attachment was not found.', 'geniem_importer' ) );
                        // @codingStandardsIgnoreEnd
                        unset( $this->meta[ $key ] );
                        continue;
                    }
                }

                // Update post meta
                update_post_meta( $this->post_id, $key, $value );
            }
        }

        // This functions is done.
        $this->set_save_state( 'meta' );
    }

    /**
     * Sets the terms of a post and create taxonomy terms
     * if they do not exist yet.
     *
     * @todo make similar to acf taxonomies
     */
    protected function save_taxonomies() {
        if ( is_array( $this->taxonomies ) ) {
            $term_ids_by_tax = [];
            foreach ( $this->taxonomies as &$term ) {
                // Safely get values from the term.
                $slug     = Api::get_prop( $term, 'slug' );
                $taxonomy = Api::get_prop( $term, 'taxonomy' );

                // Fetch the term object.
                $term_obj = get_term_by( 'slug', $slug, $taxonomy );

                // If the term does not exist, create it.
                if ( ! $term_obj ) {
                    $term_obj = Api::create_new_term( $term, $this );
                    // @todo check for wp error and continue, edit for ACF taxonomies with similar code
                }
                // Add term id.
                if ( isset( $term_ids_by_tax[ $taxonomy ] ) ) {
                    $term_ids_by_tax[ $taxonomy ][] = $term_obj->term_id;
                } else {
                    $term_ids_by_tax[ $taxonomy ] = [ $term_obj->term_id ];
                }
            }
            foreach ( $term_ids_by_tax as $taxonomy => $terms ) {
                // Set terms for the post object.
                wp_set_object_terms( $this->post_id, $terms, $taxonomy );
            }
        }

        // This functions is done.
        $this->set_save_state( 'taxonomies' );
    }

    /**
     * Saves the acf data of the post.
     */
    protected function save_acf() {

        // If ACF is activated
        if ( function_exists( 'get_field' ) ) {

            if ( is_array( $this->acf ) ) {

                foreach ( $this->acf as $acf_row ) {
                    // The key must be set.
                    if ( empty( Api::get_prop( $acf_row, 'key', '' ) ) ) {
                        continue;
                    }

                    $type  = Api::get_prop( $acf_row, 'type', 'default' );
                    $key   = Api::get_prop( $acf_row, 'key', '' );
                    $value = Api::get_prop( $acf_row, 'value', '' );

                    switch ( $type ) {
                        case 'taxonomy':
                            $terms = [];
                            foreach ( $value as &$term ) {
                                $term_slug = Api::get_prop( $term, 'slug' );
                                $term_taxonomy = Api::get_prop( $term, 'taxonomy' );
                                $term_obj = \get_term_by( 'slug', $term_slug, $term_taxonomy );
                                // If the term does not exist, create it.
                                if ( ! $term_obj ) {
                                    $term_obj = Api::create_new_term( $term, $this );
                                }
                                $terms[] = (int) $term_obj->term_id;
                            }
                            if ( count( $terms ) ) {
                                update_field( $key, $terms, $this->post_id );
                            }
                            break;

                        case 'image':
                            // Check if image exists.
                            $attachment_post_id = $this->attachment_ids[ $value ];
                            if ( ! empty( $attachment_post_id ) ) {
                                update_field( $key, $attachment_post_id, $this->post_id );
                            } else {
                                $err = __( 'Trying to set an image in an ACF field that does not exists.', 'geniem-importer' );
                                $this->set_error( 'acf', 'image_field', $err );
                            }
                            break;

                        // @todo Test which field types require no extra logic.
                        // Currently tested: 'select'
                        default:
                            update_field( $key, $value, $this->post_id );
                            break;
                    }
                } // End foreach().
            } // End if().
        } // End if().
        else {
            // @codingStandardsIgnoreStart
            $this->set_error( 'acf', $this->acf, __( 'Advanced Custom Fields is not active! Please install and activate the plugin to save acf meta fields.', 'geniem_importer' ) );
            // @codingStandardsIgnoreEnd
        }

        // This functions is done.
        $this->set_save_state( 'acf' );
    }

    /**
     * Adds postmeta rows for matching a WP post with an external source.
     */
    protected function identify() {
        $id_prefix = Settings::get( 'id_prefix' );

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
     * @throws PostException If the post data is not valid, an PostException error is thrown.
     */
    protected function validate() {
        if ( ! empty( $this->errors ) ) {
            return false;
        }
        else {
            return true;
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
    public function pre_post_save( $post_data ) {
        // If this instance has time values, set them here and override WP automation.
        if ( isset( $this->post->post_date ) &&
             $this->post->post_date !== '0000-00-00 00:00:00'
        ) {
            $post_data['post_date']         = $this->post->post_date;
            $post_data['post_date_gmt']     = \get_gmt_from_date( $this->post->post_date );
        }
        if ( isset( $this->post->post_modified ) &&
             $this->post->post_modified !== '0000-00-00 00:00:00'
        ) {
            $post_data['post_modified']     = $this->post->post_modified;
            $post_data['post_modified_gmt'] = \get_gmt_from_date( $this->post->post_modified );
        }

        return apply_filters( 'geniem_importer_pre_post_save', $post_data, $this->gi_id );
    }

    /**
     * This function creates a filter for the 'wp_insert_posts_data'
     * Use this to customize the imported data after any database actions.
     *
     * @param array $postarr Post data array.
     * @return array
     */
    public function after_post_save( $postarr ) {
        return apply_filters( 'geniem_importer_after_post_save', $postarr, $this->gi_id );
    }

    /**
     * Restores a post's state back to the last successful import.
     *
     * @return boolean Did we roll back or not?
     */
    protected function rollback() {
        // Set the rollback mode.
        $this->rollback_mode = true;

        $last_import = Log::get_last_successful_import( $this->post_id );

        if ( $last_import &&
             Settings::get( 'rollback_disable' ) !== true ) {
            // First delete all imported data.
            $this->delete_data();

            // Save the previous import again.
            $data = $last_import->get_data();
            $this->set_data( $data );
            $this->save();

            $this->rollback_mode = false;

            return true;
        }
        else {
            // Set post status to 'draft' to hide posts containing errors.
            $update_status = [
                'ID'          => $this->post_id,
                'post_status' => 'draft',
            ];

            // Update the status into the database
            wp_update_post( $update_status );

            return false;
        }
    }

}

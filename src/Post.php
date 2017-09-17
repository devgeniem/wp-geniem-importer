<?php
/**
 * The Post class is used to import posts into WordPres.
 */

namespace Geniem\Importer;

use Geniem\Importer\Exception\PostException as PostException;
use Geniem\Importer\Localization\Polylang as Polylang;
use WP_Post;
use WP_Error;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

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
    protected $rollback = false;

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

        $message = '(' . Settings::get( 'GI_ID_PREFIX' ) . $gi_id . ') ' . $error;

        $this->errors[ $scope ][] = [
            'message' => $message,
            'data'    => $data,
        ];

        // Maybe log errors.
        if ( Settings::get( 'GI_LOG_ERRORS' ) ) {
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
            }
        }
    }

    /**
     * Handles a full importer object data setting.
     *
     * @param object $raw_post An object following the plugin specification.
     */
    public function set_data( $raw_post ) {
        $this->set_post( (object) [
            'post_type'    => $raw_post['post_type'] ?? 'post',
            'post_title'   => $raw_post['post_title'] ?? '',
            'post_content' => $raw_post['post_content'] ?? '',
            'post_excerpt' => $raw_post['post_excerpt'] ?? '',
        ] );

        // Attachments
        if ( isset( $raw_post['attachments'] ) && is_array( $raw_post['attachments'] ) ) {
            $this->set_attachments( $raw_post['attachments'] );
        }

        // Post meta
        if ( isset( $raw_post['meta'] ) ) {
            $this->set_meta( $raw_post['meta'] );
        }

        // Taxonomies
        if ( isset( $raw_post['taxonomies'] ) && is_array( $raw_post['taxonomies'] ) ) {
            $this->set_taxonomies( $raw_post['taxonomies'] );
        }

        // Advanced custom fields
        if ( isset( $raw_post['acf'] ) && is_array( $raw_post['acf'] ) ) {
            $this->set_acf( $raw_post['acf'] );
        }

        // If post object has i18n object property set post language
        if ( isset( $raw_post['i18n'] ) && is_array( $raw_post['i18n'] ) ) {
            $this->set_i18n( $raw_post['i18n'] );
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
     * @return int Post id.
     */
    public function save() {

        // Check for errors before the saving process.
        if ( ! $this->rollback ) {
            $valid = $this->validate();
            if ( ! $valid ) {
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

        // Save pll data.
        if ( ! empty( $this->i18n ) ) {
            // @todo separate save_pll to own class
            Localization\Controller::save_locale( $this );
        }

        // Check for errors after save process.
        if ( ! $this->rollback ) {
            // Log this import.
            Log::add( $this );

            $valid = $this->validate();
            if ( ! $valid ) {
                $this->rollback();
                throw new PostException(
                    __( 'The post data was not valid. The import was canceled.', 'geniem-importer' ),
                    0,
                    $this->errors
                );
            }
        }

        // Remove the custom filters.
        remove_filter( 'wp_insert_post_data', [ $this, 'pre_post_save' ] );
        remove_filter( 'wp_insert_post', [ $this, 'after_post_save' ] );

        return $post_id;
    }

    /**
     * Saves the attachments of the post.
     *
     * @todo currently only accepts images
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

        $attachment_prefix      = Settings::get( 'GI_ATTACHMENT_PREFIX' );
        $attachment_language    = Api::get_prop( $this->i18n, 'locale' );

        foreach ( $this->attachments as &$attachment ) {

            $attachment_id      = Api::get_prop( $attachment, 'id' );
            $attachment_src     = Api::get_prop( $attachment, 'src' );
            $attachment_post_id = Api::get_attachment_post_id_by_attachment_id( $attachment_id );

            if ( empty( $attachment_src ) || empty( $attachment_id ) ) {
                continue;
            }

            // Check if attachment doesn't exists, and upload it.
            if ( ! $attachment_post_id ) {

                // Insert upload attachment from url
                $attachment_post_id = $this->insert_attachment_from_url( $attachment_src, $attachment, $this->post_id );

                // Something went wrong.
                if ( is_wp_error( $attachment_post_id ) ) {
                    // @codingStandardsIgnoreStart
                    $this->set_error( 'attachment', $name, __( 'An error occurred uploading the file.', 'geniem_importer' ) );
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
            // @todo check the translation flow and
            // move attachment translation related stuff to Polylang/WPML classes
            if ( $attachment_post_id ) {

                // Get attachment translations.
                if ( Polylang::pll() ) {
                    $attachment_post_id = Polylang::get_attachment_by_language( $attachment_post_id, $attachment_language );
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

                // Set attachment post_id.
                $this->attachment_ids[ $attachment_prefix . $attachment_id ] = Api::set_prop( $attachment, 'post_id', $attachment_post_id );
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

        // Get file from url
        $http_object = wp_remote_get( $attachment_src );

        // Something went wrong.
        if ( is_wp_error( $http_object ) ) {
            return $http_object;
        }

        $wub_name       = basename( $attachment_src );
        $file_content   = $http_object['body'];

        // Upload file to uploads.
        $upload = wp_upload_bits( $wub_name, null, $file_content );

        // If error occured during upload return false.
        if ( ! empty( $upload['error'] ) ) {
            return false;
        }

        // File variables
        $file_path          = $upload['file'];
        $file_name          = basename( $file_path );
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
                    if ( ! $this->is_saved( 'meta' ) ) {
                        // @codingStandardsIgnoreStart
                        $err = __( 'Attachments must be saved before saving the thumbnail id for a post. Discarding saving meta for the key "_thumbnail_id".', 'geniem-importer' );
                        // @codingStandardsIgnoreEnd
                        $this->set_error( 'meta', $key, $err );
                        continue;
                    }

                    // If attachment id exists
                    $attachment_post_id = $this->attachment_ids[ $value ] ?? '';

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
                $term_obj = get_term_by( 'slug', $term['slug'], $term['taxonomy'] );
                // If the term does not exist, create it.
                if ( ! $term_obj ) {
                    $term_obj = Api::create_new_term( $term, $this );
                    // @todo check for wp error and continue, edit for ACF taxonomies with similar code
                }
                // Add term id.
                if ( isset( $term_ids_by_tax[ $term['taxonomy'] ] ) ) {
                    $term_ids_by_tax[ $term['taxonomy'] ][] = $term_obj->term_id;
                } else {
                    $term_ids_by_tax[ $term['taxonomy'] ] = [ $term_obj->term_id ];
                }
            }
            foreach ( $term_ids_by_tax as $taxonomy => $terms ) {
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

                foreach ( $this->acf as $acf_arr ) {
                    // Key must be set.
                    if ( empty( $acf_arr['key'] ) ) {
                        continue;
                    }

                    switch ( isset( $acf_arr['type'] ) ? $acf_arr['type'] : 'default' ) {
                        case 'taxonomy':
                            $terms = [];
                            foreach ( $acf_arr['value'] as &$term ) {
                                $term_obj = \get_term_by( 'slug', $term['slug'], $term['taxonomy'] );
                                // If the term does not exist, create it.
                                if ( ! $term_obj ) {
                                    $term_obj = Api::create_new_term( $term, $this );
                                }
                                $terms[] = (int) $term_obj->term_id;
                            }
                            if ( count( $terms ) ) {
                                update_field( $acf_arr['key'], $terms, $this->post_id );
                            }
                            break;

                        case 'image':
                            // Check if image exists.
                            $attachment_post_id = $this->attachment_ids[ $acf_arr['value'] ];
                            if ( ! empty( $attachment_post_id ) ) {
                                update_field( $acf_arr['key'], $attachment_post_id, $this->post_id );
                            } else {
                                $err = "Image doesn't exists";
                                $this->set_error( 'acf', 'image_field', $err );
                            }
                            break;

                        // @todo Test which field type require no extra logic.
                        // Currently tested: 'select'
                        default:
                            update_field( $acf_arr['key'], $acf_arr['value'], $this->post_id );
                            break;
                    }
                } // End foreach().
            } // End if().
        } // End if().
        else {
            // @codingStandardsIgnoreStart
            $this->set_error( 'acf', $name, __( 'Advanced Custom Fields is not active! Please install and activate the plugin to save acf meta fields.', 'geniem_importer' ) );
            // @codingStandardsIgnoreEnd
        }

        // This functions is done.
        $this->set_save_state( 'acf' );
    }

    /**
     * Create new term.
     *
     * @param  array $term Term data.
     *
     * @return array|WP_Error An array containing the `term_id` and `term_taxonomy_id`,
     *                        WP_Error otherwise.
     * @todo  make static and move to api
     */
    protected function create_new_term( array $term ) {
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
            $this->set_error( 'taxonomy', $name, __( 'An error occurred creating the taxonomy term.', 'geniem_importer' ) );
            // @codingStandardsIgnoreEnd
        }

        return (object) $result;
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
        // @todo apply dates if set
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
     */
    protected function rollback() {
        // Set the rollback mode.
        $this->rollback = true;

        $last_import = Log::get_last_successful_import( $this->post_id );

        if ( $last_import ) {
            // @TODO: Delete all data first and rollback correct dates.
            // @TODO: ^ do this also for pll duplicates.
            // @TODO: run $this->set_data() and then save
        }
        else {
            // @TODO: set post status to 'draft'.
        }
    }

}

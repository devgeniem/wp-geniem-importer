<?php
/**
 * The Log class file.
 */

namespace Geniem\Importer;

/**
 * Class Log
 *
 * This class controls logging into the custom database table.
 *
 * @package Geniem\Importer
 */
class Log {

    /**
     * Log id.
     *
     * @var int
     */
    protected $id;

    /**
     * The importer id of the logged item.
     *
     * @var string
     */
    protected $gi_id;

    /**
     * The WP post id of the logged item.
     *
     * @var int
     */
    protected $post_id;

    /**
     * The gmt timestamp of the log.
     *
     * @var string
     */
    protected $import_date_gmt;

    /**
     * Importer post data.
     *
     * @var object|string
     */
    protected $data;

    /**
     * Import status.
     *
     * @var string
     */
    protected $status;

    /**
     * Get the id.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the importer id.
     *
     * @return string
     */
    public function get_gi_id() {
        return $this->gi_id;
    }

    /**
     * Get the WP post id.
     *
     * @return int
     */
    public function get_post_id() {
        return $this->post_id;
    }

    /**
     * Get the log timestamp.
     *
     * @return mixed
     */
    public function get_import_date_gmt() {
        return $this->import_date_gmt;
    }

    /**
     * Get importer post data.
     *
     * @return object
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get import status.
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Log constructor.
     *
     * An instance can be made out of a Importer Post object or from a log entry data.
     * If the $data is a Post instance, a new log entry is saved automatically.
     *
     * @param mixed $data The data from which the log instance is parsed.
     */
    public function __construct( $data ) {
        // This is an importer Post object. Save the log entry.
        if ( $data instanceof Post ) {
            // Get status texts.
            $ok_status   = Settings::get( 'GI_LOG_STATUS_OK' );
            $fail_status = Settings::get( 'GI_LOG_STATUS_FAIL' );

            // Data for the log entry.
            $this->gi_id           = $data->get_gi_id();
            $this->post_id         = $data->get_post_id();
            $this->import_date_gmt = \current_time( 'mysql', true );
            $this->data            = $data->to_json();
            $this->status          = empty( $data->get_errors() ) ? $ok_status : $fail_status;

            $this->save();
        }
        // This is fetch.
        else {
            $this->gi_id           = isset( $data->gi_id ) ? $data->gi_id : null;
            $this->post_id         = isset( $data->post_id ) ? (int) $data->post_id : null;
            $this->import_date_gmt = isset( $data->import_date_gmt ) ? $data->import_date_gmt : null;
            $this->data            = isset( $data->data ) ? $data->data: null;
            $this->status          = isset( $data->status ) ? $data->status : null;

            // Data might not be decoded yet.
            $this->data = Api::is_json( $this->data ) ? json_decode( $this->data ) : $this->data;
        }
    }

    /**
     * Save the log entry into the database.
     */
    public function save() {
        global $wpdb;

        // Insert into database.
        $table = $wpdb->prefix . Settings::get( 'TABLE_NAME' );
        $wpdb->insert(
            $table,
            [
                'gi_id'           => $this->gi_id,
                'post_id'         => $this->post_id,
                'import_date_gmt' => $this->import_date_gmt,
                'data'            => $this->data,
                'status'          => $this->status,
            ],
            [
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Fetches the last successful import from the database for a given post id.
     *
     * @param integer $post_id A WP post id.
     *
     * @return Log
     */
    public static function get_last_successful_import( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . Settings::get( 'TABLE_NAME' );
        $ok_status  = Settings::get( 'GI_LOG_STATUS_OK' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "
            SELECT * FROM $table_name
            WHERE post_id = %d
            AND status = %s
            ORDER BY import_date_gmt DESC;
            ",
            $post_id,
            $ok_status
        ) );

        if ( $row !== null ) {
            // Make things visible and help IDEs to interpret the object.
            return new Log( $row );
        }
        else {
            return false;
        }
    }

}
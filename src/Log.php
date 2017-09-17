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
     * Adds a log entry.
     *
     * @param Post $post The imported post object.
     */
    public static function add( $post ) {
        global $wpdb;

        // Get status texts.
        $ok_status   = Settings::get( 'GI_LOG_STATUS_OK' );
        $fail_status = Settings::get( 'GI_LOG_STATUS_FAIL' );

        // Data for the log entry.
        $gi_id         = $post->get_gi_id();
        $post_id       = $post->get_post_id();
        $post_date_gmt = \get_post_time( 'Y-m-d H:i:s', true, $post_id, false );
        $status        = empty( $post->get_errors() ) ? $ok_status : $fail_status;

        // Insert into database.
        $wpdb->insert( $wpdb->prefix . Settings::get( 'TABLE_NAME' ), [
                'gi_id'         => $gi_id,
                'post_id'       => $post_id,
                'post_date_gmt' => $post_date_gmt,
                'data'          => $post->to_json(),
                'status'        => $status,
            ]
        );
    }

    /**
     * Fetches the last successful import from the database for a given post id.
     *
     * @param integer $post_id A WP post id.
     *
     * @return bool
     */
    public static function get_last_successful_import( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . Settings::get( 'TABLE_NAME' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "
            SELECT * FROM %s
            WHERE post_id = %d 
            ORDER BY post_date_gmt DESC,
            ",
            $table_name,
            $post_id
        ) );

        if ( $row !== null ) {
            return $row[0];
        }
        else {
            return false;
        }
    }

}
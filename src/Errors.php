<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Errors
 *
 * @package Geniem\Importer
 */
class Errors {

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
    protected static $errors = [];

    /**
     * Sets a single error message or a full error array depending on the $key value.
     *
     * @param string       $post  The current importer element.
     * @param string       $scope The error scope.
     * @param string|array $data  The key or the error array.
     * @param string       $error The error message.
     */
    public static function set( $post, $scope = '', $data = '', $error = '' ) {

        // Get needed variables
        $gi_id = $post->get_gi_id();

        self::$errors[ $scope ] = self::$errors[ $scope ] ?? [];

        $message = '(' . Settings::get( 'GI_ID_PREFIX' ) . $gi_id . ') ' . $error;

        self::$errors[ $scope ][] = [
            'message' => $message,
            'data'    => $data,
        ];

        // Maybe log errors.
        if ( Settings::get( 'GI_LOG_ERRORS' ) ) {
            error_log( 'Geniem Importer: ' . $error );
        }
    }

    /**
     * Returns all errors.
     *
     * @return array
     */
    public static function get_all() {
        return self::$errors;
    }
}






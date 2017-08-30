<?php
/**
 * This class extends the PHP Exception class with the ability
 * to store multiple errors when validating a post.
 */

namespace Geniem\Importer\Exception;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class PostException
 *
 * @package Geniem\Importer\Exception
 */
class PostException extends \Exception {

    /**
     * Holds errors found in validating the post.
     *
     * @var array
     */
    protected $errors;

    /**
     * Get the errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * PostException constructor.
     *
     * @param string $message The error message to be thrown thrown.
     * @param int    $code    An error code to be thrown.
     * @param array  $errors  An array of error messages.
     *
     * @throws PostException Throws the current error instance.
     */
    public function __construct( $message = '', $code = 0, $errors = [] ) {

        // Set errors.
        $this->errors = $errors;

        // Construct the base.
        parent::__construct( $message, $code );
    }
}

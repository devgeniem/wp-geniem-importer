<?php
/**
 * An example of post importing.
 */

$data = new stdClass();

// Set the basic post data as an associative array and cast it to object.
$data->post = (object) [
    'post_title'   => 'The post title',
    'post_content' => 'The post main content HTML.',
    'post_excerpt' => 'The excerpt text of the post.',
];

// The array of attachments.
$data->attachments = [
    [
        'filename'      => '123456.jpg',
        'mime_type'     => 'image/jpg',
        'id'            => '123456',
        'alt'           => 'Alt text is stored in postmeta.',
        'caption'       => 'This is the post excerpt.',
        'description'   => 'This is the post content.',
        'src'           => 'http://upload-from-here.com/123456.jpg',
    ],
];

// Postmeta data as key-value pairs.
$data->meta = [
    'key1'          => 'value1',
    '_thumbnail_id' => 'gi_attachment_123456',
];

// Advanced custom fields data.
$data->acf = [
    'name'  => 'repeater_field_key',
    'value' => [
        [
            'name'  => 'repeater_value_1',
            'value' => '...',
        ],
        [
            'name'  => 'repeater_value_2',
            'value' => '...',
        ],
    ],
    [
        'name'  => 'single_field_key',
        'value' => '...',
    ],
    [
        'name'  => 'attachment_field_key',
        'value' => 'gi_attachment_123456',
    ],
];

// Localization data.
$data->i18n = [
    'locale' => 'en',
    'master' => 'gi_id_56',
];

// Create a new instance by a unique id.
$api_id = 'my_custom_id_1234';
$post = new \Geniem\Importer\Post( $api_id );

// Set all the data for the post.
$post->set_data( $data );

// Try to save the post.
try {
    // If the data was invalid or errors occur while saving the post into the dabase, an exception is thrown.
    $post->save();
} catch ( \Geniem\Importer\Exception\PostException $e ) {
    foreach ( $e->get_errors() as $scope => $errors ) {
        foreach ( $errors as $key => $message ) {
            error_log( "Importer error in $scope: " . $message );
        }
    }
}

<?php
/**
 * An example of post importing.
 */

$post_obj = new stdClass();

$post_obj->post_title   = 'The post title';
$post_obj->post_content = 'The post main content HTML.';
$post_obj->post_excerpt = 'The excerpt text of the post.';

$attachments = [
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

$postmeta = [
    [
        'meta_key'      => '_thumbnail_id',
        'meta_key'      => '_thumbnail_id',
        'meta_value'    => 'gi_attachment_123456',
    ],
];

$advanced_custom_fields = [
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

$polylang_data = [
    'locale' => 'en',
    'master' => 'gi_id_56',
];

$api_id = 'my_custom_id_123';
$post   = new \Geniem\Importer\Post( $api_id );

$post->set_post( $post_obj );
$post->set_attachments( $attachments );
$post->set_meta( $postmeta );
$post->set_acf( $advanced_custom_fields );
$post->set_pll( $polylang_data );

try {
    $post->save();
} catch ( PostException $e ) {
    foreach ( $e->get_errors() as $scope => $errors ) {
        foreach ( $errors as $key => $message ) {
            error_log( "Importer error in $scope: " . $message );
        }
    }
}

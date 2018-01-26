![geniem-github-banner](https://cloud.githubusercontent.com/assets/5691777/14319886/9ae46166-fc1b-11e5-9630-d60aa3dc4f9e.png)

# Geniem WordPress Importer

Geniem Importer is a WordPress importer plugin enabling importing WordPress data from external sources through an object-oriented functional API.

***The plugin is in development stage and available only as a private repository for Geniem developers!***

## Installation

Install the plugin with Composer by first adding the private repository and then requiring the package:

```
composer config repositories.devgeniem/wp-geniem-importer git git@github.com:devgeniem/wp-geniem-importer.git
composer require devgeniem/wp-geniem-importer
```
Then activate the plugin from the WordPress dashboard or with the WP-CLI.

```
wp plugin activate wp-geniem-importer
```

## Importing post objects

The plugin provides a functional API for importing various data types. You can think of the importer as an integration layer between your custom data source and the WordPress database. Your job is to format the data to meet the importer object specification. If you are lucky and your external data source can provide the data in the importer data format, your job is as simple as decoding the data into a PHP object and then passing it through the API functions.

An import is a two step process. First you must set the data for the importer `\Geniem\Importer\Post` object instance and then you call its save function to store the object data into the WordPress database.

An example of importing a single post can be found [here](docs/examples/example-post.php).

### \Geniem\Importer\Post - Functions

#### __construct() `public`

To start a new import process call the Post class constructor and pass a unique Geniem Importer id for it. This creates a new instance of the class and identifies it. If this is an update, the WP post matching the id is fetched and the post object data is loaded as default values for the import. *To ensure the time values are updating they are unset from the post object at this point.*

##### Parameters

- `$gi_id` *(string) (Required)* An id uniquely identifies the object in the external data source.

##### Example usage

```php
$post = new \Geniem\Importer\Post( 'my_id_1234' );
```



#### set_data() `public`

The first step in the import process is to set the data for the importer. This funtion takes a full importer object as a parameter, validates all fields and sets the data into the corresponding class properties. To check if the data is valid after setting it, you can call the `get_errors()` which will return an array of occurred errors.

##### Parameters

- `$raw_post` *(object) (Required)* An object containing needed `\Geniem\Importer\Post` class properties.
  - `post` *(object) (Required)* The basic [WP post](https://codex.wordpress.org/Class_Reference/WP_Post) object data as a `stdClass` object.
  - `attachments` *(array) (Optional)* An array of attachment objects containing:
    - `id` *(string) (Required)* An unique id identifying the object in the external data source.
    - `src` *(string) (Required)* The source from which to upload the image into WordPress. 
      - *The plugin currently supports only image files!*
    - `alt` *(string) (Optional)* The alt text for the image. This is saved into postmeta.
    - `caption` *(string) (Optional)* The file caption text.
    - `descrpition` *(string) (Optional)* The file description text.
  - `meta` *(object) (Optional)* An object where all the keys correspond to meta keys and values correspond to meta values.
  - `taxonomies` *(array) (Optional)* An array of taxonomy objects containing:
    - `slug` *(string) (Required)* The taxonomy term slug.
    - `name` *(string) (Required)* The taxonomy term display name.
    - `taxonomy` *(string) (Required)* The taxonomy name, for example `category`.
  - `acf` *(array) (Optional)* An array of Advanced Custom Fields data objects containing:
    - `type` *(string) (Required)* The ACF field type ([types](https://www.advancedcustomfields.com/resources/#field-types)).
    - `key` *(string) (Required)* The ACF field key. This must be the unique key defined for the field.
    - `value` *(mixed) (Required)* The data value matching the field type specifications.
  - `i18n` *(object) (Optional)* Custom localization information is stored in the property. It must contain an object with the following properties:
    - `locale` *(string) (Required)* The language code string, for example `fi`.
    - `master` *(string|object) (Required)* A `gi_id` value to be used to fetch a default language post or an array containing a `query_key` value. This is used to link the post as a translation.

#### Example usage

```php
$post->set_data( $my_raw_post_data );
```

##### Example data in JSON format

```json
{
  "post": {
    "post_title": "The title",
    "post_content": "This is a new post and it is awesome!",
    "post_excerpt": "This is a new post..."
  },
  "meta": {
    "my_meta_key": "My meta value.",
    "my_meta_key2": 1234
  },
  "attachments": [
    {
      "mime_type": "image/jpg",
      "id": "123456",
      "alt": "Alt text is stored in postmeta.",
      "caption": "This is the post excerpt.",
      "description": "This is the post content.",
      "src": "http://upload-from-here.com/123456.jpg",
    }
  ],
  "taxonomies": [
    {
      "slug": "my-term",
      "taxonomy": "post_tag"
    }
  ],
}
```

#### save_data() `public`

Run this function after setting the data for the importer object. This function saves all set data into WordPress database. Before any data is stored into the database the current `Post` object is validated and it throws an `Geniem\Importer\Exception\PostException` if any errors have occurred. After all data is saved into the database the instance is validated again and any save errors throw the same expection. If no errors occurred, the WordPress post id is returned.

##### Parameters

- `$force_save` *(boolean) (Optional)* Set this to `true` skip validation and force saving. You can create custom validations through multiple hooks or by manually inspecting error with by getting them with the `get_errors()` function. Defaults to `false`.

## Logging

The plugin creates a custom table into the WordPress database called `wp_geniem_importer_log`. This table holds log entries of all import actions and contains the following columns:

- `id` Log entry id.
- `gi_id` The importer object id.
- `post_id` The WordPress post id of the importer object. Stored only if the `save()` is run successfully.
- `import_date_gmt` A GMT timestamp of the import date in MySQL datetime format.
- `data` The importer object data containing all properties including errors.
- `status` The import status: `OK|FAIL`.

### Rollback

The log provides a rollback feature. If an import fails the importer tries to roll back the previous successful import. If no previous imports with the `OK` status are found, the imported object is set into `draft` state to prevent front-end users from accessing posts with malformed data.

To disable the rollback feature set the `GENIEM_IMPORTER_ROLLBACK_DISABLE` constant with a value of `true`.

## Changelog

[CHANGELOG.md](CHANGELOG.md)

## Contributors

-  [Geniem](https://github.com/devgeniem)
-  [Ville Siltala](https://github.com/villesiltala)
-  [Timi-Artturi Mäkelä](https://github.com/Liblastic)


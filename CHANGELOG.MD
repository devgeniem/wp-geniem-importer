# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Released

### Fixed
- Not detecting all languages if importing from backend

## [1.0.0] - 2018-01-26

### Changed

- Initial release.
- Settings are now used with a shorter lower case format and thus separated from the constants.

## [0.3.0] - 2018-01-26

### Changed

- Code documentation updates and fixes.
- All plugin data including the plugin version is loaded automatically from the plugin file comments and stored into plugin settings.
- Errors are back under the Post class. Other classes may access the errors via an object reference of the Post class.
- The `Polylang` class uses `Api::get_prop()` for `i18n` data handling to prevent errors.
- Fixed the `master` property handling in the `Polylang` class to match the original specification.

### Added

- If `_thumbnail_id` is trying to be saved before attachments are saved, an error is produced and the meta key-value pair is not saved.
- If the Post object contains time information, add them into the post data array in `wp_insert_post_data` hook to save them into the database.
- A custom database table that stores all the imports made.
- If errors are found, revert the current post to the last successful import. If it is the first import, set post status as `draft`.
- Added a `force_save` flag for the `save()` functions. This forces saving even if the importer object has errors.

## [0.2.5] - 2018-01-26

### Added
- Check for exif_read_data() function errors.

## [0.2.4] - 2017-09-20

### Changed

- New composer name wp-geniem-importer in composer.json

## [0.2.3] - 2017-09-12

### Changed

- Attachment documentation
- insert_attachment_from_url response strict comparison

## [0.2.0] - 2017-09-08

### Added

- Added CHANGELOG.MD

### Changed

- post_meta straight from key value pair. No more associative array with "meta_key" : "key_1", "meta_value" : "value_1" refactored structure to "key_1" : "value_1"
- gi_id isn't anymore in post_meta block refactored to the ground level of post object in json. {... "gi_id" : 12345 }
- Better Localization class structure.
- Moved Localization to own folder
- Error messages for localization
- Better comments
- Errors:set changed to public from private

## Unreleased
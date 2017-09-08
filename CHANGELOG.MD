# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

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

### Removed 

- 
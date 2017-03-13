![geniem-github-banner](https://cloud.githubusercontent.com/assets/5691777/14319886/9ae46166-fc1b-11e5-9630-d60aa3dc4f9e.png)

# Geniem Importer

Geniem Importer is a WordPress importer plugin enabling importing WordPress data from external sources through an object-oriented api.

***The plugin is in development stage and available only as a private repository for Geniem developers!***

## Installation

Install the plugin with Composer by first adding the private repository and then requiring the package:

```
composer config repositories.devgeniem/geniem-importer git git@github.com:devgeniem/geniem-importer.git
composer require devgeniem/geniem-importer
```
## Usage

First activate the plugin from WordPress dashboard or with WP-CLI.

```
wp plugin activate geniem-importer
```

### Importing post objects

The plugin creates a namespaced api providing functions for importing various data types. An example of importing a single post can be found [here](docs/examples/example-post-php).

## Contributors

-  [devgeniem](https://github.com/devgeniem)
- [villesiltala](https://github.com/villesiltala)


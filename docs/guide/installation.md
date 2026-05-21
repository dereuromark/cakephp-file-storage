# Installation

## Using Composer

Installing the plugin via [Composer](https://getcomposer.org/) is straightforward.
Run this in your project folder:

```bash
composer require dereuromark/cakephp-file-storage
```

## Load the plugin

```bash
bin/cake plugin load FileStorage
```

If you do not have authentication set up around the admin backend, disable its
routes so visitors cannot browse to the file-storage backend:

```bash
bin/cake plugin load FileStorage --no-routes
```

The admin backend is optional — you can always replace it with your own. See the
[Admin Backend](/admin/) page for how to gate and configure it.

## Database setup

Set up the plugin database using the
[official migrations plugin for CakePHP](https://github.com/cakephp/migrations):

```bash
bin/cake migrations migrate -p FileStorage
```

You can also copy the migrations over and adjust them to your needs, then run
your local `migrations migrate` instead.

## Adapter-specific configuration

Depending on your storage backend — for example Amazon S3 or Azure — you will
very likely need additional vendor libraries and extended adapter
configuration.

The plugin uses the FlySystem-based storage abstraction from the
`php-collective/file-storage` library. Check that library's documentation for
the available adapters and their configuration options.

::: tip
For local development the bundled `Local` adapter needs nothing extra. See
[Usage](./usage#configure-storage-service) for a minimal storage configuration.
:::

## Running the tests

The plugin tests are set up so you can run them without putting the plugin into
a CakePHP application. Go into the FileStorage folder and run:

```bash
cd <file-storage-plugin-folder>
composer update
composer test
```

Or run the tests directly with PHPUnit:

```bash
vendor/bin/phpunit
```

## Next steps

- [Quick Start](./quick-start) — a complete avatar upload walkthrough.
- [Usage](./usage) — storage configuration, associations, and the upload flow.

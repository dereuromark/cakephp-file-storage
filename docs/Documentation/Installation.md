Installation
============

Using Composer
--------------

Installing the plugin via [Composer](https://getcomposer.org/) is very simple, just run in your project folder:

```
composer require dereuromark/cakephp-file-storage
```

## Load Plugin
Run
```
bin/cake plugin load FileStorage
```

Make sure to disable routing if you do not have authentication set up:
```
bin/cake plugin load FileStorage --no-routes
```
You do not want visitors to be able to browse to the file storage backend.

This backend is also optional, you can always replace it with your own.]()



Database Setup
--------------

You need to set up the plugin database using [the official migrations plugin for CakePHP](https://github.com/cakephp/migrations).

```
cake migrations migrate -p FileStorage
```

You can also copy over the migrations and manually adjust them to your needs and run local `migrations migrate` instead.


Adapter Specific Configuration
------------------------------

Depending on the storage backend of your choice, for example Amazon S3 or Azure, you'll very likely need additional vendor libs and extended adapter configuration.

The plugin uses the FlySystem-based storage abstraction from the `php-collective/file-storage` library. Check the documentation of that library for available adapters and configuration options.


## Setting up backend auth

You can set up backend auth using Authentication/Authorization plugins or e.g. TinyAuth.

With the latter it is just the following in `config/auth_acl.ini`:
```
[FileStorage.Admin/FileStorage]
* = admin
```
With `admin` being your role that should be able to access it as `/admin/file-storage` via URL.

WARNING: Do not expose the controller actions without any proper auth in place.
You do not want to make the uploaded content accessible publicly.

Running Tests
-------------

The plugin tests are set up in a way that you can run them without putting the plugin into a CakePHP application. All you need to do is to go into the FileStorage folder and run these commands:

```bash
cd <file-storage-plugin-folder>
composer update
composer test
```

Or run tests directly with PHPUnit:

```bash
vendor/bin/phpunit
```

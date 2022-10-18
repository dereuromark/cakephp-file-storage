Installation
============

Make sure you've checked the [requirements](Requirements.md) first!

Using Composer
--------------

Installing the plugin via [Composer](https://getcomposer.org/) is very simple, just run in your project folder:

```
composer require dereuromark/file-storage:^3.0
```

Database Setup
--------------

You need to set up the plugin database using [the official migrations plugin for CakePHP](https://github.com/cakephp/migrations).

```
cake migrations migrate -p FileStorage
```

You can also copy over the migrations and manually adjust them to your needs and run local `migrations migrate` instead.

CakePHP Bootstrap
-----------------

Add the following part to your applications ```config/bootstrap.php```.

```php
use Cake\Event\EventManager;
use FileStorage\Lib\FileStorageUtils;
use FileStorage\Lib\StorageManager;
use FileStorage\Event\ImageProcessingListener;
use FileStorage\Event\LocalFileStorageListener;

$listener = new LocalFileStorageListener();
EventManager::instance()->on($listener);

// For automated image processing you'll have to attach this listener as well
$listener = new ImageProcessingListener();
EventManager::instance()->on($listener);
```

Adapter Specific Configuration
------------------------------

Depending on the storage backend of your choice, for example Amazon S3 or Dropbox, you'll very likely need additional vendor libs and extended adapter configuration.

Please see the [Specific Adapter Configuration](Specific-Adapter-Configurations.md) page of the documentation for more information about then. It is also worth checking the Gaufrette documentation for additonal adapters.

Running Tests
-------------

The plugin tests are set up in a way that you can run them without putting the plugin into a CakePHP3 application. All you need to do is to go into the FileStorage folder and run these commands:

```
cd <file-storage-plugin-folder>
composer update
phpunit
```

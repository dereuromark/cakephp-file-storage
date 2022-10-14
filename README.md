FileStorage Plugin for CakePHP
==============================

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/burzum/cakephp-file-storage/3.0.svg?style=flat-square)](https://travis-ci.org/burzum/cakephp-file-storage)
[![Coverage Status](https://img.shields.io/coveralls/burzum/cakephp-file-storage.svg?branch=3.0.svg?style=flat-square)](https://coveralls.io/r/burzum/cakephp-file-storage)
[![Code Quality](https://img.shields.io/scrutinizer/g/burzum/cakephp-file-storage.svg?branch=3.0?style=flat-square)](https://coveralls.io/r/burzum/cakephp-file-storage)

**If you're upgrading from CakePHP 2.x please read [the migration guide](docs/Documentation/Migrating-from-CakePHP-2.md).**

The **File Storage** plugin is giving you the possibility to upload and store files in virtually any kind of storage backend. The plugin features the [FlySystem](https://github.com/thephpleague/flysystem) library in a CakePHP fashion and provides a simple way to use the storage adapters.

Storage adapters are an unified interface that allow you to store file data to your local file system, in memory, in a database or into a zip file and remote systems. There is a database table keeping track of what you stored where. You can always write your own adapter or extend and overload existing ones.

How it works
------------

The whole plugin is build with clear [Separation of Concerns (SoC)](https://en.wikipedia.org/wiki/Separation_of_concerns) in mind: A file is *always* an entry in the `file_storage` table from the app perspective. The table is the *reference* to the real place of where the file is stored and keeps some meta information like mime type, filename, file hash (optional) and size as well. Storing the path to a file inside an arbitrary table along other data is considered as *bad practice* because it doesn't respect SoC from an architecture perspective but many people do it this way for some reason.

You associate the `file_storage` table with your model using the FileStorage model from the plugin via hasOne, hasMany or HABTM. When you upload a file you save it to the FileStorage model through the associations, `Documents.file` for example. The FileStorage model dispatches then file storage specific events, the listeners listening to these events process the file and put it in the configured storage backend using adapters for different backends and build the storage path using a path builder class.


Supported CakePHP Versions
--------------------------

 * CakePHP 4.x -> 4.0 Branch (Rewritten almost from scratch)
 * CakePHP 4.x -> 3.0 Branch (Old codebase)
 * CakePHP 3.x -> 2.0 Branch
 * CakePHP 2.x -> 1.0 Branch

Requirements
------------

 * PHP 7.4+
 * CakePHP 4.x

Documentation
-------------

For documentation, as well as tutorials, see the [docs](docs/README.md) directory of this repository.

Support
-------

For bugs and feature requests, please use the [issues](https://github.com/burzum/cakephp-file-storage/issues) section of this repository.

Contributing
------------

To contribute to this plugin please follow a few basic rules.

* Pull requests must be send to the branch that reflects the version you want to contribute to.
* [Unit tests](http://book.cakephp.org/4.0/en/development/testing.html) are required.

License
-------

Copyright Florian Krämer

Licensed under The MIT License
Redistributions of files must retain the above copyright notice.

# FileStorage Plugin for CakePHP

[![CI](https://github.com/dereuromark/cakephp-file-storage/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/dereuromark/cakephp-file-storage/actions/workflows/ci.yml?query=branch%3Amaster)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Latest Stable Version](https://poser.pugx.org/dereuromark/cakephp-file-storage/v/stable.svg)](https://packagist.org/packages/dereuromark/cakephp-file-storage)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

The **FileStorage** plugin is giving you the possibility to upload and store files in virtually any kind of storage backend. The plugin features the [FlySystem](https://github.com/thephpleague/flysystem) library in a CakePHP fashion and provides a simple way to use the storage adapters.

Storage adapters are a unified interface that allow you to store file data to your local file system, in memory, in a database or into a zip file and remote systems. There is a database table keeping track of what you stored where. You can always write your own adapter or extend and overload existing ones.

This branch is for use with **CakePHP 5.1+**. See [version map](https://github.com/dereuromark/cakephp-file-storage/wiki#cakephp-version-map) for details.

## How it works

The whole plugin is build with clear [Separation of Concerns (SoC)](https://en.wikipedia.org/wiki/Separation_of_concerns) in mind: A file is *always* an entry in the `file_storage` table from the app perspective. The table is the *reference* to the real place of where the file is stored and keeps some meta information like mime type, filename, file hash (optional) and size as well. Storing the path to a file inside an arbitrary table along other data is considered as *bad practice* because it doesn't respect SoC from an architecture perspective but many people do it this way for some reason.

You associate the `file_storage` table with your model using the FileStorage model from the plugin via hasOne, hasMany or HABTM. When you upload a file you save it to the FileStorage model through the associations, `Documents.file` for example. The FileStorage model dispatches then file storage specific events, the listeners listening to these events process the file and put it in the configured storage backend using adapters for different backends and build the storage path using a path builder class.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/) directory of this repository.

## Support

For bugs and feature requests, please use the [issues](https://github.com/dereuromark/cakephp-file-storage/issues) section of this repository.

## Contributing

To contribute to this plugin please follow a few basic rules.

* Pull requests must be sent to the branch that reflects the version you want to contribute to.
* [Unit tests](http://book.cakephp.org/4.0/en/development/testing.html) are required.

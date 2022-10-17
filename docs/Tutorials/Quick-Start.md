Quick-Start Tutorial
====================

It is required that you have at least a basic understanding of how the event system of CakePHP work works. If you're unsure it is recommended to read about it first. It is expected that you take the time to try to actually *understand* what you're doing instead of just copy and pasting the code. Understanding OOP and namespaces in php is required for this tutorial.

This tutorial will assume that we're going to add an avatar image upload for our users.

For image processing you'll need the phauthentic/file-storage-image-processor library. If you don't have it already added, add it now:

```sh
composer require phauthentic/file-storage-image-processor
```

In your applications `config/bootstrap.php` load the plugins:

```php
Plugin::load('FileStorage', [
	'bootstrap' => true,
]);
```

This will load the `bootstrap.php` of the File Storage plugin. The default configuration in there will load the LocalStorage listener and the ImageProcessing listener. You can also skip that bootstrap part and configure your own listeners in your apps bootstrap.php or a new file.

To make image processing work you'll have to add this to your applications bootstrap (or use a dedicated storage.php):

```php
<?php
// Container
$container = \App\Container\Container::getSingletonInstance();
$container->delegate(
    new League\Container\ReflectionContainer(),
);

// Storage setup
$storageFactory = new \Phauthentic\Infrastructure\Storage\StorageAdapterFactory($container);
$storageService = new \Phauthentic\Infrastructure\Storage\StorageService(
    $storageFactory,
);
$storageService->addAdapterConfig(
    'Local',
    \Phauthentic\Infrastructure\Storage\Factories\LocalFactory::class,
    [
        'root' => WWW_ROOT . 'img' . DS . 'thumbs' . DS,
    ],
);

$pathBuilder = new \Phauthentic\Infrastructure\Storage\PathBuilder\PathBuilder([
    'randomPathLevels' => 1,
    'sanitizer' => new \Phauthentic\Infrastructure\Storage\Utility\FilenameSanitizer([
        'urlSafe' => true,
        'removeUriReservedChars' => true,
        'maxLength' => 190,
    ]),
]);
$fileStorage = new \Phauthentic\Infrastructure\Storage\FileStorage(
    $storageService,
    $pathBuilder,
);

// Image Manager and Processor
$imageManager = new \Intervention\Image\ImageManager();
$imageProcessor = new \Phauthentic\Infrastructure\Storage\Processor\Image\ImageProcessor(
    $fileStorage,
    $pathBuilder,
    $imageManager,
);
$imageDimensionsProcessor = new \App\Storage\Processor\ImageDimensionsProcessor();
$stackProcessor = new \Phauthentic\Infrastructure\Storage\Processor\StackProcessor([
    $imageProcessor,
    $imageDimensionsProcessor,
]);

// Configure variants
$collection = \Phauthentic\Infrastructure\Storage\Processor\Image\ImageVariantCollection::create();
$collection->addNew(\App\Storage\StorageCollections::IMG_RESIZED)
    ->resize(300, 300)
    ->optimize();
$collection->addNew(\App\Storage\StorageCollections::IMG_CROPPED)
    ->crop(100, 100);

\Cake\Core\Configure::write([
    'FileStorage' => [
        'imageVariants' => [
            'Avatars' => [
                'EventImages' => $collection->toArray(),
            ],
        ],
        'behaviorConfig' => [
            'fileStorage' => $fileStorage,
            'fileProcessor' => $stackProcessor,
        ],
    ],
]);
```

We now assume that you have a table called `Users` and that you want to attach an avatar image to your users.

In your `App\Model\Table\UsersTable.php` file is a method called `inititalize()`. Add the avatar file association there:

```php
$this->hasOne('Avatars', [
	'className' => 'FileStorage.FileStorage',
	'foreignKey' => 'foreign_key',
	'conditions' => [
		'Avatars.model' => 'Avatar',
	],
]);
```

Especially pay attention to the `conditions` key in the config array of the association. You must specify this here or File Storage won't be able to identify that kind of file properly.

Either save it through the association along with your users save call or save it separate. However, whatever you do, it is important that you set the `foreign_key` and `model` field for the associated file storage entity.

If you don't specify the model field it will use the file storage table name by default and your has one association won't find it.

Inside the `edit.php` template view file of your Users::edit action:

```php
echo $this->Form->create($user);
echo $this->Form->input('username');
// That's the important line / field
echo $this->From->file('avatar.file');
echo $this->Form->submit(__('Submit'));
echo $this->Form->end();
```

You **must** use the `file` field for the uploaded file. The plugin will check the entity for this field.

Your users controller `edit()` method:

```php
/**
 * Assuming you've loaded:
 *
 * - AuthComponent
 * - FlashComponent
 */
class UsersController extends AppController {

	public function edit() {
		$userId = $this->Auth->user('id');
		$user = $this->Users->get($userId);

		if ($this->request->is(['post', 'put'])) {
			$user = $this->Users->patchEntity($user, $this->request->data());

			if ($this->Users->save($user)) {
				$this->Flash->success('User updated');
			} else {
				$this->Flash->error('There was a problem updating the user.');
			}
		}

		$this->set('user', $user);
	}
}
```

Quick-Start Tutorial
====================

It is required that you have at least a basic understanding of how the event system of CakePHP works. If you're unsure it is recommended to read about it first. It is expected that you take the time to try to actually *understand* what you're doing instead of just copy and pasting the code. Understanding OOP and namespaces in PHP is required for this tutorial.

This tutorial will assume that we're going to add an avatar image upload for our users.

For image processing you'll need the `php-collective/file-storage-image-processor` library. If you don't have it already added, add it now:

```sh
composer require php-collective/file-storage-image-processor
```
See also other require-dev dependencies of the plugin, if they could be useful for you.

In your application's `config/bootstrap.php` load the plugin:

```php
// CakePHP 5.x - plugins are auto-loaded via composer.json
// The plugin is automatically available after running:
// bin/cake plugin load FileStorage
```

If you need to manually load the plugin in your Application.php:

```php
// In src/Application.php bootstrap() method
$this->addPlugin('FileStorage');
```

To make image processing work you'll have to add this to your applications bootstrap (or use a dedicated storage.php):

```php
<?php
// Container
$container = \App\Container\Container::getSingletonInstance();
$container->delegate(
    new League\Container\ReflectionContainer(),
);

// Storage setup
$storageFactory = new \PhpCollective\Infrastructure\Storage\StorageAdapterFactory($container);
$storageService = new \PhpCollective\Infrastructure\Storage\StorageService(
    $storageFactory,
);
$storageService->addAdapterConfig(
    'Local',
    \PhpCollective\Infrastructure\Storage\Factories\LocalFactory::class,
    [
        'root' => WWW_ROOT . 'img' . DS . 'thumbs' . DS,
    ],
);

$pathBuilder = new \PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder([
    'randomPathLevels' => 1,
    'sanitizer' => new \PhpCollective\Infrastructure\Storage\Utility\FilenameSanitizer([
        'urlSafe' => true,
        'removeUriReservedChars' => true,
        'maxLength' => 190,
    ]),
]);
$fileStorage = new \PhpCollective\Infrastructure\Storage\FileStorage(
    $storageService,
    $pathBuilder,
);

// Image Manager and Processor
$imageManager = new \Intervention\Image\ImageManager();
$imageProcessor = new \PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor(
    $fileStorage,
    $pathBuilder,
    $imageManager,
);
$imageDimensionsProcessor = new \App\Storage\Processor\ImageDimensionsProcessor();
$stackProcessor = new \PhpCollective\Infrastructure\Storage\Processor\StackProcessor([
    $imageProcessor,
    $imageDimensionsProcessor,
]);

// Configure variants
$collection = \PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection::create();
$collection->addNew(\App\Storage\StorageCollections::IMG_RESIZED)
    ->resize(300, 300)
    ->optimize();
$collection->addNew(\App\Storage\StorageCollections::IMG_CROPPED)
    ->crop(100, 100);

\Cake\Core\Configure::write([
    'FileStorage' => [
        // Structure: [ModelAlias][CollectionName][variants]
        // Model comes from table alias, Collection from entity field
        'imageVariants' => [
            'Users' => [
                'Avatar' => $collection->toArray(),
            ],
        ],
        'behaviorConfig' => [
            'fileStorage' => $fileStorage,
            'fileProcessor' => $stackProcessor,
            'fileValidator' => \App\FileStorage\Validator\ImageValidator::class,
        ],
    ],
]);
```

We now assume that you have a table called `Users` and that you want to attach an avatar image to your users.

In your `App\Model\Table\UsersTable.php` file is a method called `initialize()`. Add the avatar file association there:

```php
$this->hasOne('Avatars', [
    'className' => 'FileStorage.FileStorage',
    'foreignKey' => 'foreign_key',
    'conditions' => [
        'Avatars.model' => 'Users',
        'Avatars.collection' => 'Avatar',
    ],
]);
```

Especially pay attention to the `conditions` key in the config array of the association. You must specify both `model` and `collection` conditions or File Storage won't be able to identify that kind of file properly.

Either save it through the association along with your users save call or save it separately. However, whatever you do, it is important that you set the `foreign_key`, `model`, and `collection` fields for the associated file storage entity.

If you don't specify these fields, the association won't find the correct files.

Inside the `edit.php` template view file of your Users::edit action:

```php
echo $this->Form->create($user);
echo $this->Form->control('username');
// That's the important line / field
echo $this->Form->control('avatar.file', ['type' => 'file']);
echo $this->Form->button(__('Submit'));
echo $this->Form->end();
```

You **must** use the `file` field for the uploaded file. The plugin will check the entity for this field.

Your users controller `edit()` method:

```php
/**
 * Assuming you've loaded:
 *
 * - Authentication plugin (or similar)
 * - FlashComponent
 */
class UsersController extends AppController
{
    public function edit()
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $user = $this->Users->get($userId, contain: ['Avatars']);

        if ($this->request->is(['post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->request->getData());

            if ($this->Users->save($user)) {
                $this->Flash->success('User updated');

                return $this->redirect(['action' => 'view', $user->id]);
            } else {
                $this->Flash->error('There was a problem updating the user.');
            }
        }

        $this->set(compact('user'));
    }
}
```

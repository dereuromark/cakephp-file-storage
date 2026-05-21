# Quick Start

This tutorial adds an **avatar image upload** for your users, end to end.

::: tip Prerequisites
You should have at least a basic understanding of the CakePHP event system, OOP,
and namespaces. Take the time to *understand* each step rather than copy-pasting.
:::

For image processing you'll need the `php-collective/file-storage-image-processor`
library. Add it if you don't have it already:

```bash
composer require php-collective/file-storage-image-processor
```

See also the other require-dev dependencies of the plugin — some may be useful
for you.

## Load the plugin

In CakePHP 5.x the plugin is available after loading it:

```bash
bin/cake plugin load FileStorage
```

If you prefer to load it manually in `src/Application.php`:

```php
// In src/Application.php bootstrap()
$this->addPlugin('FileStorage');
```

## Configure storage and image processing

To make image processing work, add this to your application's bootstrap (or a
dedicated `config/storage.php`):

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

// Image Manager and Processor (Intervention Image v3)
$imageManager = new \Intervention\Image\ImageManager(
    new \Intervention\Image\Drivers\Gd\Driver()
);
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
        // Model comes from the table alias, Collection from the entity field
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

## Add the association

We assume you have a `Users` table and want to attach an avatar image to your
users. In `App\Model\Table\UsersTable::initialize()`, add the avatar
association:

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

::: warning Set model and collection
Pay attention to the `conditions` key. You **must** specify both `model` and
`collection` conditions, or FileStorage won't be able to identify that kind of
file properly.
:::

Either save it through the association along with your user's save call, or save
it separately. Whatever you do, it is important that you set the `foreign_key`,
`model`, and `collection` fields for the associated file-storage entity —
otherwise the association won't find the correct files.

## The form

Inside the `edit.php` template of your `Users::edit` action:

```php
echo $this->Form->create($user);
echo $this->Form->control('username');
// That's the important line / field
echo $this->Form->control('avatar.file', ['type' => 'file']);
echo $this->Form->button(__('Submit'));
echo $this->Form->end();
```

You **must** use the `file` field for the uploaded file — the plugin checks the
entity for this field.

## The controller

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
            }

            $this->Flash->error('There was a problem updating the user.');
        }

        $this->set(compact('user'));
    }
}
```

## Where to go next

- [Usage](./usage) — the full set of concepts, associations, and operations.
- [Image variants and versioning](/images/) — configure and generate variants.
- [The Image helper](/images/helper) — display images and variants in templates.

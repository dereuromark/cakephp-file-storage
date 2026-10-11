# Quick Start

Add an avatar upload to an existing CakePHP application. Start by storing and
displaying the original image. Thumbnails are an optional final step.

You need a working database connection and an existing
`Users` table with a `username` field and integer primary keys. Adapt the table
and field names if your app uses different ones. For UUID owner keys, configure
[foreign key column types](./installation#foreign-key-column-types) first.

## 1. Install and create the tables

Follow [Installation](./installation) to install and load the plugin and apply its migrations. Existing FileStorage installations should follow [Upgrading](./upgrading) first.

The admin UI denies access by default. Leave `FileStorage.adminAccess` unset
while following this tutorial.

<span id="configure-storage-and-image-processing"></span>

## 2. Configure storage

Create `config/storage.php` with the following contents. This first configuration
stores original files in a private directory outside `webroot`:

```php
<?php
use Cake\Core\Configure;
use PhpCollective\Infrastructure\Storage\Factories\LocalFactory;
use PhpCollective\Infrastructure\Storage\FileStorage;
use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder;
use PhpCollective\Infrastructure\Storage\StorageAdapterFactory;
use PhpCollective\Infrastructure\Storage\StorageService;

$storageService = new StorageService(new StorageAdapterFactory());
$storageService->addAdapterConfig('Local', LocalFactory::class, [
    'root' => ROOT . DS . 'storage' . DS . 'files',
]);
$pathBuilder = new PathBuilder();
$fileStorage = new FileStorage($storageService, $pathBuilder);

Configure::write('FileStorage.behaviorConfig', [
    'fileStorage' => $fileStorage,
    'fileProcessor' => null,
    'fileValidator' => null,
]);
```

Load it at the end of `config/bootstrap.php`, after the application's normal
configuration has loaded:

```php
require CONFIG . 'storage.php';
```

Create `storage/files` and grant your PHP process write access. Keep this
persistent directory out of version control and include it in your backups.
Do not use `TMP` for permanent uploads: temporary-directory cleanup would remove
stored files.

`StorageAdapterFactory` works without a container. You do not need an
`App\Container\Container` class or any other application-specific storage classes.

## 3. Validate avatar uploads

Create `src/FileStorage/Validator/ImageValidator.php`:

```php
<?php
namespace App\FileStorage\Validator;

use Cake\Validation\Validator;
use FileStorage\Model\Validation\ImageValidationTrait;
use FileStorage\Model\Validation\UploadValidationTrait;
use FileStorage\Model\Validation\UploadValidatorInterface;

class ImageValidator implements UploadValidatorInterface
{
    use ImageValidationTrait;
    use UploadValidationTrait;

    public function configure(Validator $validator): void
    {
        $validator->setProvider('upload', static::class);
        $validator->remove('file', 'image');
        $validator->remove('file', 'size');
        $validator->remove('file', 'extension');
        $validator->add('file', 'image', [
            'rule' => 'isValidImage',
            'provider' => 'upload',
            'message' => 'Choose a JPEG, PNG, GIF, or WebP image.',
        ]);
        $validator->add('file', 'extension', [
            'rule' => ['hasAllowedExtension', ['jpg', 'jpeg', 'png', 'gif', 'webp']],
            'provider' => 'upload',
            'message' => 'Use a JPEG, PNG, GIF, or WebP filename.',
        ]);
        $validator->add('file', 'size', [
            'rule' => ['fileSize', '<=', '5MB'],
            'message' => 'Choose an image no larger than 5 MB.',
        ]);
    }
}
```

Then add this after the behavior configuration in `config/storage.php`:

```php
Configure::write(
    'FileStorage.behaviorConfig.fileValidator',
    \App\FileStorage\Validator\ImageValidator::class,
);

\Cake\Event\EventManager::instance()->on(
    'FileStorage.beforeSave',
    static function (\Cake\Event\EventInterface $event): void {
        $entity = $event->getData('entity');
        $upload = $entity->get('file');
        $source = $upload instanceof \Psr\Http\Message\UploadedFileInterface
            ? $upload->getStream()->getMetadata('uri')
            : ($upload['tmp_name'] ?? null);
        if (!is_string($source) || !is_file($source)) {
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $entity->set('mime_type', $mime ?: 'application/octet-stream');
    },
);
```

The listener stores a MIME type detected from the uploaded bytes. The plugin
otherwise copies the client's MIME header into the row. Extension validation
also prevents uploading a valid image with a script filename into public storage.

The validator replaces its own named rules because configuration can run more
than once during a save. The controller below removes an empty file input before validation, so editing
a username without selecting a new image keeps the existing avatar. See [Validation](./validation)
for other limits and file types.

## 4. Add the association

Add this inside `UsersTable::initialize()`, after `parent::initialize($config)`:

```php
$this->hasOne('Avatars', [
    'className' => 'FileStorage.FileStorage',
    'foreignKey' => 'foreign_key',
    'conditions' => [
        'Avatars.model' => 'Users',
        'Avatars.collection' => 'Avatars',
    ],
    'dependent' => true,
    'cascadeCallbacks' => true,
]);
$this->addBehavior('FileStorage.FileAssociation', [
    'associations' => [
        'Avatars' => ['replace' => true],
    ],
]);
```

The default collection name is the association name, `Avatars`. The association
behavior sets `model` and `collection`; CakePHP supplies `foreign_key` when it
saves the association. Replacing the avatar removes the previous file row and
its stored files. Deleting the user also deletes its avatar through callbacks.

In `src/Model/Entity/User.php`, add `avatar` to the existing `$patchable` array:

```php
'avatar' => true,
```

Keep your other accessible fields. A `hasOne('Avatars')` association uses the
singular entity property `avatar`.

## 5. Save the upload

This action assumes your application already loads the Authentication component
and Flash component. It edits the signed-in user rather than taking a user id
from the submitted form. Keep your normal authentication, authorization and
CSRF middleware enabled.

In `src/Controller/UsersController.php`:

```php
public function edit()
{
    $userId = $this->Authentication->getIdentity()->getIdentifier();
    $user = $this->Users->get($userId, contain: ['Avatars']);

    if ($this->request->is(['post', 'put', 'patch'])) {
        $data = $this->request->getData();
        $upload = $this->request->getData('avatar.file');
        if (!$upload || $upload->getError() === UPLOAD_ERR_NO_FILE) {
            unset($data['avatar']);
        } else {
            $user->set('avatar', null);
        }
        $user = $this->Users->patchEntity($user, $data, [
            'fields' => ['username', 'avatar'],
            'associated' => ['Avatars' => ['fields' => ['file', 'filename', 'filesize', 'mime_type', 'extension']]],
        ]);
        if ($this->Users->save($user)) {
            $this->Flash->success('User updated');

            return $this->redirect(['action' => 'edit']);
        }
        $this->Flash->error('Check the form and try again.');
    }

    $this->set(compact('user'));
}
```

A new selection creates a new avatar entity so the association behavior can
remove the previous row and its bytes after saving. Patching a file into the
existing file row replaces that row but can leave its old original on storage.

The allow-lists keep storage paths, adapter names and file ownership out of
client-controlled form data. The metadata fields in the association allow-list
are filled by the upload behavior during marshalling.

## 6. Add the form and display the avatar

Create or update `templates/Users/edit.php`:

```php
<?= $this->Form->create($user, ['type' => 'file']) ?>
<?= $this->Form->control('username') ?>
<?= $this->Form->control('avatar.file', ['type' => 'file', 'label' => 'Avatar']) ?>
<?= $this->Form->button('Save') ?>
<?= $this->Form->end() ?>

<?php if ($user->avatar && $user->avatar->path) { ?>
    <?= $this->Html->image(
        \FileStorage\Utility\SignedUrlGenerator::url($user->avatar, [
            'expires' => strtotime('+15 minutes'),
        ]),
        ['alt' => 'Your avatar'],
    ) ?>
<?php } ?>
```

`'type' => 'file'` makes the form multipart. The field name `avatar.file` matches
the association property and the upload behavior's default `fileField`.

The signed URL serves the original from private storage. Anyone holding that URL
can read it until it expires, so generate it only after authorizing access to the
user. The plugin routes must be loaded for this URL to work. Signed URLs use
`Security.salt` unless you set a separate `FileStorage.signatureSecret`.

## 7. Check the result

Open `/users/edit` while signed in. Choose a small JPEG or PNG and save. You
should see the avatar after the redirect, a row in `file_storage` with `model`
`Users`, collection `Avatars`, adapter `Local`, and a generated `uuid` and `path`.
The bytes should exist below `storage/files`.

Save once without selecting a file: the avatar should stay. Upload another
image: the old avatar should be replaced. Try a text file: the form should show
a validation error. If saving fails, check `$user->getErrors()` and your
application logs; the [troubleshooting guide](/reference/troubleshooting) covers
common configuration problems.

Full cleanup removes rows without a `foreign_key`. The avatars above are
attached to users. If you later store intentionally unattached files, preview
cleanup before running it.

## Optional: add thumbnails for public avatars

The steps above need no image processor or GD extension. To generate thumbnails,
install `php-collective/file-storage-image-processor` and enable GD or Imagick.
Version 1.x uses Intervention Image 3; version 2.x uses Intervention Image 4.
The following setup works with both processor versions.

```bash
composer require php-collective/file-storage-image-processor
```

For public avatars, change the adapter root to `WWW_ROOT . 'img' . DS . 'avatars'`
and set `FileStorage.pathPrefix` to `img/avatars/`. Make this change before your
first upload, or move existing files to the new root. Files under `webroot` are
public, even if your page uses signed URLs. Keep private avatars outside webroot
and use an authorized controller to serve variants instead.

Add this to `config/storage.php`, after creating `$fileStorage` and `$pathBuilder`:

```php
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor;
use PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection;

$variants = ImageVariantCollection::create();
$variants->addNew('thumb')->cover(100, 100);
Configure::write('FileStorage.imageVariants.Users.Avatars', $variants->toArray());
Configure::write('FileStorage.behaviorConfig.fileProcessor', new ImageProcessor(
    $fileStorage,
    $pathBuilder,
    new ImageManager(new Driver()),
));
Configure::write('FileStorage.pathPrefix', 'img/avatars/');
```

Load the helper in `AppView::initialize()`:

```php
$this->addHelper('FileStorage.Image');
```

Then display the generated thumbnail in your template:

```php
<?= $this->Image->display($user->avatar, 'thumb', ['alt' => 'Avatar']) ?>
```

New uploads generate the thumbnail. Generate it for existing avatars with:

```bash
bin/cake file_storage generate_image_variant Users Avatars
```

## Where to go next

* [Usage](./usage): multiple files, custom collections and other adapters.
* [Image variants](/images/): image processing and variant configuration.
* [Serving files](/serving/): application-specific authorization.
* [Admin backend](/admin/): enable the optional management interface.

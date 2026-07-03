<?php
// Example for using local filesystem (trivial case) with 2 variants: resize (medium) & crop (small)

define('THUMBS', WWW_ROOT . 'img' . DS . 'thumbs' . DS);

// Storage setup
$storageFactory = new \PhpCollective\Infrastructure\Storage\StorageAdapterFactory();
$storageService = new \PhpCollective\Infrastructure\Storage\StorageService(
    $storageFactory,
);
$storageService->addAdapterConfig(
    'Local',
    \PhpCollective\Infrastructure\Storage\Factories\LocalFactory::class,
    [
        'root' => THUMBS,
    ],
);

$pathBuilder = new \PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilder([
    'pathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{extension}',
    'variantPathTemplate' => '{model}{ds}{collection}{ds}{randomPath}{ds}{strippedId}{ds}{filename}.{hashedVariant}.{extension}',
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
$imageManager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
$imageProcessor = new \PhpCollective\Infrastructure\Storage\Processor\Image\ImageProcessor(
    $fileStorage,
    $pathBuilder,
    $imageManager,
);

// Configure variants
$collection = \PhpCollective\Infrastructure\Storage\Processor\Image\ImageVariantCollection::create();
$collection->addNew('resize')
    ->resize(300, 300)
    ->optimize();
$collection->addNew('crop')
    ->cover(100, 100)
    ->optimize();

return [
    'FileStorage' => [
        'pathPrefix' => 'img/thumbs/',

        // Secret used to sign temporary file-access URLs (SignedUrlGenerator,
        // HMAC-SHA256). Should be a strong, random, app-specific string kept
        // secret — anyone with it can forge valid signed URLs. No default is
        // baked in: when unset, it falls back to the app's Security salt. Set
        // this explicitly to decouple signed-URL invalidation from the salt.
        // 'signatureSecret' => env('FILE_STORAGE_SECRET'),
        // Admin UI access gate. The admin controller is fail-closed: leaving this unset
        // (or null) means every action returns 403. Opt in with one of:
        //
        //   true                        — trust an upstream gate (Authentication+Authorization,
        //                                 TinyAuth, custom middleware) on the Admin prefix.
        //   Closure(ServerRequest $r)   — return true to allow this request.
        //
        // See docs/Documentation/Installation.md for examples.
        'adminAccess' => null,

        // Standalone admin backend. When true the admin controllers run independent of
        // the host application's `App\Controller\AppController` (skips its initialize()
        // chain, loads only Flash). Useful for projects without their own admin shell.
        // Leave false (default) to inherit your AppController's components.
        'standalone' => false,

        // Bundled Bootstrap 5 / Font Awesome 6 admin layout (CDN with SRI).
        //   null    — use the bundled `FileStorage.file_storage` layout (default).
        //   false   — fall back to the host application's default layout
        //             (matches the pre-4.4 behaviour).
        //   string  — use the given layout, e.g. `'App.admin'`.
        'adminLayout' => null,

        // Back-to-App link in the admin header (opt-in). When set, an outline
        // button appears in the top navbar so admins can escape the
        // plugin-isolated layout. Accepts anything Router::url() takes — Cake
        // URL array, path string, or full URL. Use 'plugin' => false to
        // anchor the builder to the host app rather than the FileStorage plugin.
        // 'adminBackUrl' => ['plugin' => false, 'prefix' => 'Admin', 'controller' => 'Overview', 'action' => 'index'],
        // 'adminBackLabel' => 'Back to admin', // Optional. Defaults to "Back to App".

        // Optional dependency note:
        // - The "regenerate variants" button on the admin file listing requires
        //   `dereuromark/cakephp-queue` to be installed and loaded; the button
        //   renders disabled (with a tooltip) when Queue is not loaded.
        // Image variants configuration
        // Structure: [ModelAlias][CollectionName][variants]
        // ModelAlias is the persisted file_storage.model value.
        // CollectionName can be different (e.g., 'Cover', 'Avatar', 'Gallery')
        'imageVariants' => [
            'EventImages' => [
                'EventImages' => $collection->toArray(),
            ],
            'PlaceImages' => [
                'PlaceImages' => $collection->toArray(),
            ],
            'Photos' => [
                'Photos' => $collection->toArray(),
            ],
            // Example with model != collection:
            // 'Posts' => [
            //     'Cover' => $coverVariants->toArray(),
            //     'GalleryImages' => $galleryVariants->toArray(),
            // ],
        ],
        'behaviorConfig' => [
            'fileStorage' => $fileStorage,
            'fileProcessor' => null,
            'fileValidator' => \App\FileStorage\Validator\ImageValidator::class,
            // Entity<->file object transformer used by the image variant queue task.
            // Must be a FileStorage\FileStorage\DataTransformerInterface instance;
            // anything else is ignored. Default (unset): a DataTransformer bound
            // to the storage table.
            // 'dataTransformer' => null,
        ],
    ],
];

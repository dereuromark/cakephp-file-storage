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
$imageManager = new \Intervention\Image\ImageManager();
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
    ->fit(100, 100)
    ->optimize();

return [
    'FileStorage' => [
        'pathPrefix' => 'img/thumbs/',
        // Image variants configuration
        // Structure: [ModelAlias][CollectionName][variants]
        // Note: ModelAlias comes from the table (e.g., 'Posts', 'Users')
        //       CollectionName can be different (e.g., 'Cover', 'Avatar', 'Gallery')
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
        ],
    ],
];

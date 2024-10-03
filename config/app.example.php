<?php
// Example for using local filesystem (trivial case) with 2 variants: resize (medium) & crop (small)

define('THUMBS', WWW_ROOT . 'img' . DS . 'thumbs' . DS);

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
    ->fit(100, 100)
    ->optimize();

return [
    'FileStorage' => [
        'pathPrefix' => 'img/thumbs/',
        'imageVariants' => [
            //FIXME
            'EventImages' => [
                'EventImages' => $collection->toArray(),
            ],
            'PlaceImages' => [
                'PlaceImages' => $collection->toArray(),
            ],
            'Photos' => [
                'Photos' => $collection->toArray(),
            ],
        ],
        'behaviorConfig' => [
            'fileStorage' => $fileStorage,
            'fileProcessor' => $stackProcessor,
            'fileValidator' => \App\FileStorage\Validator\ImageValidator::class,
        ],
    ],
];

# Getting a file path and URL

The path and filename of a stored file in the storage backend that was used to store the file is generated by a path builder. The event listener that stored your file has used a path builder to generate the path based on the entity data. This means that if you have the entity and instantiate a path builder you can build the path to it in any place.

The plugin already provides you with several convenience short cuts to do that.

Be aware that whenever you use a path builder somewhere, you **must** use the same path builder and options as when the entity was created. They're usually the same as configured in your event listener.

## Getting it from an entity

If you're using an entity from this plugin, or extending it they'll implement the PathBuilderTrait. This enables you to set and get the path builder on the entities.

You can't pass options to the entity when calling `Table::newEntity()`.

There are two workarounds for that issue. Either you'll have to set it manually on the entity instance:

```php
$entity->pathBuilder('PathBuilderName', ['options-array' => 'goes-here']);
$entity->path(); // Gets you the path in the used storage backend to the file
$entity->url(); // Gets you the URL to the file if possible
```

Or do it in the constructor of the entity. Pay attention to the two properties `_pathBuilderClass` and `_pathBuilderOptions`.
Set whatever you need here. If you're inheriting `FileStorage\Model\Entity\FileStorage` these options and the code below will be already present.

```php
namespace App\Model\Entity;

use PhpCollective\Infrastructure\Storage\PathBuilder\PathBuilderTrait;

class SomeEntityInYourApp extends Entity {

	use PathBuilderTrait;

	/**
	 * Path Builder Class.
	 *
	 * This is named $_pathBuilderClass because $_pathBuilder is already used by
	 * the trait to store the path builder instance.
	 *
	 * @param string|null
	 */
	protected $_pathBuilderClass = null;

	/**
	 * Path Builder options
	 *
	 * @param array
	 */
	protected $_pathBuilderOptions = [];

	/**
	 * Constructor
	 *
	 * @param array $properties hash of properties to set in this entity
	 * @param array $options list of options to use when creating this entity
	 */
	public function __construct(array $properties = [], array $options = []) {
		$options += [
			'pathBuilder' => $this->_pathBuilderClass,
			'pathBuilderOptions' => $this->_pathBuilderOptions
		];

		parent::__construct($properties, $options);

		if ($options['pathBuilder']) {
			$this->pathBuilder(
				$options['pathBuilder'],
				$options['pathBuilderOptions']
			);
		}
	}
}
```

If you want to use path builders depending on the kind of file or the identifier which is stored in the `model` field of the `file_storage` table, you can implement that logic as well there and use the entities data to determine the path builder class or options.

## Getting it using the storage helper

The storage helper is basically just a proxy to a path builder. The helper takes two configuration options:

 * **pathBuilder**: Name of the path builder to use.
 * **pathBuilderOptions**: The options passed to the path builders constructor.

Make sure that the options you pass and the path builder are the same you've used when you uploaded the file! Otherwise you end up with a different path!

```php
// Load the helper
$this->loadHelper('FileStorage.Storage', [
	'pathBuilder' => 'Base',
	// The builder options must match the options and builder class that were used to store the file!
	'pathBuilderOptions' => [
		'modelFolder' => true,
	]
]);

// Use it in your views
$url = $this->Storage->url($yourEntity);

// Change the path builder at run time
// Be careful, this will change the path builder instance in the helper!
$this->Storage->pathBuilder('SomePathBuilder', ['options' => 'here']);
```

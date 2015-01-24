<?php
namespace Burzum\FileStorage\Event;

use Cake\Event\Event;
use Burzum\FileStorage\Lib\StorageManager;

/**
 * @author Florian Krämer
 * @copy 2013 - 2014 Florian Krämer
 * @license MIT
 */
class ImageProcessingListener extends AbstractStorageEventListener {

/**
 * The adapter class
 *
 * @param null|string
 */
	public $adapterClass = null;

/**
 * Name of the storage table class name the event listener requires the table
 * instances to extend.
 *
 * This information is important to know when to use the event callbacks or not.
 *
 * Must be \FileStorage\Model\Table\FileStorageTable or \FileStorage\Model\Table\ImageStorageTable
 *
 * @var string
 */
	public $storageTableClass = '\FileStorage\Model\Table\ImageStorageTable';

/**
 * Constructor
 *
 * @param array $config
 * @return ImageProcessingListener
 */
	public function __construct(array $config = []) {
		$this->config($config);
	}

/**
 * Implemented Events
 *
 * @return array
 */
	public function implementedEvents() {
		return array(
			'ImageVersion.createVersion' => 'createVersions',
			'ImageVersion.removeVersion' => 'removeVersions',
			'ImageStorage.afterSave' => 'afterSave',
			'ImageStorage.afterDelete' => 'afterDelete',
			'FileStorage.ImageHelper.imagePath' => 'imagePath',
		);
	}

/**
 * Creates the different versions of images that are configured
 *
 * @param Model $Model
 * @param array $record
 * @param array $operations
 * @throws Exception
 * @return void
 */
	protected function _createVersions(Model $Model, $record, $operations) {
		$Storage = StorageManager::adapter($record['adapter']);
		$path = $this->_buildPath($record, true);
		$tmpFile = $this->_tmpFile($Storage, $path, TMP . 'image-processing');

		foreach ($operations as $version => $imageOperations) {
			$hash = $Model->hashOperations($imageOperations);
			$string = $this->_buildPath($record, true, $hash);

			if ($this->adapterClass === 'AmazonS3' || $this->adapterClass === 'AwsS3' ) {
				$string = str_replace('\\', '/', $string);
			}

			if ($Storage->has($string)) {
				return false;
			}

			try {
				$image = $Model->processImage($tmpFile, null, array('format' => $record['extension']), $imageOperations);
				$result = $Storage->write($string, $image->get($record['extension']), true);
			} catch (Exception $e) {
				$this->log($e->getMessage(), 'file_storage');
				unlink($tmpFile);
				throw $e;
			}
		}

		unlink($tmpFile);
	}

/**
 * Creates versions for a given image record
 *
 * @param Event $Event
 * @return void
 */
	public function createVersions(Event $Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$record = $Event->data['record'][$Model->alias];
			$this->_createVersions($Model, $record, $Event->data['operations']);
			$Event->stopPropagation();
		}
	}

/**
 * Removes versions for a given image record
 *
 * @param Event $Event
 */
	public function removeVersions(Event $Event) {
		$this->_removeVersions($Event);
	}

/**
 * Removes versions for a given image record
 *
 * @param Event $Event
 * @return void
 */
	protected function _removeVersions(Event $Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$Storage = $Event->data['storage'];
			$record = $Event->data['record'][$Model->alias];
			foreach ($Event->data['operations'] as $version => $operations) {
				$hash = $Model->hashOperations($operations);
				$string = $this->_buildPath($record, true, $hash);
				if ($this->adapterClass === 'AmazonS3' || $this->adapterClass === 'AwsS3' ) {
					$string = str_replace('\\', '/', $string);
				}
				try {
					if ($Storage->has($string)) {
						$Storage->delete($string);
					}
				} catch (Exception $e) {
					$this->log($e->getMessage(), 'file_storage');
				}
			}
			$Event->stopPropagation();
		}
	}

/**
 * afterDelete
 *
 * @param Event $Event
 * @return void
 */
	public function afterDelete(Event $Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$record = $Event->data['record'][$Model->alias];
			$string = $this->_buildPath($record, true, null);
			if ($this->adapterClass === 'AmazonS3' || $this->adapterClass === 'AwsS3' ) {
				$string = str_replace('\\', '/', $string);
			}
			try {
				$Storage = StorageManager::adapter($record['adapter']);
				if (!$Storage->has($string)) {
					return false;
				}
				$Storage->delete($string);
			} catch (Exception $e) {
				$this->log($e->getMessage(), 'file_storage');
				return false;
			}
			$operations = Configure::read('FileStorage.imageSizes.' . $record['model']);
			if (!empty($operations)) {
				$Event->data['operations'] = $operations;
				$this->_removeVersions($Event);
			}
			return true;
		}
	}

/**
 * afterSave
 *
 * @param Event $Event
 * @return void
 */
	public function afterSave(Event $Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$Storage = StorageManager::adapter($Model->data[$Model->alias]['adapter']);
			$record = $Model->data[$Model->alias];

			try {
				$id = $record[$Model->primaryKey];
				$filename = $Model->stripUuid($id);
				$file = $record['file'];
				$record['path'] = $this->fsPath('images' . DS . $record['model'], $id);

				if ($this->_config['preserveFilename'] === true) {
					$path = $record['path'] . $record['filename'];
				} else {
					$path = $record['path'] . $filename . '.' . $record['extension'];
				}

				if ($this->adapterClass === 'AmazonS3' || $this->adapterClass === 'AwsS3' ) {
					$path = str_replace('\\', '/', $path);
					$record['path'] = str_replace('\\', '/', $record['path']);
				}

				$result = $Storage->write($path, file_get_contents($file['tmp_name']), true);

				$data = $Model->save(array($Model->alias => $record), array(
					'validate' => false,
					'callbacks' => false
				));

				$operations = Configure::read('FileStorage.imageSizes.' . $record['model']);
				if (!empty($operations)) {
					$this->_createVersions($Model, $record, $operations);
				}

				$Model->data = $data;
			} catch (Exception $e) {
				$this->log($e->getMessage(), 'file_storage');
			}
		}
	}

/**
 * Generates the path the image url / path for viewing it in a browser depending on the storage adapter
 *
 * @param Event $Event
 * @throws RuntimeException
 * @return void
 */
	public function imagePath(Event $Event) {
		extract($Event->data);

		if (!isset($Event->data['image']['adapter'])) {
			throw new \RuntimeException(__d('file_storage', 'No adapter config key passed!'));
		}

		$adapterClass = $this->getAdapterClassName($Event->data['image']['adapter']);
		$buildMethod = '_build' . $adapterClass . 'Path';

		if (method_exists($this, $buildMethod)) {
			return $this->$buildMethod($Event);
		}

		throw new \RuntimeException(__d('file_storage', 'No callback image url callback implemented for adapter %s', $adapterClass));
	}

/**
 * Builds an url to the given image
 *
 * @param Event $Event
 * @return void
 */
	protected function _buildLocalPath(Event $Event) {
		extract($Event->data);
		$path = $this->_buildPath($image, true, $hash);
		$Event->data['path'] = '/' . $path;
		$Event->stopPropagation();
	}

/**
 * Wrapper around the other AmazonS3 Adapter
 *
 * @param Event $Event
 * @see ImageProcessingListener::_buildAmazonS3Path()
 */
	protected function _buildAwsS3Path($Event) {
		$this->_buildAmazonS3Path($Event);
	}

/**
 * Builds an url to the given image for the amazon s3 adapter
 *
 * http(s)://<bucket>.s3.amazonaws.com/<object>
 * http(s)://s3.amazonaws.com/<bucket>/<object>
 *
 * @param Event $Event
 * @return void
 */
	protected function _buildAmazonS3Path(Event $Event) {
		extract($Event->data);

		$path = $this->_buildPath($image, true, $hash);
		$image['path'] = '/' . $path;

		$config = StorageManager::config($Event->data['image']['adapter']);
		$bucket = $config['adapterOptions'][1];
		if (!empty($config['cloudFrontUrl'])) {
			$cfDist = $config['cloudFrontUrl'];
		} else {
			$cfDist = null;
		}

		$http = 'http';
		if (!empty($Event->data['options']['ssl']) && $Event->data['options']['ssl'] === true) {
			$http = 'https';
		}

		$image['path'] = str_replace('\\', '/', $image['path']);
		$bucketPrefix = !empty($Event->data['options']['bucketPrefix']) && $Event->data['options']['bucketPrefix'] === true;

		$Event->data['path'] = $this->_buildCloudFrontDistributionUrl($http, $image['path'], $bucket, $bucketPrefix, $cfDist);
		$Event->stopPropagation();
	}

/**
 * Builds an url to serve content from cloudfront
 *
 * @param string $protocol
 * @param string $image
 * @param string $bucket
 * @param string null $bucketPrefix
 * @param string $cfDist
 * @return string
 */
	protected function _buildCloudFrontDistributionUrl($protocol, $image, $bucket, $bucketPrefix = null, $cfDist = null) {
		$path = $protocol . '://';
		if ($cfDist) {
			$path .= $cfDist;
		} else {
			if ($bucketPrefix) {
				$path .= $bucket . '.s3.amazonaws.com';
			} else {
				$path .= 's3.amazonaws.com/' . $bucket;
			}
		}
		$path .= $image;

		return $path;
	}

/**
 * Builds a path to a file
 *
 * @param array $record
 * @param boolean $extension
 * @param string $hash
 * @return string
 */
	protected function _buildPath($record, $extension = true, $hash = null) {
		if ($this->_config['preserveFilename'] === true) {
			if (!empty($hash)) {
				$path = $record['path'] . preg_replace('/\.[^.]*$/', '', $record['filename']) . '.' . $hash . '.' . $record['extension'];
			} else {
				$path = $record['path'] . $record['filename'];
			}
		} else {
			$path = $record['path'] . str_replace('-', '', $record['id']);
			if (!empty($hash)) {
				$path .= '.' . $hash;
			}
			if ($extension == true) {
				$path .= '.' . $record['extension'];
			}
		}

		if ($this->adapterClass === 'AmazonS3' || $this->adapterClass === 'AwsS3' ) {
			return str_replace('\\', '/', $path);
		}

		return $path;
	}

/**
 * Gets the adapter class name from the adapter configuration key
 *
 * @param string
 * @return void
 */
	public function getAdapterClassName($adapterConfigName) {
		$config = StorageManager::config($adapterConfigName);

		switch ($config['adapterClass']) {
			case '\Gaufrette\Adapter\Local':
				$this->adapterClass = 'Local';
				return $this->adapterClass;
			case '\Gaufrette\Adapter\AwsS3':
				$this->adapterClass = 'AwsS3';
				return $this->adapterClass;
			case '\Gaufrette\Adapter\AmazonS3':
				$this->adapterClass = 'AwsS3';
				return $this->adapterClass;
			case '\Gaufrette\Adapter\AwsS3':
				$this->adapterClass = 'AwsS3';
				return $this->adapterClass;
			default:
				return false;
		}
	}

}
<?php
/**
 * Copyright 2011-2016, Florian Krämer
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * Copyright 2011-2016, Florian Krämer
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Burzum\Imagine\Model\Behavior;

use Burzum\Imagine\Lib\ImagineUtility;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Imagine\Image\AbstractImage;

/**
 * CakePHP Imagine Plugin
 */
class ImagineBehavior extends Behavior {

/**
 * Default settings array
 *
 * @var array
 */
	protected $_defaultConfig = [
		'engine' => 'Gd',
		'processorClass' => '\Burzum\Imagine\Lib\ImageProcessor'
	];

	/**
	 * Class name of the image processor to use.
	 *
	 * @var string
	 */
	protected $_processorClass;

	/**
	 * Constructor
	 *
	 * @param Table $table The table this behavior is attached to.
	 * @param array $settings The settings for this behavior.
	 */
	public function __construct(Table $table, array $settings = []) {
		parent::__construct($table, $settings);

		$class = '\Imagine\\' . $this->config('engine') . '\Imagine';
		$this->Imagine = new $class();
		$this->_table = $table;
		$processorClass = $this->config('processorClass');
		$this->_processor = new $processorClass($this->config());
	}

	/**
	 * Returns the image processor object.
	 *
	 * @return mixed
	 */
	public function getImageProcessor() {
		return $this->_processor;
	}

	/**
	 * Get the imagine object
	 *
	 * @deprecated Call ImagineBehavior->getImageProcessor()->imagine() instead.
	 * @return Imagine object
	 */
	public function imagineObject() {
		return $this->_processor->imagine();
	}

	/**
	 * Delegate the calls to the image processor lib.
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		if (method_exists($this->_processor, $args)) {
			return call_user_func_array([$this->_processor, $method], $args);
		}
	}

	/**
	 * Loads an image and applies operations on it.
	 *
	 * Caching and taking care of the file storage is NOT the purpose of this method!
	 *
	 * @param string|\Imagine\Image\AbstractImage $ImageObject
	 * @param string $output
	 * @param array $imagineOptions
	 * @param array $operations
	 * @throws \InvalidArgumentException
	 * @return bool
	 */
	public function processImage($image, $output = null, $imagineOptions = [], $operations = []) {
		if (is_string($image)) {
			$this->_processor->open($image);
			$image = $this->_processor->image();
		}
		if (!$image instanceof AbstractImage) {
			throw new \InvalidArgumentException('An instance of `\Imagine\Image\AbstractImage` is required, you passed `%s`!', get_class($image));
		}

		$event = $this->_table->dispatchEvent('ImagineBehavior.beforeApplyOperations', compact('image', 'operations'));
		if ($event->isStopped()) {
			return $event->result;
		}

		$data = $event->data();
		$this->_applyOperations(
			$data['operations'],
			$data['image']
		);

		$event = $this->_table->dispatchEvent('ImagineBehavior.afterApplyOperations', $data);
		if ($event->isStopped()) {
			return $event->result;
		}

		if ($output === null) {
			return $image;
		}

		return $this->_processor->save($output, $imagineOptions);
	}

	/**
	 * Applies the actual image operations to the image.
	 *
	 * @param array $operations
	 * @param array $image
	 * @throws \BadMethodCallException
	 * @return void
	 */
	protected function _applyOperations($operations, $image) {
		foreach ($operations as $operation => $params) {
			$event = $this->_table->dispatchEvent('ImagineBehavior.applyOperation', compact('image', 'operations'));
			if ($event->isStopped()) {
				continue;
			}
			if (method_exists($this->_table, $operation)) {
				$this->_table->{$operation}($image, $params);
			} elseif (method_exists($this->_processor, $operation)) {
				$this->_processor->{$operation}($params);
			} else {
				throw new \BadMethodCallException(__d('imagine', 'Unsupported image operation `{0}`!', $operation));
			}
		}
	}

	/**
	 * Turns the operations and their params into a string that can be used in a file name to cache an image.
	 *
	 * Suffix your image with the string generated by this method to be able to batch delete a file that has versions of it cached.
	 * The intended usage of this is to store the files as my_horse.thumbnail+width-100-height+100.jpg for example.
	 *
	 * So after upload store your image meta data in a db, give the filename the id of the record and suffix it
	 * with this string and store the string also in the db. In the views, if no further control over the image access is needed,
	 * you can simply direct-link the image like $this->Html->image('/images/05/04/61/my_horse.thumbnail+width-100-height+100.jpg');
	 *
	 * @param array $operations Imagine image operations
	 * @param array $separators Optional
	 * @param bool $hash
	 * @return string Filename compatible String representation of the operations
	 * @link http://support.microsoft.com/kb/177506
	 */
	public function operationsToString($operations, $separators = [], $hash = false) {
		return ImagineUtility::operationsToString($operations, $separators, $hash);
	}

	/**
	 * hashImageOperations
	 *
	 * @param array $imageSizes
	 * @param int $hashLength
	 * @return string
	 */
	public function hashImageOperations($imageSizes, $hashLength = 8) {
		return ImagineUtility::hashImageOperations($imageSizes, $hashLength = 8);
	}

	public function getImageSize($Image) {
		return $this->_processor->getImageSize($Image);
	}

}

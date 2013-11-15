<?php

namespace li3_frontender\extensions\helper;

use li3_frontender\Manifest;

use RuntimeException;
use \lithium\core\Environment;
use \lithium\core\Libraries;
use \lithium\util\String;

class Assets extends \lithium\template\Helper {

	protected $_config;
	protected $_paths;
	protected $_production;
	protected $styles;
	protected $scripts;

	public function _init(){
		parent::_init();
		$this->config = Libraries::get('li3_frontender');
	}

	/**
	 * Takes a manifest name and:
	 * 1. compiles less files to css
	 * 2. mangles all files in the manifest (if $config['mangle'] is true)
	 * 3. returns one or more style link tags (based on mangle setting)
	 *
	 * @param  string $manifest name of manifest
	 * @return string           stylesheet `<link>` tag(s)
	 */
	public function style($manifest) {
		$files = $this->build('css', $manifest);
		$tags = array();
		foreach($files as $file) {
			$tags[] = $this->_context->helper('html')->style($file);
		}
		return implode("\n", $tags);
	}

	/**
	 * Takes a manifest name and:
	 * 1. compiles coffee files to js
	 * 2. mangles all files in the manifest (if $config['mangle'] is true)
	 * 3. returns one or more script tags (based on mangle setting)
	 *
	 * @param  string $manifest name of manifest
	 * @return string           javascript script tag(s)
	 */
	public function script($manifest) {
		$files = $this->build('js', $manifest);
		$tags = array();
		foreach($files as $file) {
			$tags[] = $this->_context->helper('html')->script($file);
		}
		return implode("\n", $tags);
	}

	/**
	 * Initializes a new Manifest and calls compile() on it.
	 *
	 * @param  string $type     either 'js' or 'css'
	 * @param  string $manifest name of manifest
	 * @return array            filenames of assets
	 */
	protected function build($type, $manifest) {
		$manifest = new Manifest($type, $manifest);
		$simulate = $this->config['batch_only'];

		// only compile if rev/cache string has changed
		if(!$simulate) {
			$sameRev = ($this->getRev($type) === $this->config['cacheString']);
			if($sameRev) $simulate = true;
		} else {
			$sameRev = false;
		}

		$files = $manifest->compile($simulate);
		$relative = array();
		foreach($files as $file) {
			if(!$this->config['mangle']) $file .= "?" . $this->config['cacheString'];
			$relative[] = substr($file, strlen($this->config['root']));
		}

		if(!$sameRev) $this->setRev($type);

		return $relative;
	}

	protected function getRev($type) {
		$rev_filename = $this->config['root'] . "/$type/compiled/REV";
		if(file_exists($rev_filename)) {
			return trim(file_get_contents($rev_filename));
		} else {
			Manifest::$processed = array(); // need to reset this since the directory was deleted
		}
	}

	protected function setRev($type) {
		$rev_filename = $this->config['root'] . "/$type/compiled/REV";
		file_put_contents($rev_filename, $this->config['cacheString']);
	}


}
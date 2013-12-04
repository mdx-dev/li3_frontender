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
		$this->debug = $this->config['debug'];
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
		Manifest::resetProcessed();
		$manifest = new Manifest($type, $manifest);
		$manifest->timestamp = !$this->config['batch_only'];
		$manifest->verbose = $this->debug;

		if($this->config['batch_only']) {
			$files = array($this->mangledFilename());
		} else {
			$files = $manifest->filenameMappings();
			$compile = array();
			foreach($files as $in => $out) {
				if(!file_exists($out)) {
					$this->cleanOldRev($type, $out);
					$compile[] = $in;
				}
			}
			if(count($compile) > 0) {
				if($this->debug) echo "<pre>";
				$manifest->compile($compile); // compile!
				if($this->debug) echo "</pre>";
			}
		}

		$relative = array();
		foreach($files as $file) {
			$relative[] = substr($file, strlen($this->config['root']));
		}

		return $relative;
	}

	protected function cleanOldRev($type, $file) {
		$pattern = preg_replace("/_\d+\.(.+)$/", "_\\d+.\\1", $file);
		$pattern = '/'.preg_replace("/\\//", "\\/", $pattern).'/';
		$files = glob($this->config['root'] . "/$type/compiled/**");
		foreach($files as $file){
			if(preg_match($pattern, $file) && is_file($file)) {
				if($this->debug) { echo "<pre>removing: $file</pre>"; }
				unlink($file);
			}
		}
	}

}

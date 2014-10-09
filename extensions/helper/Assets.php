<?php

namespace li3_frontender\extensions\helper;

use li3_frontender\Manifest;

class Assets extends \lithium\template\Helper {

	/**
	 * Takes a manifest name and:
	 * 1. Generates the corresponding CSS files based on rules of $this->_build
	 * 2. Returns one or more style link tags.
	 *
	 * @param  string $manifest name of manifest
	 * @return string stylesheet `<link>` tag(s)
	 */
	public function style($manifest) {
		$files = $this->style_list($manifest);
		return $this->_html('style', $files);
	}

	/**
	 * Takes a manifest name and:
	 * 1. Generates the corresponding CSS files based on rules of $this->_build
	 * 2. Returns array of CSS files.
	 *
	 * @param  string $manifest name of manifest
	 * @return array stylesheet files
	 */
	public function style_list($manifest) {
		return $this->_build('css', $manifest);
	}

	/**
	 * Takes a manifest name and:
	 * 1. Compiles coffee files to js.
	 * 2. Merges all files in the manifest (if $config['mergeAssets'] is true).
	 * 3. Returns one or more script tags.
	 *
	 * @param  string $manifest name of manifest
	 * @return string javascript script tag(s)
	 */
	public function script($manifest) {
		$files = $this->script_list($manifest);
		return $this->_html('script', $files);
	}

	/**
	 * Takes a manifest name and:
	 * 1. Generates the corresponding JS files based on rules of $this->_build
	 * 2. Returns array of JS files.
	 *
	 * @param  string $manifest name of manifest
	 * @return array js files
	 */
	public function script_list($manifest) {
		return $this->_build('js', $manifest);
	}

	/**
	 * Takes a manifest name and:
	 * 1. Compiles less and coffee files
	 * 2. Merges all files in the manifest (if $config['mergeAssets'] is true).
	 * 3. Returns array of files
	 *
	 * @param  string $type of asset
	 * @param  string $manifest name of manifest
	 * @return array of files
	 */
	protected function _build($type, $manifest) {
		$manifest = Manifest::load($type, $manifest);
		return $manifest->build();
	}

	/**
	 * Takes an html method and list of files
	 *
	 * @param  string $method to be called on html helper
	 * @param  array $files  array of files
	 * @return string with baked html
	 */
	protected function _html($method, $files) {
		$tags = array();
		foreach ($files as $file) {
			$tags[] = $this->_context->helper('html')->$method($file);
		}
		return implode("\n", $tags);
	}

}

?>

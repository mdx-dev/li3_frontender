<?php

namespace li3_frontender\extensions\helper;

use li3_frontender\Manifest;

class Assets extends \lithium\template\Helper {

	/**
	 * Takes a manifest name and:
	 * 1. Compiles less files to css.
	 * 2. Merges all files in the manifest (if $config['mergeAssets'] is true).
	 * 3. Splits files by IE selector count limint (if $config['blessCss'] is true).
	 * 4. Returns one or more style link tags.
	 *
	 * @param  string $manifest name of manifest
	 * @return string stylesheet `<link>` tag(s)
	 */
	public function style($manifest) {
		$manifest = Manifest::load('css', $manifest);
		$files = $manifest->build();
		$tags = array();
		foreach ($files as $file) {
			$tags[] = $this->_context->helper('html')->style($file);
		}
		return implode("\n", $tags);
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
		$manifest = Manifest::load('js', $manifest);
		$files = $manifest->build();
		$tags = array();
		foreach ($files as $file) {
			$tags[] = $this->_context->helper('html')->script($file);
		}
		return implode("\n", $tags);
	}

}

?>

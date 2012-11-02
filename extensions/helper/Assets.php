<?php

namespace li3_frontender\extensions\helper;

use RuntimeException;
use \lithium\core\Environment;
use \lithium\core\Libraries;
use \lithium\util\String;

// Assetic Classes
use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Assetic\Asset\GlobAsset;

// Assetic Filters
use Assetic\Filter\CoffeeScriptFilter;
use Assetic\Filter\LessphpFilter;
use Assetic\Filter\Yui;

class Assets extends \lithium\template\Helper {

	protected $_config;
	protected $_paths;
	protected $_production;
	protected $styles;
	protected $scripts;

	public function _init(){

		parent::_init();

		$this->_config = Libraries::get('li3_frontender');

		$defaults = array(
			'compress' => false,
			'queryString' => true,
			'assets_root' => LITHIUM_APP_PATH . "/webroot",
			'production' => (Environment::get() == 'production'),
			'locations' => array(
				'node' => '/usr/bin/node',
				'coffee' => '/usr/bin/coffee'
			)
		);

		$this->_config += $defaults;

		$this->_production = $this->_config['production'];

		// remove extra slash if it was included in the library config
		$this->_config['assets_root'] = (substr($this->_config['assets_root'], -1) == "/") 
			? substr($this->_config['assets_root'], 0, -1) : $this->_config['assets_root'];

		$this->_paths['styles'] =  $this->_config['assets_root'] . "/css/";
		$this->_paths['scripts'] = $this->_config['assets_root'] . "/js/";

	}

	/**
	 * Takes styles and parses them with appropriate filters and, if in 
	 * production, merges them all into a single file.
	 * @param  array $stylesheets list of stylesheets
	 * @return string              stylesheet `<link>` tag
	 */
	public function style($stylesheets) {

		$options = array(
			'type' => 'css',
			'filters' => array( new LessphpFilter() ),
			'path' => $this->_paths['styles']
		);

		if(gettype($stylesheets) == 'string') {
			$options['manifest'] = $stylesheets;						
			$stylesheets = $this->_config['manifests']['css'][$stylesheets];
		}

		$this->_runAssets($stylesheets, $options);

	}

	/**
	 * This is pretty much identical to the style method above
	 * I plan to consolidate these two.
	 */
	public function script($scripts) {

		$options = array(
			'type' => 'js',
			'filters' => array( new CoffeeScriptFilter($this->_config['locations']['coffee'], $this->_config['locations']['node']) ),
			'path' => $this->_paths['scripts']
		);

		if(gettype($scripts) == 'string') {
			$options['manifest'] = $scripts;
			$scripts = $this->_config['manifests']['js'][$scripts];
		}

		$this->_runAssets($scripts, $options);

	}

	/**
	 * Method used to determine if an asset needs to be cached or timestamped.
	 * Makes appropriate calls based on this.
	 * @param  array  $files   [description]
	 * @param  array  $options [description]
	 * @return [type]          [description]
	 */
	private function _runAssets(array $files = array(), array $options = array()) {

		$this->styles =  new AssetCollection();
		$this->scripts = new AssetCollection();

		if($this->_config['compress'] OR $this->_production){
			$this->styles->ensureFilter( new Yui\CssCompressorFilter( YUI_COMPRESSOR ) );
			$this->scripts->ensureFilter( new Yui\JsCompressorFilter( YUI_COMPRESSOR ) );
		}

		$filename = ""; // will store concatenated filename

		$stats = array('modified' => 0, 'size' => 0); // stores merged file stats

		// request type
		$type = ($options['type'] == 'css') ? 'styles' : 'scripts';

		// loop over the sheets that were passed and run them thru Assetic
		foreach($files as $file){
			
			$_filename = $file;
			$path = $options['path'];
			
			// build filename if not a less file
			if(($isSpecial = $this->specialExt($file)) 
				OR (preg_match("/(.css|.js)$/is", $file))){
				$path .= $file;
			} else {
				$path .= "{$file}.{$options['type']}";
				$_filename = "{$file}.{$options['type']}";
			}

			// ensure file exists, if so set stats
			if(file_exists($path)){

				$_stat = stat($path);

				$stats['modified'] += $_stat['mtime'];
				$stats['size'] += $_stat['size'];
				$stats[$path]['modified'] = $_stat['mtime'];
				$stats[$path]['size'] = $_stat['size'];

			} else {

				throw new RuntimeException("The {$options['type']} file '{$path}' does not exist");

			}

			$filters = array();

			// its a less or coffee file
			if($isSpecial){

				$path = $options['path'] . $file;

				$filters +=  $options['filters'];

			} else {

				// If we're not in production and we're not compressingthen we 
				// dont need to cache static css assets
				if(!$this->_production AND !$this->_config['compress']){

					$method = substr($type, 0, -1);
					echo $this->_context->helper('html')->{$method}("{$_filename}?{$stats[$path]['modified']}") . "\n\t";
					continue;

				}

			}

			$filename .= $file;

			// add asset to assetic collection
			$this->{$type}->add( 
				new FileAsset( $path , $filters )
			);

		} 
		
		// If in production merge files and server up a single stylesheet
		if($this->_production){

			echo $this->buildHelper($filename, $this->{$type}, array_merge($options, array('stats' => $stats)));

		} else {

			// not production so lets serve up individual files (better debugging)
			foreach($this->{$type} as $leaf){

				$fullPath = "{$leaf->getSourceRoot()}/{$leaf->getSourcePath()}";
				$stat = isset($stats[$fullPath]) ? $stats[$fullPath] : false;

				if ($stat) echo $this->buildHelper($leaf->getSourcePath(), $leaf, array_merge($options, array('stats' => $stats)));

			}

		}

	}

	/**
	 * Check cache and spits out the style/script link
	 * @param  string $filename name of the cache file
	 * @param  object $content  Assetic style object
	 * @param  array $options    file stats and helper type
	 * @return string           lithium link helper
	 */
	private function buildHelper($filename, $content, array $options = array()){
		// if it is production and using manifest, name file according to manifest
		if($this->_production && isset($options['manifest']) && !empty($options['manifest'])){
			$filename = $options['manifest'];
		}

		// just in case filename is too long
		if(strlen($filename) > 250){
			$filename = substr($filename, 0, 250);
		}

		// switch between query string or unique filename
		if(isset($this->_config['queryString']) && $this->_config['queryString'] == true){
			$filename = $filename.'.'.$options['type'];
			$link = $filename.'?'.String::hash($options['stats']['size'].$options['stats']['modified'], array('type' => 'sha1'));
		} else {
			$filename = String::hash($filename, array('type' => 'sha1'));			
			$filename = "{$filename}_{$options['stats']['size']}_{$options['stats']['modified']}.{$options['type']}";		
			$link = $filename;
		}


		// If Cache doesn't exist then we recache
		// Recache removes old caches and adds the new
		// ---
		// If you change a file in the styles added then a recache is made due
		// to the fact that the file stats changed
		$cached_path = FRONTENDER_WEBROOT_DIR . "/{$options['type']}/compiled/{$filename}";
		if(!file_exists($cached_path) || !$cached = file_get_contents($cached_path)){
			$this->setCache($filename, $content->dump(), array('location' => $options['type']));			
		}

		// pass single stylesheet link
		switch($options['type']){
			case 'css':
				return $this->_context->helper('html')->style("compiled/{$link}") . "\n\t";
			case 'js':
				return $this->_context->helper('html')->script("compiled/{$link}") . "\n\t";
		}

	}

	/**
	 * Rebuild asset cache file
	 * @param  string $filename The name of the file to cache or test caching on
	 * @param  string $content  File contents to be cached
	 * @param  array  $options  Options to handle cache length, location, so on.
	 */
	private function setCache($filename, $content, $options = array()){

		// Create css cache dir if it doesnt exist.
		// FIXME This is janky
		if (!is_dir($cache_location = FRONTENDER_WEBROOT_DIR . "/" . $options['location'] . "/compiled")) {
			mkdir($cache_location, 0755, true);
		}

		$defaults = array(
			'length' => '+1 year',
			'location' => 'templates'
		);

		$options += $defaults;

		$name_sections = explode('_', $filename);

		$like_files = $name_sections[0];


		// loop thru cache and delete old cache file
		if (!$this->_production && $handle = opendir($cache_location)) {

			while (false !== ($oldfile = readdir($handle))) {

				if(preg_match("/^{$like_files}/", $oldfile)){

					file_exists("{$options['location']}/{$oldfile}") && unlink("{$options['location']}/{$oldfile}");

				}

			}

			closedir($handle);

		}

		file_put_contents("{$cache_location}/{$filename}", $content);

	}

	private function specialExt($filename){

		if(preg_match("/(.less|.coffee)$/is", $filename, $matches)){
			$ext = $matches[0];
		} else {
			$ext = false;
		}

		return $ext;
	}

}
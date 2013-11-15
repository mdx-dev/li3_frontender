<?php

namespace li3_frontender;

use lithium\core\Libraries;
use lithium\core\Environment;
use \Exception;

class Manifest {

	public $type;               // either 'js' or 'css'
	public $name;               // name of manifest, configured in the app
	public $filenames;          // asset filenames in this manifest
	public $in_path;            // root directory where source files can be found, usually app/webroot/js or app/webroot/css
	public $out_path;           // root directory where target files are written, usually app/webroot/js/compiled or app/webroot/css/compiled
	public $verbose = false;    // set to true to get verbose logging about which assets are being built
	public $mangle = false;     // set to true to combine individual assets and minify

	// list of assets that have already been processed
	public static $processed = array();

	/**
	 * Returns an array of manifest objects based on the config.
	 *
	 * @return array of Manifest objects
	 */
	public static function all() {
		$config = Libraries::get('li3_frontender');

		$all = array();
		foreach($config['manifests'] as $type => $manifests){
			foreach($manifests as $manifest => $filenames) {
				$all[] = new Manifest($type, $manifest);
			}
		}

		return $all;
	}

	/**
	 * Constructs a new Manifest.
	 *
	 * @param  string $type either 'js' or 'css'
	 * @param  string $name name of manifest
	 * @return object Manifest
	 */
	public function __construct($type, $name) {
		$this->type = $type;
		$this->name = $name;

		$this->less_binary = dirname(dirname(__DIR__)) . "/libraries/_source/leafo/lessphp/plessc";
		$this->coffee_binary = "coffee";

		$this->config = Libraries::get('li3_frontender');
		if(!isset($this->config['manifests'][$type][$name])) {
			throw new Exception("manifest $type/$name not found");
		}
		$this->filenames = $this->config['manifests'][$type][$name];
		$this->mangle = $this->config['mangle'];

		if(!isset($this->in_path)) $this->in_path = $this->inPath();
		if(!preg_match('/\/$/', $this->in_path)) $this->in_path .= '/';

		if(!isset($this->out_path)) $this->out_path = $this->outPath();
		if(!preg_match('/\/$/', $this->out_path)) $this->out_path .= '/';
	}

	/**
	 * Compiles all assets in a manifest.
	 *
	 * @param boolean $simulate set to true to skip compilation and mangling steps (use this in production)
	 * @return array if($this->mangle) array with single path to combined, mangled asset
	 *               otherwise, absolute paths to individual built assets
	 */
	public function compile($simulate=false) {
		$in_manifest = array();
		if($this->verbose) echo "{$this->type} manifest '{$this->name}'\n";
		foreach($this->filenameMappings() as $in => $out) {
			if(!$simulate) {
				$this->copyOrCompile($in, $out);
			}
			static::$processed[] = $out;
			$in_manifest[] = $out;
		}
		if($this->mangle) {
			$mangled_filename = $this->mangledFilename();
			if(!$simulate) $this->merge($mangled_filename, $in_manifest);
			return array($mangled_filename);
		} else {
			return $in_manifest;
		}
	}

	/**
	 * Returns the combined, minified (mangled) filename
	 * for this manifest.
	 *
	 * @return string filename
	 */
	public function mangledFilename() {
		$rev = $this->cacheString();
		return "{$this->out_path}{$this->name}{$rev}.{$this->type}";
	}

	/**
	 * Returns an associative array mapping source to destination
	 * for each individual asset in this manifest.
	 *
	 * @return array with source => destination pairs
	 */
	public function filenameMappings() {
		$mappings = array();
		foreach($this->filenames as $filename) {
			$filename = $this->appendFileType($filename, true);
			$in = $this->in_path . $filename;
			$out = $this->appendFileType($this->out_path . $filename);
			$mappings[$in] = $out;
		}
		return $mappings;
	}

	protected function copyOrCompile($in, $out) {
		if($this->needsCompileStep($in)) {
			$start = microtime(true);
			if(!in_array($out, static::$processed)) {
				$this->compileFile($in, $out);
			}
			$ms = (int)((microtime(true) - $start) * 1000);
			if($this->verbose) echo("  compiled $out ($ms msec)\n");
		} else {
			$this->copy($in, $out);
		}
	}

	protected function cacheString() {
		return $this->config['cacheString'];
	}

	protected function outPath() {
		return $this->inPath() . "/compiled";
	}

	protected function inPath() {
		return $this->config['root'] . "/" . $this->type;
	}

	protected function appendFileType($path, $allow_any=false) {
		$ftype = pathinfo($path, PATHINFO_EXTENSION);
		$allowed = array('js', 'css');
		if($allow_any) $allowed = array_merge($allowed, array('coffee', 'less'));
		if(!$ftype || !in_array($ftype, $allowed)) {
			$path .= '.' . $this->type;
		}
		return $path;
	}

	protected function needsCompileStep($filename) {
		return preg_match("/\.coffee|\.less/", $filename);
	}

	protected function mkdir($path) {
		$info = pathinfo($path);
		$directory = $info['dirname'];
		`mkdir -p $directory`;
	}

	protected function compileFile($path, $destination) {
		$info = pathinfo($path);
		$ftype = $info['extension'];
		$ret = 0;
		$result = null;

		switch($ftype) {
		case 'coffee':
			$cmd = "{$this->coffee_binary} -p -c {$path} 2>&1";
			exec($cmd, $result, $ret);
			break;
		case 'less':
			$cmd = "{$this->less_binary} {$path} 2>&1";
			exec($cmd, $result, $ret);
			break;
		default:
			echo("  unknown file type: $destination\n");
			exit(1);
		}

		if($ret != 0) {
			echo("    ERROR - could not compile asset.\n");
			echo("    Try running: $cmd\n");
			exit(1);
		} else if($result) {
			$result = implode($result, "\n");
			$this->mkdir($destination);
			file_put_contents($destination, $result);
		}
	}

	protected function copy($path, $destination) {
		$this->mkdir($destination);
		system("cp $path $destination", $ret);
		if($ret != 0) {
			echo("error copying $path to $destination\n");
			exit(1);
		}
	}

	protected function merge($path, $filenames) {
		$start = microtime(true);
		switch($this->type) {
		case 'js':
			$cmd = "uglifyjs " . implode($filenames, ' ') . " -o $path -c -m 2>/dev/null";
			system($cmd, $ret);
			break;
		case 'css':
			$cmd = "uglifycss " . implode($filenames, ' ') . " 2>/dev/null > $path";
			system($cmd, $ret);
			break;
		default:
			echo("  unknown type: {$this->type}\n");
			exit(1);
		}
		$ms = (int)((microtime(true) - $start) * 1000);
		if($ret != 0) {
			echo("  There was an error merging files.\n");
			echo("  Try running: $cmd\n");
			exit(1);
		} else if($this->verbose) {
			echo("  wrote $path ($ms msec)\n");
		}
	}

}

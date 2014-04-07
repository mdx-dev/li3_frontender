<?php

namespace li3_frontender;

use lithium\core\Libraries;
use lithium\net\http\Media;
use \Exception;

class Manifest {

	public $type;      // either 'js' or 'css'
	public $name;      // name of manifest, configured in the app
	public $filenames; // asset filenames in this manifest

	public $bless_binary  = 'blessc';
	public $coffee_binary = 'coffee';
	public $less_binary   = 'lessc';

	/**
	 * Returns an array of manifest objects based on the config.
	 *
	 * @param array $options options to pass to the manifest
	 * @return array of Manifest objects
	 */
	public static function all(array $options = array()) {
		$config = Libraries::get('li3_frontender') + array(
			'manifests' => array()
		);

		$all = array();
		foreach ($config['manifests'] as $type => $manifests) {
			foreach ($manifests as $manifest => $filenames) {
				$all[] = Manifest::load($type, $manifest, $options);
			}
		}

		return $all;
	}

	/**
	 * Populates a new Manifest with the assets listed in the `manifests`
	 * configuration array.
	 *
	 * @param string $type either 'js' or 'css'
	 * @param string $name name of manifest
	 * @param array $options options to pass to the manifest
	 * @return Manifest
	 */
	public static function load($type, $name, array $options = array()) {
		$config = Libraries::get('li3_frontender') + array(
			'manifests' => array()
		);
		if (!isset($config['manifests'][$type][$name])) {
			throw new Exception("Manifest `$type/$name` not found.");
		}
		$assets = $config['manifests'][$type][$name];
		return new Manifest($type, $name, $assets, $options);
	}

	/**
	 * Constructs a new Manifest.
	 *
	 * @param string $type either 'js' or 'css'
	 * @param string $name a name for the manifest.
	 * @param mixed $files an asset name or array of asset names.
	 * @param array $options options which override Li3 config
	 * @return object Manifest
	 */
	public function __construct($type, $name, $files = array(), array $options = array()) {
		$defaults = array(
			'blessCss'                     => false,
			'cacheBuster'                  => 'modtime',
			'cacheCheckLessImports'        => false,
			'cacheCheckManifestSameAssets' => true,
			'cacheString'                  => 'bust',
			'manifests'                    => array(),
			'mergeAssets'                  => false,
			'purgeStale'                   => true,
			'root'                         => null,
			'verbose'                      => false,
		);
		$this->config = $options + Libraries::get('li3_frontender') + $defaults;

		$this->type = $type;
		$this->name = $name;
		$this->filenames = is_array($files) ? $files : array($files);
		$this->filenames = array_unique($this->filenames); // In the rare case of duplicates.

		// Default to the Li3 webroot.
		if (is_null($this->config['root'])) {
			$this->config['root'] = Media::webroot();
		}
	}

	public function build() {
		$compiled = array(); // Stores full paths of compiled/copied assets.

		if ($this->requiresFullAssetNames()) {
			$this->filenames = $this->stepResolveAssets($this->filenames);
		}

		if ($this->config['mergeAssets']) {
			$manifestName = $this->manifestCompiledName();
			$manifestOutPath = $this->outPath() . '/' . $manifestName;
		}

		// Compile
		if (!$this->config['mergeAssets'] || !file_exists($manifestOutPath)) {
			if ($this->config['verbose']) echo "{$this->type} manifest `{$this->name}`\n";
			$compiled = $this->stepCompile($this->filenames);
		}

		// Uglify and merge
		if ($this->config['mergeAssets']) {
			if (count($compiled)) {
				if ($this->config['purgeStale']) $this->purgeManifest();
				$this->merge($manifestOutPath, $compiled);
			}
			$compiled = array($manifestOutPath);
		}

		// Bless
		if ('css' === $this->type && $this->config['blessCss']) {
			$blessed = array();
			foreach ($compiled as $inPath) {
				if (!$outPaths = $this->findBlessedFiles($inPath)) {
					$outPaths = $this->blessFile($inPath);
				}
				$blessed = array_merge($blessed, $outPaths);
			}
			$compiled = $blessed;
		}

		return $this->relativePaths($compiled);
	}

	/**
	 * Compiles an array of individual assets.
	 *
	 * @param array $assets asset names
	 * @return array of paths to compiled asset files.
	 */
	public function stepCompile($assets) {
		$compiled = array();
		foreach ($assets as $filename) {
			$compiledName = $this->assetCompiledName($filename);
			$fullInPath = $this->inPath() . '/' . $filename;
			$fullOutPath = $this->outPath() . '/' . $compiledName;

			if (!file_exists($fullOutPath)) {
				if (!file_exists($fullInPath)) {
					throw new Exception("Asset `$filename` cannot be found at `$fullInPath`.");
				}
				if ($this->config['purgeStale']) $this->purgeAsset($filename);
				if ($this->needsCompileStep($filename)) {
					$this->compileFile($fullInPath, $fullOutPath);
				} else {
					$this->copy($fullInPath, $fullOutPath);
				}
			}
			$compiled[] = $fullOutPath;
		}
		return $compiled;
	}

	/**
	 * Resolves embedded manifests and lazy asset names.
	 *
	 * @param array $assets array of asset names
	 * @return array of asset names
	 */
	public function stepResolveAssets($assets) {
		$seenAssets = array();
		$seenManifests = array("{$this->name}.manifest" => true);
		$filenameQueue = array_reverse($assets);
		while (!is_null($item = array_pop($filenameQueue))) {
			if ('manifest' === pathinfo($item, PATHINFO_EXTENSION)) {
				if (isset($seenManifests[$item])) continue;
				$seenManifests[$item] = true;
				$item = pathinfo($item, PATHINFO_FILENAME);
				if (!isset($this->config['manifests'][$this->type][$item])) {
					throw new Exception("Embedded manifest `{$this->type}/$item` not found.");
				}
				$embeddedAssets = $this->config['manifests'][$this->type][$item];
				$filenameQueue = array_merge($filenameQueue, array_reverse($embeddedAssets));
			} else {
				// Handle lazy asset names.
				// Resolving asset names can be expensive if your manifests are already built
				// so including extensions in asset names is recommended.
				if (!$lazyName = $this->lazyAssetName($item)) {
					throw new Exception("Couldn't find a valid asset matching `$item`.");
				}
				$seenAssets[$lazyName] = true;
			}
		}
		return array_keys($seenAssets);
	}

	// There are some cases where we can proceed without resolving lazy asset
	// names.
	protected function requiresFullAssetNames() {
		return (
			!$this->config['mergeAssets']
			|| (
				$this->config['cacheBuster']
				&& (
					$this->config['cacheBuster'] != 'string'
					|| $this->config['cacheCheckManifestSameAssets']
				)
			)
			|| !file_exists($this->manifestCompiledName())
		);
	}

	protected function purgeManifest() {
		$name = $this->name . '.manifest';
		foreach (glob("{$this->outPath()}/{$name}*") as $stale) {
			unlink($stale);
		}
	}

	protected function purgeAsset($filename) {
		foreach (glob("{$this->outPath()}/{$filename}*") as $stale) {
			unlink($stale);
		}
	}

	/**
	 * Typing a few extra characters for the sake of clarity is difficult. Help
	 * out developers that believe in magic by digging around for a similar
	 * asset. </sarcasm>
	 * If `$asset` has a valid extension, `$asset` is returned. If no
	 * extension is found, alternatives are tried ('coffee' then 'js' for js
	 * manifests; 'less' then 'css' for css manifests). An altered path is
	 * returned if an alternative asset is found, otherwise false is returned.
	 *
	 * @param string $asset relative asset name
	 */
	public function lazyAssetName($asset) {
		$altExts = array();
		switch ($this->type) {
			case 'js':
				$altExts = array('coffee', 'js');
				break;
			case 'css':
				$altExts = array('less', 'css');
				break;
		}
		if (in_array(pathinfo($asset, PATHINFO_EXTENSION), $altExts)) return $asset;
		$fullpath = $this->inPath() . '/' . $asset;
		foreach ($altExts as $ext) {
			$possible = "{$fullpath}.{$ext}";
			if (file_exists($possible)) return "{$asset}.{$ext}";
		}
		return false;
	}

	/**
	 * Removes a number of characters from the begining of each path equal to
	 * the `root` option's length, i.e., stupidly makes paths relative.
	 *
	 * @param array $paths array of string paths.
	 * @param string $root root path to use, leave blank to use the option value.
	 */
	protected function relativePaths(array $paths, $root = null) {
		$relative = array();
		$rootLength = $root ? strlen($root) : strlen($this->config['root']);
		foreach ($paths as $path) {
			$relative[] = substr($path, $rootLength);
		}
		return $relative;
	}

	protected function ensureExtension($name, $extension) {
		$nameExt = pathinfo($name, PATHINFO_EXTENSION);
		if ($nameExt !== $extension) return $name . '.' . $extension;
		return $name;
	}

	public function assetModTime($filename) {
		$fullPath = $this->inPath() . '/' . $filename;
		if (!file_exists($fullPath)) {
			throw new Exception("Asset `$filename` cannot be found at `$fullPath`.");
		}
		$mtime = filemtime($fullPath) ? : 0;

		// Check files imported by less assets.
		if ('css' === $this->type && $this->config['cacheCheckLessImports']) {
			if (!pathinfo($fullPath, PATHINFO_EXTENSION) === 'less') break;
			$imports = $this->relativePaths(
				$this->findLessImports($fullPath),
				$this->inPath() . '/'
			);
			foreach ($imports as $import) {
				$mtime = max($mtime, $this->assetModTime($import));
			}
		}

		return $mtime;
	}

	protected function findLessImports($fullPath) {
		$cmd = $this->less_binary . ' --depends ' . $fullPath . ' .';
		$cmd .= ' | tr -s " " "\n" | tail -n +2';
		exec($cmd, $result, $ret);
		if ($ret !== 0) {
			throw new Exception("Failed checking less imports for `{$fullPath}`.");
		}
		return $result;
	}

	public function assetCompiledName($filename) {
		switch ($this->config['cacheBuster']) {
			case 'modtime':
				$filename .= '.' . $this->assetModTime($filename);
				break;
			case 'string':
				$filename .= '.' . $this->config['cacheString'];
				break;
		}
		return $this->ensureExtension($filename, $this->type);
	}

	public function manifestCompiledName() {
		$name = $this->name . '.manifest';

		// Manifest contents can change without the modtime changing so keep track
		// of what assets are included in a manifest.
		if ($this->config['cacheBuster'] && $this->config['cacheCheckManifestSameAssets']) {
			$name .= '.' . md5(implode(',', $this->filenames));
		}

		switch ($this->config['cacheBuster']) {
			case 'modtime':
				$mtime = 0;
				foreach ($this->filenames as $filename) {
					$mtime = max($mtime, $this->assetModTime($filename));
				}
				$name .= '.' . $mtime;
				break;
			case 'string':
				$name .= '.' . $this->config['cacheString'];
				break;
		}
		return $this->ensureExtension($name, $this->type);
	}

	protected function outPath() {
		return $this->inPath() . "/compiled";
	}

	protected function inPath() {
		return $this->config['root'] . "/" . $this->type;
	}

	protected function needsCompileStep($filename) {
		return preg_match("/\.coffee|\.less/", $filename);
	}

	protected function rmkdir($path) {
		$directory = pathinfo($path, PATHINFO_DIRNAME);
		if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
			throw new Exception("Error creating directory `$directory`.");
		}
	}

	protected function compileFile($path, $destination) {
		$start = microtime(true);
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
			throw new Exception("Cannot compile unknown type `{$destination}`.");
		}

		if ($ret != 0) {
			throw new Exception("Could not compile asset, cmd: `$cmd`, result: `$result[0]`.");
		} else if ($result) {
			$result = implode($result, "\n");
			$this->rmkdir($destination);
			file_put_contents($destination, $result);
		}
		$ms = (int)((microtime(true) - $start) * 1000);
		if ($this->config['verbose']) echo("  compiled $destination ($ms msec)\n");
	}

	protected function findBlessedFiles($filePath) {
		$blessed = glob($filePath . '.blessed*') ? : array();
		return $blessed;
	}

	protected function moveBlessedFiles($tmpPath, $realPath) {
		$files = array();
		$tmpInfo = pathinfo($tmpPath);
		$blessed = glob("{$tmpInfo['dirname']}/{$tmpInfo['filename']}*") ? : array();
		foreach ($blessed as $i => $in) {
			$out = "{$realPath}.blessed" . ($i + 1) . '.css';
			rename($in, $out);
			$files[] = $out;
		}
		return $files;
	}

	protected function blessFile($path) {
		// A bit of a hack to get around blessc's poor renaming...
		$pathInfo = pathinfo($path);
		$tmpPath = $pathInfo['dirname'] . '/' . md5($pathInfo['basename']) . '.css';

		$cmd = "{$this->bless_binary} --no-cache-buster --no-cleanup --no-imports {$path} {$tmpPath}";
		exec($cmd, $result, $ret);
		if ($ret !== 0) {
			throw new Exception("Could not bless asset: `{$cmd}`.");
		}

		return $this->moveBlessedFiles($tmpPath, $path);
	}

	protected function copy($path, $destination) {
		$this->rmkdir($destination);
		if (!copy($path, $destination)) {
			throw new Exception("Error copying `$path` to `$destination`.");
		}
	}

	protected function merge($path, $filenames) {
		$start = microtime(true);
		switch($this->type) {
		case 'js':
			$cmd = "uglifyjs " . implode($filenames, ' ') . " -o $path -m 2>/dev/null";
			exec($cmd, $result, $ret);
			break;
		case 'css':
			$cmd = "uglifycss " . implode($filenames, ' ') . " 2>/dev/null > $path";
			exec($cmd, $result, $ret);
			break;
		default:
			throw new Exception("Cannot merge unknown type `{$this->type}`.");
		}
		$ms = (int)((microtime(true) - $start) * 1000);
		if ($ret != 0) {
			throw new Exception("There was an error merging files, cmd: `$cmd`.");
		} else if ($this->config['verbose']) {
			echo("  wrote $path ($ms msec)\n");
		}
	}

}

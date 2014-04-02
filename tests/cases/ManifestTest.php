<?php

namespace li3_frontender\tests\cases;

use \Exception;
use org\bovigo\vfs\vfsStream;
use li3_frontender\Manifest;

class ManifestTest extends \lithium\test\Unit {

	public function defaults(array $options = array()) {
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
		$defaults['manifest'] = array(
			'css' => array(),
			'js' => array(
				'simple'
			),
		);
		$defaults['root'] = vfsStream::setup('aDir');
		return $options + $defaults;
	}

	// TODO Does li3 provide a way to override library configuration so that
	//      Manifest::load can be tested and used instead of this?
	public function load($type, $name, array $options = array()) {
		$config = $this->defaults($options);
		if (!isset($config['manifests'][$type][$name])) {
			throw new Exception("Manifest `$type/$name` not found.");
		}
		$assets = $config['manifests'][$type][$name];
		return new Manifest($type, $name, $assets, $options);
	}

	public function testMissingManifest() {
		$self = $this;
		$this->assertException('Exception', function () use ($self) {
			//TODO yea, this doesn't make any sense; consider it a placeholder for
			//     Manifest::load
			$self->load('js', 'zzzzzzz');
		});
	}

	public function testAssetOrder() {
	}

	public function testTrue() {
		$this->assertTrue(true);
	}

}

?>

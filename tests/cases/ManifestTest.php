<?php

namespace li3_frontender\tests\cases;

use \Exception;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamPrintVisitor;
use li3_frontender\Manifest;

class ManifestTest extends \lithium\test\Unit {

	public function setUp() {
		$this->vfs = vfsStream::setup('webroot', null, array(
			'css' => array(
				'lazy1.css' => '',
				'lazy2.less' => '',
				'lazy2.css' => '',
			),
			'js' => array(
				'first.js' => 'script stuff',
				'second.js' => '',
				'third.js' => '',
				'begin.js' => '',
				'end.js' => '',
				'unique.js' => '',
				'lazy1.js' => '',
				'lazy2.coffee' => '',
				'lazy2.js' => '',
			),
		));
		// useful
		//vfsStream::inspect(new vfsStreamPrintVisitor());
	}

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
			'root'                         => 'vfs://webroot',
			'verbose'                      => false,
		);
		$defaults['manifests'] = array(
			'css' => array(
				'empty' => array(),
			),
			'js' => array(
				'empty' => array(),
				'ordered' => array(
					'first',
					'second.js',
					'third',
				),
				'dups' => array(
					'third',
					'third.js',
					'unique',
					'second',
					'third',
					'third.js',
				),
				'embeddedOrdered' => array(
					'begin',
					'ordered.manifest',
					'end',
					'dups.manifest',
				),
			),
		);
		return $options + $defaults;
	}

	// TODO Does li3 provide a way to override library configuration so that
	//      Manifest::load can be tested and used instead of this?
	public function load($type, $name, array $options = array()) {
		$options = $this->defaults($options);
		if (!isset($options['manifests'][$type][$name])) {
			throw new Exception("Manifest `$type/$name` not found.");
		}
		$assets = $options['manifests'][$type][$name];
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

	public function testLazyNames() {
		$manifest = $this->load('js', 'empty');
		$this->assertIdentical('lazy1.js', $manifest->lazyAssetName('lazy1'));
		$this->assertIdentical('lazy2.coffee', $manifest->lazyAssetName('lazy2'));
		$this->assertIdentical('lazy1.js', $manifest->lazyAssetName('lazy1.js'));
		$this->assertIdentical('lazy2.js', $manifest->lazyAssetName('lazy2.js'));
		$this->assertIdentical(false, $manifest->lazyAssetName('missing'));

		$manifest = $this->load('css', 'empty');
		$this->assertIdentical('lazy1.css', $manifest->lazyAssetName('lazy1'));
		$this->assertIdentical('lazy2.less', $manifest->lazyAssetName('lazy2'));
		$this->assertIdentical('lazy1.css', $manifest->lazyAssetName('lazy1.css'));
		$this->assertIdentical('lazy2.css', $manifest->lazyAssetName('lazy2.css'));
		$this->assertIdentical(false, $manifest->lazyAssetName('missing'));
	}

	public function testDups() {
		$manifest = $this->load('js', 'dups', array('cacheBuster' => false));
		$assets = $manifest->build();
		$this->assertIdentical(array(
			'/js/compiled/third.js',
			'/js/compiled/unique.js',
			'/js/compiled/second.js',
		), $assets);
	}

	public function testAssetOrder() {
		$manifest = $this->load('js', 'ordered', array('cacheBuster' => false));
		$assets = $manifest->build();
		$this->assertIdentical(array(
			'/js/compiled/first.js',
			'/js/compiled/second.js',
			'/js/compiled/third.js',
		), $assets);
	}

	public function testEmbeddedOrder() {
		$manifest = $this->load('js', 'embeddedOrdered', array('cacheBuster' => false));
		$assets = $manifest->build();
		$this->assertIdentical(array(
			'/js/compiled/begin.js',
			'/js/compiled/first.js',
			'/js/compiled/second.js',
			'/js/compiled/third.js',
			'/js/compiled/end.js',
			'/js/compiled/unique.js',
		), $assets);
	}

}

?>

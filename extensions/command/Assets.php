<?php

namespace li3_frontender\extensions\command;

use li3_frontender\Manifest;

class Assets extends \lithium\console\Command {

	public function run() {
		$manifests = Manifest::all();
		foreach($manifests as $manifest) {
			$manifest->verbose = true;
			$manifest->compile();
		}
	}

}
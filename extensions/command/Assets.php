<?php

namespace li3_frontender\extensions\command;

use li3_frontender\Manifest;

class Assets extends \lithium\console\Command {

	public function run() {
		try {
			$manifests = Manifest::all(array(
				'blessCss' => true,
				'verbose' => true,
			));
			foreach ($manifests as $manifest) {
				$manifest->build();
			}
		} catch (\Exception $e) {
			$this->stop(1, $e->getMessage());
		}
	}

}

?>

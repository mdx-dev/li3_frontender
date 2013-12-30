<?php

use lithium\core\Libraries;

/**
 * Setting paths for composer repos or submodule repos
 */

// Submodule Repos
if($options['source'] !== 'composer'){
	$_symfony_path = FRONTENDER_LIBS . "/Symfony";
	$_lessc_path = FRONTENDER_LIBS . "/lessphp";
	$_assetic_path = FRONTENDER_SRC;
// Composer vendor/package repos
} else {
	$_symfony_path = LITHIUM_APP_PATH . "/libraries/_source/symfony/process/Symfony";
	$_lessc_path = LITHIUM_APP_PATH . "/libraries/_source/leafo/lessphp";
	$_assetic_path = LITHIUM_APP_PATH . "/libraries/_source/kriswallsmith/assetic/src/Assetic";
}

/**
 * Symfony dependancies for Assetic
 */
Libraries::add("Symfony", array(
	"path" => $_symfony_path,
	"bootstrap" => false,
));

/**
 * Assetic Library
 */
Libraries::add("Assetic", array(
	"path" => $_assetic_path,
	"bootstrap" => false,
));
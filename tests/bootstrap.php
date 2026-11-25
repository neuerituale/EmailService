<?php

/**
 * PHPUnit bootstrap: boots the ProcessWire installation that contains this module.
 *
 * Set the environment variable PW_ROOT to use another installation.
 */

namespace ProcessWire;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = getenv('PW_ROOT') ?: '';
if ($root === '') {
	$dir = dirname(__DIR__);
	while ($dir !== dirname($dir)) {
		if (is_file("$dir/wire/core/ProcessWire.php") && is_file("$dir/index.php")) {
			$root = $dir;
			break;
		}
		$dir = dirname($dir);
	}
}

$root = rtrim((string) $root, '/');
if ($root === '' || !is_file("$root/index.php")) {
	fwrite(STDERR, "ProcessWire installation not found. Set PW_ROOT to its root directory.\n");
	exit(1);
}

// ProcessWire's index.php checks the working directory for site detection
chdir($root);
ob_start();
include "$root/index.php";
ob_end_clean();

if (!isset($wire) || !$wire instanceof ProcessWire) {
	fwrite(STDERR, "ProcessWire did not boot from $root/index.php.\n");
	exit(1);
}

if (!$wire->modules->isInstalled('EmailService') || !$wire->modules->isInstalled('ProcessEmailLog')) {
	fwrite(STDERR, "EmailService and ProcessEmailLog must be installed in $root.\n");
	exit(1);
}

require_once __DIR__ . '/TestCase.php';

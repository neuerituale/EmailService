<?php

namespace ProcessWire;

/**
 * Wrapper for plain text emails. Copy to /site/templates/email/wrapper.txt.php.
 *
 * Optional. Used for templates with a .txt.php version, e.g. for a signature with
 * the sender address. Text generated from the HTML already contains wrapper.php.
 * The file name follows the wrapper setting: wrapper.php → wrapper.txt.php.
 *
 * Receives all template data plus:
 *
 * @var string $content Rendered text template
 * @var string $subject Email subject (set automatically by EmailService::send())
 * @var Config $config
 */

$siteName = $config->httpHost;
$homeUrl = $config->urls->httpRoot;

// "-- " (with trailing space) separates the signature, mail clients recognize it
?>
<?= $content ?>


-- 
<?= $siteName ?>

<?= $homeUrl ?>

<?php

namespace ProcessWire;

/**
 * Welcome email (plain text). Optional – without it the text part is generated from the HTML.
 *
 * __() returns translations HTML-encoded, so they are decoded for plain text.
 *
 * @var string $userName
 * @var string $loginUrl
 */

$plain = fn(string $text): string => html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

?>
<?= sprintf($plain(__('Welcome, %s!')), $userName) ?>


<?= $plain(__('Great to have you on board. Your account is set up and you can sign in right away:')) ?>


<?= $loginUrl ?>


<?= $plain(__('If you did not sign up, you can ignore this email.')) ?>

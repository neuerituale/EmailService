<?php

namespace ProcessWire;

/**
 * Welcome email (HTML). Copy to /site/templates/email/welcome.php.
 *
 * Texts are translatable via Setup > Languages (file site/templates/email/welcome.php).
 *
 * @var string $userName Name of the new user
 * @var string $loginUrl URL to the login page
 */

?>
<h1><?= sprintf(__('Welcome, %s!'), $sanitizer->entities($userName)) ?></h1>

<p><?= __('Great to have you on board. Your account is set up and you can sign in right away.') ?></p>

<p><a class="button" href="<?= $sanitizer->entities($loginUrl) ?>"><?= __('Sign in now') ?></a></p>

<p class="muted"><?= __('If you did not sign up, you can ignore this email.') ?></p>

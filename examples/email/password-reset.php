<?php

namespace ProcessWire;

/**
 * Password reset email (HTML). Copy to /site/templates/email/password-reset.php.
 *
 * Texts are translatable via Setup > Languages (file site/templates/email/password-reset.php).
 *
 * @var string $resetLink URL to reset the password
 * @var string $code Verification code
 * @var int $validMinutes How long the link is valid
 */

?>
<h1><?= __('Reset your password') ?></h1>

<p><?= __('You requested to reset your password. Your verification code:') ?></p>

<p><span class="code"><?= $sanitizer->entities($code) ?></span></p>

<p><a class="button" href="<?= $sanitizer->entities($resetLink) ?>"><?= __('Set new password') ?></a></p>

<p class="muted"><?= sprintf(__('The link is valid for %d minutes. If this was not you, you can ignore this email.'), (int) ($validMinutes ?? 60)) ?></p>

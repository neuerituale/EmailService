<?php

namespace ProcessWire;

/**
 * Password reset email (HTML). Copy to /site/templates/email/password-reset.php.
 *
 * @var string $resetLink URL to reset the password
 * @var string $code Verification code
 * @var int $validMinutes How long the link is valid
 */

?>
<h1>Passwort zurücksetzen</h1>

<p>Du hast angefordert, dein Passwort zurückzusetzen. Dein Bestätigungscode:</p>

<p><span class="code"><?= $sanitizer->entities($code) ?></span></p>

<p><a class="button" href="<?= $sanitizer->entities($resetLink) ?>">Neues Passwort festlegen</a></p>

<p class="muted">Der Link ist <?= (int) ($validMinutes ?? 60) ?> Minuten gültig. Falls du das nicht warst, kannst du diese E-Mail ignorieren.</p>

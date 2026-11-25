<?php

namespace ProcessWire;

/**
 * Welcome email (HTML). Copy to /site/templates/email/welcome.php.
 *
 * @var string $userName Name of the new user
 * @var string $loginUrl URL to the login page
 */

?>
<h1>Willkommen, <?= $sanitizer->entities($userName) ?>!</h1>

<p>Schön, dass du dabei bist. Dein Konto ist eingerichtet und du kannst dich ab sofort anmelden.</p>

<p><a class="button" href="<?= $sanitizer->entities($loginUrl) ?>">Jetzt anmelden</a></p>

<p class="muted">Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.</p>

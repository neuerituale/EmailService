<?php

namespace ProcessWire;

/**
 * EmailService examples – paste the parts you need into /site/ready.php
 * or into the ready() method of your own module.
 *
 * @var ProcessWire $wire
 */

/* ***** 1. Register templates for the preview in Setup > Email Log *** */

$wire->addHookAfter('EmailService::registerTemplates', function(HookEvent $event) {
	/** @var EmailService $service */
	$service = $event->object;

	$service->registerTemplate('welcome', 'Willkommens-E-Mail', [
		'subject' => 'Willkommen!',
		'preheader' => 'Dein Konto ist bereit.',
		'userName' => 'Max Mustermann',
		'loginUrl' => 'https://example.com/login/',
	]);

	$service->registerTemplate('password-reset', 'Passwort zurücksetzen', [
		'subject' => 'Passwort zurücksetzen',
		'resetLink' => 'https://example.com/reset/?token=abc123',
		'code' => '123456',
		'validMinutes' => 60,
	]);
});

/* ***** 2. Send an email *** */

// /** @var EmailService $emailService */
// $emailService = $modules->get('EmailService');
// $sent = $emailService->send([
// 	'to' => $user->email,
// 	'subject' => 'Willkommen!',
// 	'template' => 'welcome',
// 	'data' => [
// 		'preheader' => 'Dein Konto ist bereit.',
// 		'userName' => $user->title ?: $user->name,
// 		'loginUrl' => $pages->get('template=login')->httpUrl,
// 	],
// ]);
// if (!$sent) $session->error('E-Mail konnte nicht gesendet werden.');

/* ***** 3. Tag mails sent directly via WireMail in the log *** */

// Mails that other modules send via $mail->new()->send() are logged as well,
// but without template name. Assign one by subject, recipient etc.:
$wire->addHookAfter('EmailService::detectTemplate', function(HookEvent $event) {
	/** @var WireMail $mail */
	$mail = $event->arguments(0);
	if (str_contains((string) $mail->subject, 'Passwort')) {
		$event->return = 'password-reset';
	}
});

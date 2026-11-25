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

	// A closure as sample data is called in the language shown in the preview,
	// so __() returns the translated subject and preheader.
	$service->registerTemplate('welcome', __('Welcome email'), fn() => [
		'subject' => __('Welcome!'),
		'preheader' => __('Your account is ready.'),
		'userName' => 'Max Mustermann',
		'loginUrl' => 'https://example.com/login/',
	]);

	$service->registerTemplate('password-reset', __('Password reset'), fn() => [
		'subject' => __('Reset your password'),
		'resetLink' => 'https://example.com/reset/?token=abc123',
		'code' => '123456',
		'validMinutes' => 60,
	]);
});

/* ***** 2. Send an email *** */

// /** @var EmailService $emailService */
// $emailService = $modules->get('EmailService');
// // Render in the recipient's language; withLanguage() also translates the subject
// $sent = $emailService->withLanguage($user->language, fn() => $emailService->send([
// 	'to' => $user->email,
// 	'subject' => __('Welcome!'),
// 	'template' => 'welcome',
// 	'data' => [
// 		'preheader' => __('Your account is ready.'),
// 		'userName' => $user->title ?: $user->name,
// 		'loginUrl' => $pages->get('template=login')->httpUrl,
// 	],
// ]));
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

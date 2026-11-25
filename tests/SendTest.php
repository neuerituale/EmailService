<?php

namespace ProcessWire;

final class SendTest extends TestCase
{
	public function testSendRequiresRecipientAndTemplate(): void
	{
		$service = $this->service();

		$this->assertFalse($service->send(['template' => 'basic']));
		$this->assertFalse($service->send(['to' => 'a@phpunit.test']));
		$this->assertFalse($service->send([]));

		$this->assertSame([], $this->sentMails);
		$this->assertSame([], $this->logRows());
	}

	public function testSendMissingTemplate(): void
	{
		$this->assertFalse($this->service()->send(['to' => 'a@phpunit.test', 'template' => 'does-not-exist']));

		$this->assertSame([], $this->sentMails);
		$this->assertSame([], $this->logRows());
		$this->assertCount(1, $this->logLines);
	}

	public function testSendBuildsMailAndLogsIt(): void
	{
		$sent = $this->service()->send([
			'to' => 'ada@phpunit.test',
			'subject' => 'Hello Ada',
			'template' => 'basic',
			'data' => ['name' => 'Ada'],
			'cc' => 'cc@phpunit.test',
			'bcc' => 'bcc@phpunit.test',
		]);

		$this->assertTrue($sent);
		$this->assertCount(1, $this->sentMails);
		$mail = $this->sentMails[0];

		$this->assertSame(['ada@phpunit.test' => 'ada@phpunit.test'], $mail->to);
		$this->assertSame('sender@example.test', $mail->from);
		$this->assertSame('Test Sender', $mail->fromName);
		$this->assertSame('Hello Ada', $mail->subject);
		$this->assertStringContainsString('<h1>Hello Ada</h1>', $mail->bodyHTML);
		$this->assertStringContainsString('<title>Hello Ada</title>', $mail->bodyHTML, 'The subject is passed to the wrapper.');
		$this->assertSame('Text for Ada', $mail->body);
		$this->assertCopyRecipients($mail, 'cc', ['cc@phpunit.test']);
		$this->assertCopyRecipients($mail, 'bcc', ['bcc@phpunit.test']);

		$rows = $this->logRows();
		$this->assertCount(1, $rows, 'The mail is logged exactly once (not again by the WireMail::send hook).');
		$this->assertSame('ada@phpunit.test', $rows[0]['to_email']);
		$this->assertSame('sender@example.test', $rows[0]['from_email']);
		$this->assertSame('Hello Ada', $rows[0]['subject']);
		$this->assertSame('basic', $rows[0]['template']);
		$this->assertSame('sent', $rows[0]['status']);
		$this->assertSame($mail->bodyHTML, $rows[0]['body_html']);
		$this->assertSame('Text for Ada', $rows[0]['body_text']);
		$this->assertEqualsWithDelta(time(), (int) $rows[0]['created'], 5);
	}

	public function testSubjectOptionIsUsedForMailAndDataWinsInTemplate(): void
	{
		// the subject option is used for the mail
		$this->service()->send([
			'to' => 'ada@phpunit.test',
			'subject' => 'Option subject',
			'template' => 'subject',
			'data' => ['subject' => 'Data subject'],
		]);

		$mail = $this->sentMails[0];
		$this->assertSame('Option subject', $mail->subject);
		// data wins inside the template (array_merge order), documented behavior
		$this->assertStringContainsString('SUBJECT:Data subject', $mail->bodyHTML);
	}

	public function testCopyRecipientsAreSanitized(): void
	{
		$this->service()->send([
			'to' => 'a@phpunit.test',
			'template' => 'basic',
			'cc' => ' one@phpunit.test, not-an-email ,two@phpunit.test,one@phpunit.test',
			'bcc' => ["evil@phpunit.test\r\nBcc: victim@example.test", 'ok@phpunit.test', ''],
		]);

		$mail = $this->sentMails[0];
		$this->assertCopyRecipients($mail, 'cc', ['one@phpunit.test', 'two@phpunit.test']);
		$this->assertCopyRecipients($mail, 'bcc', ['ok@phpunit.test']);
	}

	public function testEmptyCopyRecipientsAddNoHeader(): void
	{
		$this->service()->send(['to' => 'a@phpunit.test', 'template' => 'basic', 'cc' => '', 'bcc' => []]);

		$headers = (array) $this->sentMails[0]->get('header');
		$this->assertArrayNotHasKey('Cc', $headers);
		$this->assertArrayNotHasKey('Bcc', $headers);
	}

	/**
	 * CC/BCC are set via cc()/bcc() if the mailer has them, otherwise as header.
	 */
	private function assertCopyRecipients(WireMail $mail, string $type, array $expected): void
	{
		if (method_exists($mail, $type) || method_exists($mail, "___$type")) {
			$this->assertEqualsCanonicalizing($expected, array_values((array) $mail->get($type)));
			return;
		}
		$headers = (array) $mail->get('header');
		$name = $type === 'cc' ? 'Cc' : 'Bcc';
		$this->assertArrayHasKey($name, $headers);
		$this->assertSame(implode(', ', $expected), $headers[$name]);
		$this->assertStringNotContainsString("\n", $headers[$name]);
	}

	public function testSendFailureIsLogged(): void
	{
		$this->mailResult = 0;

		$this->assertFalse($this->service()->send(['to' => 'fail@phpunit.test', 'template' => 'basic']));

		$rows = $this->logRows();
		$this->assertCount(1, $rows);
		$this->assertSame('failed', $rows[0]['status']);
	}

	public function testSendGeneratesTextPartFromHtml(): void
	{
		$this->service()->send(['to' => 'a@phpunit.test', 'template' => 'no-text']);

		$this->assertSame("Only HTML\n\nFirst paragraph\n\nSecond <b> paragraph\n\nWRAPPER-FOOTER", $this->sentMails[0]->body);
	}

	public function testSendFromFallsBackToAdminEmail(): void
	{
		$service = $this->service();
		$service->defaultFrom = '';
		$config = $this->wire()->config;
		$saved = $config->adminEmail;
		$config->adminEmail = 'admin@example.test';

		try {
			$service->send(['to' => 'a@phpunit.test', 'template' => 'basic']);
		} finally {
			$config->adminEmail = $saved;
		}

		$this->assertSame('admin@example.test', $this->sentMails[0]->from);
	}

	public function testSendExplicitFrom(): void
	{
		$this->service()->send([
			'to' => 'a@phpunit.test',
			'template' => 'basic',
			'from' => 'other@example.test',
			'fromName' => 'Other',
		]);

		$this->assertSame('other@example.test', $this->sentMails[0]->from);
		$this->assertSame('Other', $this->sentMails[0]->fromName);
	}

	public function testSendToMultipleRecipients(): void
	{
		$this->service()->send(['to' => ['a@phpunit.test', 'b@phpunit.test'], 'template' => 'basic']);

		$this->assertCount(2, $this->sentMails[0]->to);
		$this->assertSame('a@phpunit.test, b@phpunit.test', $this->logRows()[0]['to_email']);
	}

	public function testSendInLanguage(): void
	{
		$language = $this->otherLanguage();
		$user = $this->wire()->user;
		$user->setQuietly('language', $this->defaultLanguage());

		$this->assertTrue($this->service()->send(['to' => 'a@phpunit.test', 'template' => 'language', 'language' => $language]));

		$mail = $this->sentMails[0];
		$this->assertStringContainsString("LANG:$language->name", $mail->bodyHTML);
		$this->assertSame("TEXT-LANG:$language->name", $mail->body);
		$this->assertSame('default', $user->language->name, 'The user language is restored after sending.');
	}

	public function testSendWithUnknownLanguage(): void
	{
		if (!$this->service()->hasLanguageSupport()) $this->markTestSkipped('Requires LanguageSupport.');

		$this->assertFalse($this->service()->send(['to' => 'a@phpunit.test', 'template' => 'basic', 'language' => 'no-such-language']));

		$this->assertSame([], $this->sentMails);
		$this->assertSame([], $this->logRows());
		$this->assertStringContainsString('Unknown language', $this->logLines[0]);
	}

	public function testLongValuesAreTruncatedInLog(): void
	{
		$subject = str_repeat('ä', 300);
		$this->service()->send(['to' => 'a@phpunit.test', 'subject' => $subject, 'template' => 'basic']);

		$row = $this->logRows()[0];
		$this->assertSame(255, mb_strlen($row['subject']));
		$this->assertSame(mb_substr($subject, 0, 255), $row['subject']);
	}

	public function testDirectWireMailIsLogged(): void
	{
		$this->addHook('EmailService::detectTemplate', function(HookEvent $event) {
			$mail = $event->arguments(0);
			if ($mail->subject === 'Direct') $event->return = 'detected';
		});

		$mail = $this->wire()->mail->new();
		$mail->to('direct@phpunit.test')->from('x@example.test')->subject('Direct')->body('Plain')->bodyHTML('<p>Html</p>');
		$this->assertSame(1, $mail->send());

		$rows = $this->logRows();
		$this->assertCount(1, $rows);
		$this->assertSame('detected', $rows[0]['template']);
		$this->assertSame('x@example.test', $rows[0]['from_email']);
		$this->assertSame('Plain', $rows[0]['body_text']);
		$this->assertSame('<p>Html</p>', $rows[0]['body_html']);
		$this->assertSame('sent', $rows[0]['status']);
	}

	public function testDirectWireMailWithoutTemplate(): void
	{
		$this->mailResult = 0;
		$this->wire()->mail->new()->to('direct@phpunit.test')->subject('Other')->body('Plain')->send();

		$rows = $this->logRows();
		$this->assertCount(1, $rows);
		$this->assertSame('', $rows[0]['template']);
		$this->assertSame('failed', $rows[0]['status']);
	}

	public function testResendCreatesNewLogEntry(): void
	{
		$id = $this->insertLogRow([
			'to_email' => 'resend@phpunit.test',
			'subject' => 'Original',
			'template' => 'basic',
			'body_html' => '<p>Stored HTML</p>',
			'body_text' => 'Stored text',
			'created' => time() - 3600,
		]);
		$entry = $this->callMethod($this->process(), 'getEntry', [$id]);

		$this->assertTrue($this->service()->resend($entry));

		$mail = $this->sentMails[0];
		$this->assertSame('Original', $mail->subject);
		$this->assertSame('<p>Stored HTML</p>', $mail->bodyHTML);
		$this->assertSame('Stored text', $mail->body);

		$rows = $this->logRows('resend@phpunit.test');
		$this->assertCount(2, $rows);
		$this->assertSame('basic', $rows[1]['template']);
	}

	public function testPruneLogEntries(): void
	{
		$service = $this->service();
		$old = $this->insertLogRow(['to_email' => 'old@phpunit.test', 'created' => time() - 11 * 86400]);
		$new = $this->insertLogRow(['to_email' => 'new@phpunit.test', 'created' => time() - 9 * 86400]);

		$service->retainDays = 0;
		$service->pruneLogEntries();
		$this->assertCount(2, $this->logRows(), 'retainDays = 0 keeps everything.');

		$service->retainDays = 10;
		$service->pruneLogEntries();

		$ids = array_map('intval', array_column($this->logRows(), 'id'));
		$this->assertNotContains($old, $ids);
		$this->assertContains($new, $ids);
	}
}

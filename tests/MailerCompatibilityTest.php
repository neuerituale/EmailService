<?php

namespace ProcessWire;

/**
 * Behavior with third-party mail modules (WireMailSmtp and similar).
 */
final class MailerCompatibilityTest extends TestCase
{
	/**
	 * Let $mail->new() return the given mailer for the rest of the test.
	 */
	private function useMailer(WireMail $mailer): void
	{
		$this->addHook('WireMailTools::new', function(HookEvent $event) use ($mailer) {
			$event->return = $mailer;
		});
	}

	public function testCcSwitchesSmtpMailerToSingleSend(): void
	{
		$mailer = new SingleSendFakeMail();
		$this->useMailer($mailer);

		$this->assertTrue($this->service()->send([
			'to' => 'a@phpunit.test',
			'template' => 'basic',
			'cc' => 'copy@phpunit.test',
		]));

		$this->assertSame($mailer, $this->sentMails[0]);
		$this->assertSame(['copy@phpunit.test'], $mailer->ccList);
		$this->assertTrue($mailer->single, 'WireMailSmtp only delivers CC with sendSingle(true).');
	}

	public function testWithoutCcTheSendModeIsUnchanged(): void
	{
		$mailer = new SingleSendFakeMail();
		$this->useMailer($mailer);

		$this->service()->send(['to' => 'a@phpunit.test', 'template' => 'basic', 'bcc' => 'b@phpunit.test']);

		$this->assertFalse($mailer->single);
	}

	public function testCcWithMultipleRecipientsLogsWarning(): void
	{
		$mailer = new SingleSendFakeMail();
		$this->useMailer($mailer);

		$this->service()->send([
			'to' => ['a@phpunit.test', 'b@phpunit.test'],
			'template' => 'basic',
			'cc' => 'copy@phpunit.test',
		]);

		$this->assertFalse($mailer->single);
		$this->assertStringContainsString('ignores CC recipients', implode("\n", $this->logLines));
	}

	public function testCcWithCoreWireMailUsesHeader(): void
	{
		$this->useMailer(new WireMail());

		$this->service()->send(['to' => 'a@phpunit.test', 'template' => 'basic', 'cc' => 'copy@phpunit.test']);

		$this->assertSame('copy@phpunit.test', $this->sentMails[0]->get('header')['Cc'] ?? null);
		$this->assertSame([], $this->logLines);
	}

	public function testLogContainsSenderUsedByMailer(): void
	{
		$this->useMailer(new ForcedSenderFakeMail());

		$this->service()->send(['to' => 'forced@phpunit.test', 'template' => 'basic', 'from' => 'given@example.test']);

		$this->assertSame('forced@example.test', $this->sentMails[0]->from);
		$this->assertSame('forced@example.test', $this->logRows('forced@phpunit.test')[0]['from_email']);
	}

	public function testResendLogsSenderUsedByMailer(): void
	{
		$id = $this->insertLogRow(['to_email' => 'resend@phpunit.test', 'from_email' => 'given@example.test']);
		$entry = $this->callMethod($this->process(), 'getEntry', [$id]);
		$this->useMailer(new ForcedSenderFakeMail());

		$this->service()->resend($entry);

		$rows = $this->logRows('resend@phpunit.test');
		$this->assertCount(2, $rows);
		$this->assertSame('forced@example.test', $rows[1]['from_email']);
	}

	public function testExceptionOfMailerIsLoggedAsFailed(): void
	{
		$this->mailException = new WireException('SMTP connection refused');

		$this->assertFalse($this->service()->send(['to' => 'error@phpunit.test', 'template' => 'basic']));

		$rows = $this->logRows('error@phpunit.test');
		$this->assertCount(1, $rows);
		$this->assertSame('failed', $rows[0]['status']);
		$this->assertSame('sender@example.test', $rows[0]['from_email']);
		$this->assertStringContainsString('Sending to error@phpunit.test via', end($this->logLines));
		$this->assertStringContainsString('SMTP connection refused', end($this->logLines));
	}

	public function testExceptionWhileBuildingTheMailIsLoggedAsFailed(): void
	{
		// the core WireMail throws for an invalid recipient
		$this->assertFalse($this->service()->send(['to' => 'not-an-email', 'template' => 'basic']));

		$this->assertSame([], $this->sentMails);
		$rows = $this->logRows('not-an-email');
		$this->assertCount(1, $rows);
		$this->assertSame('failed', $rows[0]['status']);
		$this->assertSame('sender@example.test', $rows[0]['from_email'], 'Falls back to the given sender.');
	}

	public function testExceptionOnResendIsLoggedAsFailed(): void
	{
		$id = $this->insertLogRow(['to_email' => 'resend-error@phpunit.test']);
		$entry = $this->callMethod($this->process(), 'getEntry', [$id]);
		$this->mailException = new \RuntimeException('API error');

		$this->assertFalse($this->service()->resend($entry));

		$rows = $this->logRows('resend-error@phpunit.test');
		$this->assertCount(2, $rows);
		$this->assertSame('failed', $rows[1]['status']);
	}

	public function testDirectWireMailExceptionIsNotSwallowed(): void
	{
		$this->mailException = new WireException('SMTP connection refused');

		$this->expectException(WireException::class);
		$this->wire()->mail->new()->to('direct@phpunit.test')->subject('Direct')->body('Text')->send();
	}
}

/**
 * Like WireMailSmtp: cc() and bcc() methods, CC is only delivered with sendSingle(true).
 */
class SingleSendFakeMail extends WireMail
{
	public array $ccList = [];
	public array $bccList = [];
	public bool $single = false;

	public function cc($email = null): static
	{
		$this->ccList = array_values((array) $email);
		return $this;
	}

	public function bcc($email = null): static
	{
		$this->bccList = array_values((array) $email);
		return $this;
	}

	public function sendSingle($singleMail = true): static
	{
		$this->single = (bool) $singleMail;
		return $this;
	}
}

/**
 * Like WireMailSmtp with "force sender": always uses its own sender address.
 */
class ForcedSenderFakeMail extends WireMail
{
	public function from($email, $name = null): static
	{
		return parent::from('forced@example.test', 'Forced');
	}
}

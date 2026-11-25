<?php

namespace ProcessWire;

/**
 * Resending from the log detail page (ProcessEmailLog::processResend) incl. CSRF protection.
 */
final class ResendTest extends TestCase
{
	private Page $adminPage;
	private int $entryId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->adminPage = $this->useAdminPage();
		$this->entryId = $this->insertLogRow([
			'to_email' => 'resend@phpunit.test',
			'subject' => 'Stored subject',
			'template' => 'basic',
			'body_html' => '<p>Stored HTML</p>',
			'body_text' => 'Stored text',
		]);
		$this->wire()->notices->removeAll();
	}

	private function csrf(): array
	{
		$csrf = $this->wire()->session->CSRF;
		return [$csrf->getTokenName() => $csrf->getTokenValue()];
	}

	private function detailUrl(int $id): string
	{
		return $this->adminPage->url . 'detail/?id=' . $id;
	}

	private function lastNotice(): ?Notice
	{
		$notices = $this->wire()->notices;
		return count($notices) ? $notices->last() : null;
	}

	public function testValidRequestResends(): void
	{
		$this->setPost(['submit_resend' => '1', 'id' => (string) $this->entryId] + $this->csrf());

		$url = $this->process()->processResend();

		$this->assertSame($this->detailUrl($this->entryId), $url);
		$this->assertCount(1, $this->sentMails);
		$this->assertSame('Stored subject', $this->sentMails[0]->subject);
		$this->assertCount(2, $this->logRows('resend@phpunit.test'));
		$this->assertInstanceOf(NoticeMessage::class, $this->lastNotice());
		$this->assertStringContainsString('resend@phpunit.test', $this->lastNotice()->text);
	}

	public function testFailedResendShowsError(): void
	{
		$this->mailResult = 0;
		$this->setPost(['submit_resend' => '1', 'id' => (string) $this->entryId] + $this->csrf());

		$this->assertSame($this->detailUrl($this->entryId), $this->process()->processResend());
		$this->assertInstanceOf(NoticeError::class, $this->lastNotice());
		$rows = $this->logRows('resend@phpunit.test');
		$this->assertSame('failed', end($rows)['status']);
	}

	public function testMissingTokenIsRejected(): void
	{
		$this->setPost(['submit_resend' => '1', 'id' => (string) $this->entryId]);

		$this->assertRejected();
	}

	public function testWrongTokenIsRejected(): void
	{
		$name = array_key_first($this->csrf());
		$this->setPost(['submit_resend' => '1', 'id' => (string) $this->entryId, $name => 'wrong-token']);

		$this->assertRejected();
	}

	public function testTokenWithWrongNameIsRejected(): void
	{
		$value = current($this->csrf());
		$this->setPost(['submit_resend' => '1', 'id' => (string) $this->entryId, 'TOKEN_wrong' => $value]);

		$this->assertRejected();
	}

	public function testTokenInGetIsRejected(): void
	{
		// only POST counts, a link with the token in the query string must not resend
		$this->setGet(['submit_resend' => '1', 'id' => (string) $this->entryId] + $this->csrf());

		$this->assertSame($this->adminPage->url, $this->process()->processResend());
		$this->assertSame([], $this->sentMails);
	}

	public function testWithoutSubmitNothingHappens(): void
	{
		$this->setPost(['id' => (string) $this->entryId] + $this->csrf());

		$this->assertSame($this->adminPage->url, $this->process()->processResend());
		$this->assertSame([], $this->sentMails);
		$this->assertCount(0, $this->wire()->notices);
	}

	public function testUnknownEntry(): void
	{
		foreach (['999999999', '0', '-1', 'abc'] as $id) {
			$this->wire()->notices->removeAll();
			$this->setPost(['submit_resend' => '1', 'id' => $id] + $this->csrf());

			$this->assertSame($this->adminPage->url, $this->process()->processResend(), $id);
			$this->assertInstanceOf(NoticeError::class, $this->lastNotice(), $id);
		}
		$this->assertSame([], $this->sentMails);
	}

	public function testTokenCheckComesBeforeEntryLookup(): void
	{
		// an invalid token must not reveal whether an entry exists
		$this->setPost(['submit_resend' => '1', 'id' => '999999999']);

		$this->assertSame($this->detailUrl(999999999), $this->process()->processResend());
		$this->assertStringContainsString('token', $this->lastNotice()->text);
	}

	public function testDetailFormContainsToken(): void
	{
		$this->setGet(['id' => (string) $this->entryId]);
		$csrf = $this->csrf();

		$out = $this->process()->executeDetail();

		$this->assertMatchesRegularExpression(
			'~<form method="post" action="' . preg_quote($this->adminPage->url, '~') . 'resend/"[^>]*>\s*'
			. '<input type="hidden" name="id" value="' . $this->entryId . '">\s*'
			. '<input type=.hidden. name=.' . preg_quote(array_key_first($csrf), '~') . '. value=.' . preg_quote(current($csrf), '~') . '.~',
			$out
		);
		$this->assertStringContainsString('name="submit_resend"', $out);
	}

	private function assertRejected(): void
	{
		$this->assertSame($this->detailUrl($this->entryId), $this->process()->processResend());
		$this->assertSame([], $this->sentMails, 'No email is sent without a valid token.');
		$this->assertCount(1, $this->logRows('resend@phpunit.test'), 'No log entry is written.');
		$this->assertInstanceOf(NoticeError::class, $this->lastNotice());
		$this->assertStringContainsString('token', $this->lastNotice()->text);
	}
}

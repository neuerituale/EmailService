<?php

namespace ProcessWire;

/**
 * Deleting a single entry from the log detail page (ProcessEmailLog::processDeleteEntry).
 */
final class DeleteEntryTest extends TestCase
{
	private Page $adminPage;
	private int $entryId;
	private int $otherId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->adminPage = $this->useAdminPage();
		$this->entryId = $this->insertLogRow(['to_email' => 'delete@phpunit.test']);
		$this->otherId = $this->insertLogRow(['to_email' => 'keep@phpunit.test']);
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

	private function assertNothingDeleted(): void
	{
		$this->assertCount(1, $this->logRows('delete@phpunit.test'));
		$this->assertCount(1, $this->logRows('keep@phpunit.test'));
	}

	public function testValidRequestDeletesOnlyThisEntry(): void
	{
		$this->setPost(['submit_delete_entry' => '1', 'id' => (string) $this->entryId] + $this->csrf());

		$this->assertSame($this->adminPage->url, $this->process()->processDeleteEntry());

		$this->assertSame([], $this->logRows('delete@phpunit.test'));
		$this->assertCount(1, $this->logRows('keep@phpunit.test'), 'Other entries are kept.');
		$this->assertInstanceOf(NoticeMessage::class, $this->lastNotice());
		$this->assertStringContainsString('delete@phpunit.test', $this->lastNotice()->text);
		$this->assertSame([], $this->sentMails);
	}

	public function testMissingTokenIsRejected(): void
	{
		$this->setPost(['submit_delete_entry' => '1', 'id' => (string) $this->entryId]);

		$this->assertSame($this->detailUrl($this->entryId), $this->process()->processDeleteEntry());

		$this->assertNothingDeleted();
		$this->assertInstanceOf(NoticeError::class, $this->lastNotice());
		$this->assertStringContainsString('token', $this->lastNotice()->text);
	}

	public function testWrongTokenIsRejected(): void
	{
		$csrf = $this->csrf();
		$this->setPost(['submit_delete_entry' => '1', 'id' => (string) $this->entryId, array_key_first($csrf) => 'wrong']);

		$this->assertSame($this->detailUrl($this->entryId), $this->process()->processDeleteEntry());
		$this->assertNothingDeleted();
	}

	public function testTokenInGetIsRejected(): void
	{
		$this->setGet($this->csrf());
		$this->setPost(['submit_delete_entry' => '1', 'id' => (string) $this->entryId]);

		$this->assertSame($this->detailUrl($this->entryId), $this->process()->processDeleteEntry());
		$this->assertNothingDeleted();
	}

	public function testWithoutSubmitNothingHappens(): void
	{
		$this->setPost(['id' => (string) $this->entryId] + $this->csrf());

		$this->assertSame($this->adminPage->url, $this->process()->processDeleteEntry());
		$this->assertNothingDeleted();
		$this->assertNull($this->lastNotice());
	}

	public function testUnknownEntry(): void
	{
		$this->setPost(['submit_delete_entry' => '1', 'id' => '999999999'] + $this->csrf());

		$this->assertSame($this->adminPage->url, $this->process()->processDeleteEntry());
		$this->assertNothingDeleted();
		$this->assertInstanceOf(NoticeError::class, $this->lastNotice());
	}

	public function testInvalidIdsDeleteNothing(): void
	{
		foreach (['0', '-1', 'abc', '', $this->entryId . ' OR 1=1'] as $id) {
			$this->wire()->notices->removeAll();
			$this->setPost(['submit_delete_entry' => '1', 'id' => $id] + $this->csrf());
			$this->process()->processDeleteEntry();
			if ((int) $id === $this->entryId) {
				// (int) cast: "<id> OR 1=1" is the id itself, only that row may go
				$this->assertCount(1, $this->logRows('keep@phpunit.test'), "Input: $id");
				$this->entryId = $this->insertLogRow(['to_email' => 'delete@phpunit.test']);
				continue;
			}
			$this->assertNothingDeleted();
		}
	}

	public function testOnlySuperusersCanDelete(): void
	{
		$process = $this->process();
		$csrf = $this->csrf();
		$this->loginAsGuest();
		$this->setPost(['submit_delete_entry' => '1', 'id' => (string) $this->entryId] + $csrf);

		$this->assertSame($this->detailUrl($this->entryId), $process->processDeleteEntry());

		$this->assertNothingDeleted();
		$this->assertInstanceOf(NoticeError::class, $this->lastNotice());
		$this->assertStringContainsString('permission', $this->lastNotice()->text);
	}

	public function testDetailPageHasDeleteForm(): void
	{
		$this->setGet(['id' => (string) $this->entryId]);
		$csrf = $this->csrf();

		$out = $this->process()->executeDetail();

		$this->assertMatchesRegularExpression(
			'~<form method="post" action="' . preg_quote($this->adminPage->url, '~') . 'delete-entry/" data-confirm="Delete this email log entry\?">\s*'
			. '<input type="hidden" name="id" value="' . $this->entryId . '">\s*'
			. '<input type=.hidden. name=.' . preg_quote(array_key_first($csrf), '~') . '. value=.' . preg_quote(current($csrf), '~') . '.~',
			$out
		);
		$this->assertStringContainsString('name="submit_delete_entry"', $out);
		$this->assertStringNotContainsString('onclick=', $out, 'Confirmations use ProcessWire.confirm via data-confirm.');
		$this->assertStringNotContainsString('uk-width-1-1"', substr($out, strpos($out, 'email-actions')), 'The buttons are not full width.');
	}
}
